<?php

declare(strict_types=1);

namespace Test\Prometheus\RedisNg;

use Prometheus\Storage\Redis;
use Prometheus\Storage\RedisNg;
use Test\Prometheus\AbstractSummaryTestCase;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class SummaryTest extends AbstractSummaryTestCase
{
    public function configureAdapter(): void
    {
        $this->adapter = new RedisNg(['host' => REDIS_HOST]);
        $this->adapter->wipeStorage();
    }
}
