<?php

declare(strict_types=1);

namespace Test\Prometheus\Predis;

use Predis\Client;
use Prometheus\Storage\Predis;
use Test\Prometheus\AbstractHistogramTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension redis
 */
class HistogramWithPrefixTest extends AbstractHistogramTest
{
    public function configureAdapter(): void
    {
        $client = new Client([
            'host'   => REDIS_HOST,
            'prefix' => 'prefix:',
        ]);

        $client->connect();

        $this->adapter = Predis::fromClient($client);
        $this->adapter->wipeStorage();
    }
}
