[![MIT license](http://img.shields.io/badge/license-MIT-brightgreen.svg)](http://opensource.org/licenses/MIT)
[![Latest Stable Version](https://poser.pugx.org/cheprasov/php-redis-lock/v/stable)](https://packagist.org/packages/cheprasov/php-redis-lock)
[![Total Downloads](https://poser.pugx.org/cheprasov/php-redis-lock/downloads)](https://packagist.org/packages/cheprasov/php-redis-lock)

# RedisLock v1.0.3 for PHP >= 5.5

## About
RedisLock for PHP is a synchronization mechanism for enforcing limits on access to a resource in an environment where there are many threads of execution. A lock is designed to enforce a mutual exclusion concurrency control policy. Based on [redis](http://redis.io/).


## Usage

### Create a new instance of RedisLock

```php
<?php
require 'vendor/autoload.php';

use RedisLock\RedisLock;
use RedisClient\ClientFactory;
use RedisClient\RedisClient;

// Create a new Redis instance
$Redis = ClientFactory::create([
    'server' => 'tcp://127.0.0.1:6379'
]);

$Lock = new RedisLock(
    $Redis, // Instance of RedisClient,
    'key', // Key in storage,
);
```

### Usage for lock a process

```php
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

```

## Methods

#### RedisLock :: __construct ( `RedisClient` **$Redis** , `string` **$key** [, `int` **$flags** = 0 ] )
---
Create a new instance of RedisLock.

##### Method Pameters

1. RedisClient **$Redis** - Instanse of [RedisClient](https://github.com/cheprasov/php-redis-client)
2. string **$key** - name of key in Redis storage. Only locks with the same name will compete with each other for lock.
3. int **$flags**, default = 0
   * `RedisLock::FLAG_DO_NOT_THROW_EXCEPTIONS` - use this flag, if you don't want catch exceptions by yourself. Do not use this flag, if you want have a full control on situation with locks. Default behavior without this flag - all Exceptions will be thrown.

##### Example

```php
$Lock = new RedisLock($Redis, 'lockName');
// or
$Lock = new RedisLock($Redis, 'lockName', RedisLock::FLAG_DO_NOT_THROW_EXCEPTIONS);

```

#### `bool` RedisLock :: acquire ( `int|float` **$lockTime** , [ `float` **$waitTime** = 0 [, `float` **$sleep** = 0.005 ] ] )
---
Try to acquire lock for `$lockTime` seconds.
If lock has acquired in another thread then we will wait `$waitTime` seconds, until another thread release the lock.
Otherwise method throws a exception (if `FLAG_DO_NOT_THROW_EXCEPTIONS` is not set) or result.
Returns `true` on success or `false` on failure.

##### Method Pameters

1. int|float **$lockTime** - The time for lock in seconds, the value must be `>= 0.01`.
2. float **$waitTime**, default = 0 - The time for waiting lock in seconds. Use `0` if you don't wait until lock release.
3. float **$sleep**, default = 0.005 - The wait time between iterations to check the availability of the lock.

##### Example

```php
$Lock = new RedisLock($Redis, 'lockName');
$Lock->acquire(3, 4);
// ... do something
$Lock->release();
```

#### `bool` RedisLock :: update ( `int|float` **$lockTime** )
---
Set a new time for lock if it is acquired already. Returns `true` on success or `false` on failure. Method can throw Exceptions.

##### Method Pameters
1. int|float **$lockTime** - Please, see description for method `RedisLock :: acquire`

##### Example

```php
$Lock = new RedisLock($Redis, 'lockName');
$Lock->acquire(3, 4);
// ... do something
$Lock->update(3);
// ... do something
$Lock->release();
```

#### `bool` RedisLock :: isAcquired ( )
---
Check this lock for acquired. Returns `true` on success or `false` on failure.

#### `bool` RedisLock :: isLocked ( )
---
Check this lock for acquired and not expired, and active yet. Returns `true` on success or `false` on failure. Method can throw Exceptions.

#### `bool` RedisLock :: isExists ()
---
Does lock exists or acquired anywhere? Returns `true` if lock is exists or `false` if is not.

## Installation

### Composer

Download composer:

    wget -nc http://getcomposer.org/composer.phar

and add dependency to your project:

    php composer.phar require cheprasov/php-redis-lock

## Running tests

To run tests type in console:

    ./vendor/bin/phpunit
	
## Dependencies

Depending on your PHP version you will need to install Posix library.
Check php-posix or php-process

## Something doesn't work

Feel free to fork project, fix bugs and finally request for pull
