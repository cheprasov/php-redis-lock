<?php
require 'vendor/autoload.php';

use RedisLock\RedisLock;
use RedisClient\ClientFactory;
use RedisClient\RedisClient;

// Create a new Redis instance
$Redis = ClientFactory::create([
    'server' => 'tcp://127.0.0.1:6379'
]);

// ...

/**
 * Safe update json in Redis storage
 * @param Redis $Redis
 * @param string $key
 * @param array $array
 * @throws Exception
 */
function updateJsonInRedis(RedisClient $Redis, $key, array $array) {
    // Create new Lock instance
    $Lock = new RedisLock($Redis, 'Lock_'.$key, RedisLock::FLAG_DO_NOT_THROW_EXCEPTIONS);

    // Acquire lock for 2 sec.
    // If lock has acquired in another thread then we will wait 3 second,
    // until another thread release the lock. Otherwise it throws a exception.
    if (!$Lock->acquire(2, 3)) {
        throw new Exception('Can\'t get a Lock');
    }

    // Get value from storage
    $json = $Redis->get($key);
    if (!$json) {
        $jsonArray = [];
    } else {
        $jsonArray = json_decode($json, true);
    }

    // Some operations with json
    $jsonArray = array_merge($jsonArray, $array);

    $json = json_encode($jsonArray);
    // Update key in storage
    $Redis->set($key, $json);

    // Release the lock
    // After $lock->release() another waiting thread (Lock) will be able to update json in storage
    $Lock->release();
}

updateJsonInRedis($Redis, 'json-key', ['for' => 1, 'bar' => 2]);
updateJsonInRedis($Redis, 'json-key', ['for' => 42, 'var' => 2016]);
