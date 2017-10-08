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
namespace RedisLock;

interface LockInterface {

    /**
     * Acquire the lock
     * @param int|float $lockTime in Seconds
     * @param int|float $waitTime in Seconds
     * @param int $sleep in Seconds
     * @return bool
     */
    public function acquire($lockTime, $waitTime = 0, $sleep = null);

    /**
     * Release the lock
     * @return bool
     */
    public function release();

    /**
     * Set a new time for acquired lock
     * @param int|float $lockTime
     * @return bool
     */
    public function update($lockTime);

    /**
     * Check this lock for acquired
     * @return bool
     */
    public function isAcquired();

    /**
     * Check this lock for acquired and not expired, and active yet
     * @return bool
     */
    public function isLocked();

    /**
     * Does lock exists or acquired anywhere else?
     * @return bool
     */
    public function isExists();

}
