<?php

namespace Prometheus\Storage;

use PHPUnit\Framework\TestCase;
use Prometheus\Exception\StorageException;

/**
 * @requires extension redis
 */
class RedisTest extends TestCase
{
    /**
     * @test
     * @expectedException StorageException
     * @expectedExceptionMessage Can't connect to Redis server
     */
    public function itShouldThrowAnExceptionOnConnectionFailure()
    {
        $redis = new Redis(['host' => 'completely.fake.example.test']);
        $redis->flushRedis();
    }

}
