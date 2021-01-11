<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;

/**
 * @requires extension redis
 */
class RedisTest extends TestCase
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
    public function itShouldThrowAnExceptionOnConnectionFailure(): void
    {
        $redis = new Redis(['host' => '/dev/null']);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage("Can't connect to Redis server");

        $redis->collect();
        $redis->wipeStorage();
    }

    /**
     * @test
     */
    public function itShouldThrowExceptionWhenInjectedRedisIsNotConnected(): void
    {
        $connection = new \Redis();

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Connection to Redis server not established');

        Redis::fromExistingConnection($connection);
    }

    /**
     * @test
     */
    public function itShouldNotClearWholeRedisOnFlush(): void
    {
        $this->redisConnection->set('not a prometheus metric key', 'data');

        $redis    = Redis::fromExistingConnection($this->redisConnection);
        $registry = new CollectorRegistry($redis);

        // ensure flush is working correctly on large number of metrics
        for ($i = 0; $i < 1000; $i++) {
            $registry->getOrRegisterCounter('namespace', "counter_$i", 'counter help')->inc();
            $registry->getOrRegisterGauge('namespace', "gauge_$i", 'gauge help')->inc();
            $registry->getOrRegisterHistogram('namespace', "histogram_$i", 'histogram help')->observe(1);
        }
        $redis->wipeStorage();

        $redisKeys = $this->redisConnection->keys("*");
        self::assertThat(
            $redisKeys,
            self::equalTo([
                'not a prometheus metric key'
            ])
        );
    }
}
