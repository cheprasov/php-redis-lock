<?php
/**
 * This file is part of RedisLock.
 * git: https://github.com/cheprasov/php-redis-lock
 *
 * (C) Alexander Cheprasov <acheprasov84@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */
namespace Test\Integration;

use RedisClient\ClientFactory;
use RedisClient\RedisClient;
use RedisLock\Exception\InvalidArgumentException;
use RedisLock\Exception\LockException;
use RedisLock\Exception\LockHasAcquiredAlreadyException;
use RedisLock\Exception\LostLockException;
use RedisLock\RedisLock;

class RedisLockTest extends \PHPUnit_Framework_TestCase {

    const TEST_KEY = 'redisLockTestKey';

    const LOCK_MIN_TIME = 0.05;

    /**
     * @var RedisClient
     */
    protected static $Redis;

    public static function setUpBeforeClass() {
        static::$Redis = ClientFactory::create([
            'server' => REDIS_TEST_SERVER,
            'version' => '3.2.8',
        ]);
    }

    public function testRedis() {
        $this->assertInstanceOf(RedisClient::class, static::$Redis);
    }

    public function setUp() {
        $this->assertSame(true, static::$Redis->flushall());
    }

    public function test_RedisLock() {
        $key = static::TEST_KEY;
        $RedisLock = new RedisLock(static::$Redis, $key);

        try {
            $RedisLock->acquire(RedisLock::LOCK_MIN_TIME * 0.9);
            $this->assertFalse("Expect Exception " . InvalidArgumentException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(InvalidArgumentException::class, $Exception);
        }

        $this->assertTrue($RedisLock->acquire(self::LOCK_MIN_TIME * 2));

        try {
            $RedisLock->acquire(self::LOCK_MIN_TIME * 2);
            $this->assertFalse("Expect Exception " . LockHasAcquiredAlreadyException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(LockHasAcquiredAlreadyException::class, $Exception);
        }

        try {
            $RedisLock->acquire(RedisLock::LOCK_MIN_TIME * 0.9);
            $this->assertFalse("Expect Exception " . InvalidArgumentException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(InvalidArgumentException::class, $Exception);
        }

        $this->assertTrue($RedisLock->update(self::LOCK_MIN_TIME * 4));
        $this->assertTrue($RedisLock->update(self::LOCK_MIN_TIME * 3));
        $this->assertTrue($RedisLock->update(self::LOCK_MIN_TIME * 2));

        $this->assertTrue($RedisLock->isLocked());
        $this->assertTrue($RedisLock->isExists());

        $this->assertTrue($RedisLock->release());

        $this->assertFalse($RedisLock->isLocked());
        $this->assertFalse($RedisLock->isExists());

        try {
            $RedisLock->release();
            $this->assertFalse("Expect " . LockException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(LockException::class, $Exception);
        }

        try {
            $RedisLock->update(self::LOCK_MIN_TIME * 2);
            $this->assertFalse("Expect " . LockException::class);
        } catch (\Exception $Exception) {
            $this->assertInstanceOf(LockException::class, $Exception);
        }

        $this->assertFalse($RedisLock->isLocked());
        $this->assertFalse($RedisLock->isExists());
        $this->assertTrue($RedisLock->acquire(self::LOCK_MIN_TIME * 2));
        $this->assertTrue($RedisLock->update(self::LOCK_MIN_TIME * 2));
        $this->assertTrue($RedisLock->isLocked());
        $this->assertTrue($RedisLock->update(self::LOCK_MIN_TIME * 2));
        $this->assertTrue($RedisLock->isExists());
        $this->assertTrue($RedisLock->update(self::LOCK_MIN_TIME * 2));
        $this->assertTrue($RedisLock->isLocked());
        $this->assertTrue($RedisLock->release());
        $this->assertFalse($RedisLock->isLocked());
        $this->assertFalse($RedisLock->isExists());
    }

    public function test_RedisLock_WithoutExceptions() {
        $key = static::TEST_KEY;
        $RedisLock = new RedisLock(static::$Redis, $key, RedisLock::FLAG_DO_NOT_THROW_EXCEPTIONS);

        $this->assertFalse($RedisLock->acquire(RedisLock::LOCK_MIN_TIME * 0.9));
        $this->assertTrue($RedisLock->acquire(self::LOCK_MIN_TIME * 2));
        $this->assertFalse($RedisLock->acquire(self::LOCK_MIN_TIME * 2));
        $this->assertFalse($RedisLock->acquire(RedisLock::LOCK_MIN_TIME * 0.9));

        $this->assertTrue($RedisLock->update(self::LOCK_MIN_TIME * 4));
        $this->assertTrue($RedisLock->update(self::LOCK_MIN_TIME * 3));
        $this->assertTrue($RedisLock->update(self::LOCK_MIN_TIME * 2));

        $this->assertTrue($RedisLock->isLocked());
        $this->assertTrue($RedisLock->isExists());

        $this->assertTrue($RedisLock->release());

        $this->assertFalse($RedisLock->isLocked());
        $this->assertFalse($RedisLock->isExists());

        $this->assertFalse($RedisLock->release());

        $this->assertFalse($RedisLock->update(self::LOCK_MIN_TIME * 2));

        $this->assertFalse($RedisLock->isLocked());
        $this->assertFalse($RedisLock->isExists());
        $this->assertTrue($RedisLock->acquire(self::LOCK_MIN_TIME * 2));
        $this->assertTrue($RedisLock->update(self::LOCK_MIN_TIME * 2));
        $this->assertTrue($RedisLock->isLocked());
        $this->assertTrue($RedisLock->update(self::LOCK_MIN_TIME * 2));
        $this->assertTrue($RedisLock->isExists());
        $this->assertTrue($RedisLock->update(self::LOCK_MIN_TIME * 2));
        $this->assertTrue($RedisLock->isLocked());
        $this->assertTrue($RedisLock->release());
        $this->assertFalse($RedisLock->isLocked());
        $this->assertFalse($RedisLock->isExists());
    }

    public function test_RedisLock_LockTime() {
        $key = static::TEST_KEY;

        $RedisLock = new RedisLock(static::$Redis, $key);
        $RedisLock2 = new RedisLock(static::$Redis, $key);

        for ($i = 1; $i <= 5; $i++) {
            $microtime = microtime(true);

            $this->assertTrue($RedisLock->acquire(self::LOCK_MIN_TIME * $i));

            $this->assertTrue($RedisLock->isLocked());
            $this->assertTrue($RedisLock->isExists());

            $this->assertFalse($RedisLock2->isLocked());
            $this->assertTrue($RedisLock2->isExists());

            //$microtime = microtime(true);
            $this->assertTrue($RedisLock2->acquire(self::LOCK_MIN_TIME * $i, $i + 1));
            $waitTime = microtime(true) - $microtime;

            $this->assertTrue($RedisLock2->update(1));

            $this->assertGreaterThan(self::LOCK_MIN_TIME * $i - 1, $waitTime);
            $this->assertLessThanOrEqual(self::LOCK_MIN_TIME * $i + 1, $waitTime);

            try {
                $RedisLock->isLocked();
                $this->assertFalse('Expect LostLockException');
            } catch (\Exception $Ex) {
                $this->assertInstanceOf(LostLockException::class, $Ex);
            }

            $this->assertTrue($RedisLock->isExists());

            $this->assertTrue($RedisLock2->isLocked());
            $this->assertTrue($RedisLock2->isExists());

            $this->assertTrue($RedisLock2->release());
        }
    }

    public function test_RedisLock_WaitTime() {
        $key = static::TEST_KEY;
        $RedisLock = new RedisLock(static::$Redis, $key);
        $RedisLock2 = new RedisLock(static::$Redis, $key);

        for ($i = 1; $i <= 5; $i++) {
            $this->assertTrue($RedisLock->acquire(self::LOCK_MIN_TIME * $i));
            $this->assertFalse($RedisLock2->acquire(
                self::LOCK_MIN_TIME * $i,
                self::LOCK_MIN_TIME * ($i - 1))
            );
            $this->assertTrue($RedisLock->release());
        }

        for ($i = 1; $i <= 5; $i++) {
            $this->assertTrue($RedisLock->acquire(self::LOCK_MIN_TIME * $i));
            $this->assertTrue($RedisLock2->acquire(
                self::LOCK_MIN_TIME * $i,
                self::LOCK_MIN_TIME * ($i + 1)
            ));
            $this->assertTrue($RedisLock2->release());
            try {
                $this->assertTrue($RedisLock->release());
                $this->assertFalse('Expect LostLockException');
            } catch (\Exception $Ex) {
                $this->assertInstanceOf(LostLockException::class, $Ex);
            }
        }
    }

    public function test_RedisLock_Exceptions() {
        $key = static::TEST_KEY;
        $RedisLock = new RedisLock(static::$Redis, $key);

        $this->assertSame(true, $RedisLock->acquire(2));
        $this->assertSame(true, $RedisLock->isLocked());

        static::$Redis->del($key);

        try {
            $RedisLock->release();
            $this->assertFalse('Expect LostLockException');
        } catch (\Exception $Ex) {
            $this->assertInstanceOf(LostLockException::class, $Ex);
        }

        $this->assertSame(false, $RedisLock->isLocked());

        $this->assertSame(true, $RedisLock->acquire(2));
        $this->assertSame(true, $RedisLock->isLocked());

        static::$Redis->del($key);

        $this->assertSame(false, $RedisLock->isExists());
        try {
            $RedisLock->isLocked();
            $this->assertFalse('Expect LostLockException');
        } catch (\Exception $Ex) {
            $this->assertInstanceOf(LostLockException::class, $Ex);
        }
    }

}
