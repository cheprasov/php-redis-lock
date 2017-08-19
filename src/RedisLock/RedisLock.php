<?php
/**
 * This file is part of RedisLock.
 * git: https://github.com/cheprasov/php-redis-lock
 *
 * (C) Alexander Cheprasov <cheprasov.84@ya.ru>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace RedisLock;

use RedisClient\Exception\ErrorResponseException;
use RedisClient\RedisClient;
use RedisLock\Exception\InvalidArgumentException;
use RedisLock\Exception\LockException;
use RedisLock\Exception\LockHasAcquiredAlreadyException;
use RedisLock\Exception\LostLockException;

class RedisLock implements LockInterface {

    const VERSION = '1.0.1';

    /**
     * @deprecated
     * @see FLAG_DO_NOT_THROW_EXCEPTIONS
     * Catch Lock exceptions and return false or null as result
     */
    const FLAG_CATCH_EXCEPTIONS = 1;

    /**
     * Do not throw exception, return false or null as result
     */
    const FLAG_DO_NOT_THROW_EXCEPTIONS = 1;

    /**
     * Sleep time between wait iterations, in seconds
     */
    const LOCK_DEFAULT_WAIT_SLEEP = 0.005;

    /**
     * Min lock time in seconds
     */
    const LOCK_MIN_TIME = 0.001;

    const LUA_SCRIPT_RELEASE_LOCK_SHA1 = '9bdce90060b1eb1923ba581ffba7051865f063d7';
    const LUA_SCRIPT_RELEASE_LOCK = '
        if (ARGV[1] == redis.call("GET", KEYS[1])) then
            return redis.call("DEL", KEYS[1]);
        end;
        return 0;
    ';

    const LUA_SCRIPT_UPDATE_LOCK_SHA1 = 'b414769872ec8518662b9f29e83fc691b0349f45';
    const LUA_SCRIPT_UPDATE_LOCK = '
        if (ARGV[1] == redis.call("GET", KEYS[1])) then
            return redis.call("PEXPIRE", KEYS[1], ARGV[2]);
        end;
        return 0;
    ';

    /**
     * @var RedisClient
     */
    protected $Redis;

    /**
     * @var string
     */
    protected $key;

    /**
     * @var string
     */
    protected $token;

    /**
     * Flags
     * @var int
     */
    protected $flags = 0;

    /**
     * @var bool
     */
    protected $isAcquired = false;

    /**
     * @var bool
     */
    protected $catchExceptions = false;

    /**
     * @param RedisClient $Redis
     * @param string $key
     * @param int $flags
     * @throws InvalidArgumentException
     */
    public function __construct(RedisClient $Redis, $key, $flags = 0) {
        if (!isset($key)) {
            throw new InvalidArgumentException('Invalid key for Lock');
        }
        $this->Redis = $Redis;
        $this->key = (string) $key;
        $this->flags = (int) $flags;

        $this->token = $this->createToken();
        $this->catchExceptions = $this->isFlagExist(self::FLAG_CATCH_EXCEPTIONS);
    }

    /**
     * @param int $flag
     * @return bool
     */
    protected function isFlagExist($flag) {
        return (bool) ($this->flags & $flag);
    }

    /**
     *
     */
    public function __destruct() {
        if ($this->isAcquired) {
            $this->release();
        }
    }

    /**
     * @return string
     */
    protected function createToken() {
        return posix_getpid() .':'. microtime() .':'. mt_rand(1, 9999);
    }

    /**
     * @param string $script
     * @param string $sha1
     * @param string[]|null $keys
     * @param string[]|null $args
     * @return mixed
     * @throws ErrorResponseException
     */
    protected function execLua($script, $sha1, $keys = null, $args = null) {
        try {
            return $this->Redis->evalsha($sha1, $keys, $args);
        } catch (ErrorResponseException $Ex) {
            if (0 === strpos($Ex->getMessage(), 'NOSCRIPT')) {
                return $this->Redis->eval($script, $keys, $args);
            }
            throw $Ex;
        }
    }

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     * @throws LockHasAcquiredAlreadyException
     */
    public function acquire($lockTime, $waitTime = 0, $sleep = null) {
        if ($lockTime < self::LOCK_MIN_TIME) {
            if ($this->catchExceptions) {
                return false;
            }
            throw new InvalidArgumentException('LockTime must be not less than '. self::LOCK_MIN_TIME. ' sec.');
        }
        if ($this->isAcquired) {
            if ($this->catchExceptions) {
                return false;
            }
            throw new LockHasAcquiredAlreadyException('Lock with key "'. $this->key .'" has acquired already');
        }

        $time = microtime(true);
        $exitTime = $waitTime + $time;
        $sleep = ($sleep ?: self::LOCK_DEFAULT_WAIT_SLEEP) * 1000000;

        do {
            if ($this->Redis->set($this->key, $this->token, null, $lockTime * 1000, 'NX')) {
                $this->isAcquired = true;
                return true;
            }
            if ($waitTime) {
                usleep($sleep);
            }
        } while ($waitTime && microtime(true) < $exitTime);

        $this->isAcquired = false;
        return false;
    }

    /**
     * @inheritdoc
     * @throws LockException
     */
    public function release() {
        if (!$this->isAcquired) {
            if ($this->catchExceptions) {
                return false;
            }
            throw new LockException('Lock "'. $this->key .'" is not acquired');
        }

        $result = $this->execLua(
            self::LUA_SCRIPT_RELEASE_LOCK,
            self::LUA_SCRIPT_RELEASE_LOCK_SHA1,
            [$this->key],
            [$this->token]
        );

        $this->isAcquired = false;

        if ($result) {
            return true;
        }

        if ($this->catchExceptions) {
            return false;
        }
        throw new LostLockException('Lock "'. $this->key .'" has lost.');
    }

    /**
     * @inheritdoc
     * @throws InvalidArgumentException
     * @throws LockException
     */
    public function update($lockTime) {
        if ($lockTime < self::LOCK_MIN_TIME) {
            if ($this->catchExceptions) {
                return false;
            }
            throw new InvalidArgumentException('LockTime must be not less than '. self::LOCK_MIN_TIME. ' sec.');
        }
        if (!$this->isAcquired) {
            if ($this->catchExceptions) {
                return false;
            }
            throw new LockException('Lock "'. $this->key .'" is not active');
        }

        $result = $this->execLua(
            self::LUA_SCRIPT_UPDATE_LOCK,
            self::LUA_SCRIPT_UPDATE_LOCK_SHA1,
            [$this->key],
            [$this->token, $lockTime * 1000]
        );

        if ($result) {
            return true;
        }

        if ($this->catchExceptions) {
            return false;
        }
        throw new LostLockException('Lost Lock "'. $this->key .'" on update.');
    }

    /**
     * @inheritdoc
     */
    public function isAcquired() {
        return $this->isAcquired;
    }

    /**
     * @inheritdoc
     * @throws LostLockException
     */
    public function isLocked() {
        if (!$this->isAcquired) {
            return false;
        }

        $token = $this->Redis->get($this->key);
        if ($token && $token === $this->token) {
            return true;
        }

        $this->isAcquired = false;

        if ($this->catchExceptions) {
            return false;
        }
        throw new LostLockException('Lost Lock "'. $this->key .'"');
    }

    /**
     * @inheritdoc
     */
    public function isExists() {
       return $this->Redis->get($this->key) ? true : false;
    }

}
