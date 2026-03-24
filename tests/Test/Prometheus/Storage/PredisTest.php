<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use PHPUnit\Framework\TestCase;
use Predis\Client;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\StorageException;

class PredisTest extends TestCase
{
    /**
     * @var Client
     */
    private $predisConnection;

    protected function setUp(): void
    {
        $this->predisConnection = new Client(['host' => REDIS_HOST]);
        $this->predisConnection->flushall();
    }

    /**
     * @test
     */
    public function itShouldThrowAnExceptionOnConnectionFailure(): void
    {
        $predis = new Predis(['host' => '/dev/null']);

        $this->expectException(StorageException::class);
        $this->expectExceptionMessage('Cannot establish Redis Connection');

        $predis->wipeStorage();
    }

    /**
     * @test
     */
    public function itShouldNotClearWholeRedisOnFlush(): void
    {
        $this->predisConnection->set('not a prometheus metric key', 'data');

        $predis   = Predis::fromExistingConnection($this->predisConnection);
        $registry = new CollectorRegistry($predis);

        for ($i = 0; $i < 1000; $i++) {
            $registry->getOrRegisterCounter('namespace', "counter_$i", 'counter help')->inc();
            $registry->getOrRegisterGauge('namespace', "gauge_$i", 'gauge help')->inc();
            $registry->getOrRegisterHistogram('namespace', "histogram_$i", 'histogram help')->observe(1);
        }
        $predis->wipeStorage();

        $redisKeys = $this->predisConnection->keys('*');
        self::assertThat(
            $redisKeys,
            self::equalTo(['not a prometheus metric key'])
        );
    }
}
