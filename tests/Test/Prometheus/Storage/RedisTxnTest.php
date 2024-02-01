<?php

declare(strict_types=1);

namespace Test\Prometheus\Storage;

use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;
use Prometheus\Storage\RedisTxn;

/**
 * @requires extension redis
 */
class RedisTxnTest extends TestCase
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
        $redis = new RedisTxn(['host' => '/dev/null']);

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

        self::expectException(StorageException::class);
        self::expectExceptionMessage('Connection to Redis server not established');

        RedisTxn::fromExistingConnection($connection);
    }

    /**
     * @test
     */
    public function itShouldNotClearWholeRedisOnFlush(): void
    {
        $this->redisConnection->set('not a prometheus metric key', 'data');

        $redis    = RedisTxn::fromExistingConnection($this->redisConnection);
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

    /**
     * @test
     */
    public function itShouldOnlyConnectOnceOnSubsequentCalls(): void
    {
        $clientId = $this->redisConnection->rawCommand('client', 'id');
        $expectedClientId = 'id=' . ($clientId + 1) . ' ';
        $notExpectedClientId = 'id=' . ($clientId + 2) . ' ';

        $redis = new RedisTxn(['host' => REDIS_HOST]);

        $redis->collect();

        self::assertStringContainsString(
            $expectedClientId,
            $this->redisConnection->rawCommand('client', 'list')
        );
        self::assertStringNotContainsString(
            $notExpectedClientId,
            $this->redisConnection->rawCommand('client', 'list')
        );

        $redis->collect();

        self::assertStringContainsString(
            $expectedClientId,
            $this->redisConnection->rawCommand('client', 'list')
        );
        self::assertStringNotContainsString(
            $notExpectedClientId,
            $this->redisConnection->rawCommand('client', 'list')
        );
    }

    /**
     * @test
     */
    public function itShouldOnlyConnectOnceForInjectedRedisConnectionOnSubsequentCalls(): void
    {
        $clientId = $this->redisConnection->rawCommand('client', 'id');
        $expectedClientId = 'id=' . $clientId . ' ';
        $notExpectedClientId = 'id=' . ($clientId + 1) . ' ';

        $redis = RedisTxn::fromExistingConnection($this->redisConnection);

        $redis->collect();

        self::assertStringContainsString(
            $expectedClientId,
            $this->redisConnection->rawCommand('client', 'list')
        );
        self::assertStringNotContainsString(
            $notExpectedClientId,
            $this->redisConnection->rawCommand('client', 'list')
        );

        $redis->collect();

        self::assertStringContainsString(
            $expectedClientId,
            $this->redisConnection->rawCommand('client', 'list')
        );
        self::assertStringNotContainsString(
            $notExpectedClientId,
            $this->redisConnection->rawCommand('client', 'list')
        );
    }
}
