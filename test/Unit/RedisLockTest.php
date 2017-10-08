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
namespace Test\Unit;

use RedisClient\RedisClient;
use RedisLock\RedisLock;
use RedisClient\ClientFactory;

class RedisLockTest extends \PHPUnit_Framework_TestCase {

    const TEST_KEY = 'testKey';

    const TEST_TOKEN = 'testToken';

    /**
     * @return RedisClient
     */
    protected function getRedis() {
        return ClientFactory::create();
    }

    protected function getRedisLockMock() {
        $RedisLockMock = $this->getMockBuilder(RedisLock::class)
            ->setMethods([
                'createToken',
                'isFlagExist',
            ])
            ->setConstructorArgs([
                $Memcached = $this->getRedis(),
                static::TEST_KEY
            ])
            ->getMock();

        return $RedisLockMock;
    }

    /**
     * @see RedisLock::createToken
     */
    public function testMethod_createToken() {
        $key = static::TEST_KEY;

        $Method = new \ReflectionMethod('\RedisLock\RedisLock', 'createToken');
        $Method->setAccessible(true);

        $result = $Method->invoke(new RedisLock($this->getRedis(), $key));
        $this->assertTrue(is_string($result));
        $this->assertEquals(1, preg_match('/^(\d+):(0\.\d+ \d+):(\d+)$/', $result, $matches));
        $this->assertEquals(posix_getpid(), (int) $matches[1]);
    }

    /**
     * @see RedisLock::isFlagExist
     */
    public function testMethod_isFlagExist() {
        $key = static::TEST_KEY;
        $Method = new \ReflectionMethod(RedisLock::class, 'isFlagExist');
        $Method->setAccessible(true);

        $RedisLock = new RedisLock($this->getRedis(), $key);
        $this->assertSame(false, $Method->invoke(
            $RedisLock,
            RedisLock::FLAG_DO_NOT_THROW_EXCEPTIONS
        ));

        $RedisLock = new RedisLock(
            $this->getRedis(), $key,
            RedisLock::FLAG_DO_NOT_THROW_EXCEPTIONS
        );
        $this->assertSame(true, $Method->invoke(
            $RedisLock,
            RedisLock::FLAG_DO_NOT_THROW_EXCEPTIONS
        ));
    }

}
