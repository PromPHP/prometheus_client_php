<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;

/**
 * @requires extension redis
 */
class RedisSentinelTest extends TestCase
{
    /**
     * @var \Redis
     */
    private $redisConnection;

    protected function setUp(): void
    {
        $this->redisConnection = new \Redis();
        $this->redisConnection->connect(REDIS_HOST);
        $this->redisConnection->flushAll();
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionOnConnectionFailureWithRedisSentinelNotEnabled(): void
    {
        $redis = new Redis(['host' => '/dev/null', 'sentinel' => ['host' => '/dev/null']]);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("Can't connect to Redis server");

        $redis->collect();
        $redis->wipeStorage();
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionOnConnectionFailure(): void
    {
        $redis = new Redis(['host' => '/dev/null', 'sentinel' => ['host' => '/dev/null', 'enable' => true]]);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("Can't connect to RedisSentinel server");

        $redis->collect();
        $redis->wipeStorage();
    }

    /**
     * @test
     */
    public function itShouldThrowExceptionWhenInjectedRedisIsNotConnected(): void
    {
        $connection = new \Redis();
        // @phpstan-ignore arguments.count
        
        $sentinel = version_compare((string)phpversion('redis'), '6.0', '>=') ? 
                        new \RedisSentinel(['host' => '/dev/null']) :
                        new \RedisSentinel('/dev/null');

        self::expectException(StorageException::class);
        self::expectExceptionMessageMatches("/Can't connect to RedisSentinel server\\..*/");

        Redis::fromExistingConnection($connection, $sentinel);
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionOnPrimaryFailure(): void
    {
        $redis = new Redis(['host' => '/dev/null', 'sentinel' => ['host' => '/dev/null', 'enable' => true, 'service' => 'dummy']]);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("Can't connect to RedisSentinel server");

        $redis->collect();
        $redis->wipeStorage();
    }

    /**
     * @test
     */
    public function itShouldGetMaster(): void
    { 
        $redis = new Redis(['host' => REDIS_HOST,
            'sentinel' => ['host' => REDIS_SENTINEL_HOST, 'enable' => true, 'service' => 'myprimary']
        ]);

        $redis->collect();
        $redis->wipeStorage();
        $this->expectNotToPerformAssertions();
    }
}
