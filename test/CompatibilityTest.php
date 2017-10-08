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
namespace Test;

class CompatibilityTest extends \PHPUnit_Framework_TestCase {

    public function test_compatibility() {
        if (!function_exists('posix_getpid')) {
            $this->markTestSkipped();
            return;
        }
        $this->assertSame(posix_getpid(), getmypid(), 'posix_getpid() != getmypid()');
    }

}
