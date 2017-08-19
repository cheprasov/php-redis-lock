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
namespace Test\Integration;

use RedisClient\ClientFactory;
use RedisLock\RedisLock;
use Parallel\Parallel;
use Parallel\Storage\MemcachedStorage;

class RedisLockParallelTest extends \PHPUnit_Framework_TestCase {

    /**
     * @var \Redis
     */
    protected static $Redis;

    protected function getRedis() {
        return ClientFactory::create([
            'server' => REDIS_TEST_SERVER,
            'version' => '3.2.8',
        ]);
    }

    public function test_parallel() {
        $Redis = $this->getRedis();
        $Redis->flushall();
        $this->assertSame(true, $Redis->set('testcount', '1000000'));
        unset($Redis);

        $Storage = new MemcachedStorage(
            ['servers'=>[explode(':', MEMCACHED_TEST_SERVER)]]
        );
        $Parallel = new Parallel($Storage);

        $start = microtime(true) + 2;

        // 1st operation
        $Parallel->run('foo', function() use ($start) {
            $RedisLock = new RedisLock($Redis = $this->getRedis(), 'lock_test');
            while (microtime(true) < $start) {
                // wait for start
            }
            $c = 0;
            for ($i = 1; $i <= 10000; ++$i) {
                if ($RedisLock->acquire(2, 3)) {
                    $count = (int) $Redis->get('testcount');
                    ++$count;
                    $Redis->set('testcount', $count);
                    $RedisLock->release();
                    ++$c;
                }
            }
            return $c;
        });

        // 2st operation
        $Parallel->run('bar', function() use ($start) {
            $RedisLock = new RedisLock($Redis = $this->getRedis(), 'lock_test');
            while (microtime(true) < $start) {
                // wait for start
            }
            $c = 0;
            for ($i = 1; $i <= 10000; ++$i) {
                if ($RedisLock->acquire(2, 3)) {
                    $count = (int) $Redis->get('testcount');
                    ++$count;
                    $Redis->set('testcount', $count);
                    $RedisLock->release();
                    ++$c;
                }
            }
            return $c;
        });

        $RedisLock = new RedisLock($Redis = $this->getRedis(), 'lock_test');
        while (microtime(true) < $start) {
            // wait for start
        }
        $c = 0;
        for ($i = 1; $i <= 10000; ++$i) {
            if ($RedisLock->acquire(2, 3)) {
                $count = (int) $Redis->get('testcount');
                ++$count;
                $Redis->set('testcount', $count);
                $RedisLock->release();
                ++$c;
            }
        }

        $result = $Parallel->wait(['foo', 'bar']);

        $this->assertSame(10000, (int) $result['foo']);
        $this->assertSame(10000, (int) $result['bar']);
        $this->assertSame(10000, $c);
        $this->assertSame(1030000, (int) $Redis->get('testcount'));

        $Redis->flushall();
    }

}
