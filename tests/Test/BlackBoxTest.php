<?php

declare(strict_types=1);

namespace Test;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use PHPUnit\Framework\TestCase;

class BlackBoxTest extends TestCase
{
    /**
     * @var Client
     */
    private $client;

    /**
     * @var string
     */
    private $adapter;

    public function setUp(): void
    {
        $adapter = getenv('ADAPTER');
        if (is_string($adapter) === false) {
            self::fail('Env var "ADAPTER" not set');
        }

        $this->adapter = $adapter;
        $this->client = new Client(['base_uri' => 'http://nginx:80/']);
        $this->client->get('/examples/flush_adapter.php?adapter=' . $this->adapter);
    }

    /**
     * @test
     */
    public function gaugesShouldBeOverwritten(): void
    {
        die('test');
        $start = microtime(true);
        $promises = [
            $this->client->getAsync('/examples/some_gauge.php?c=0&adapter=' . $this->adapter),
            $this->client->getAsync('/examples/some_gauge.php?c=1&adapter=' . $this->adapter),
            $this->client->getAsync('/examples/some_gauge.php?c=2&adapter=' . $this->adapter),

        ];

        Promise\settle($promises)->wait();
        $end = microtime(true);
        echo "\ntime: " . ($end - $start) . "\n";

        $metricsResult = $this->client->get('/examples/metrics.php?adapter=' . $this->adapter);
        $body = (string)$metricsResult->getBody();
        echo "\nbody: " . $body . "\n";
        self::assertThat(
            $body,
            self::logicalOr(
                self::stringContains('test_some_gauge{type="blue"} 0'),
                self::stringContains('test_some_gauge{type="blue"} 1'),
                self::stringContains('test_some_gauge{type="blue"} 2')
            )
        );
    }

    /**
     * @test
     */
    public function countersShouldIncrementAtomically(): void
    {
        $start = microtime(true);
        $promises = [];
        $sum = 0;
        for ($i = 0; $i < 1100; $i++) {
            $promises[] =  $this->client->getAsync('/examples/some_counter.php?c=' . $i . '&adapter=' . $this->adapter);
            $sum += $i;
        }

        Promise\settle($promises)->wait();
        $end = microtime(true);
        echo "\ntime: " . ($end - $start) . "\n";

        $metricsResult = $this->client->get('/examples/metrics.php?adapter=' . $this->adapter);
        $body = (string)$metricsResult->getBody();

        self::assertThat($body, self::stringContains('test_some_counter{type="blue"} ' . $sum));
    }

    /**
     * @test
     */
    public function histogramsShouldIncrementAtomically(): void
    {
        $start = microtime(true);
        $promises = [
            $this->client->getAsync('/examples/some_histogram.php?c=0&adapter=' . $this->adapter),
            $this->client->getAsync('/examples/some_histogram.php?c=1&adapter=' . $this->adapter),
            $this->client->getAsync('/examples/some_histogram.php?c=2&adapter=' . $this->adapter),
            $this->client->getAsync('/examples/some_histogram.php?c=3&adapter=' . $this->adapter),
            $this->client->getAsync('/examples/some_histogram.php?c=4&adapter=' . $this->adapter),
            $this->client->getAsync('/examples/some_histogram.php?c=5&adapter=' . $this->adapter),
            $this->client->getAsync('/examples/some_histogram.php?c=6&adapter=' . $this->adapter),
            $this->client->getAsync('/examples/some_histogram.php?c=7&adapter=' . $this->adapter),
            $this->client->getAsync('/examples/some_histogram.php?c=8&adapter=' . $this->adapter),
            $this->client->getAsync('/examples/some_histogram.php?c=9&adapter=' . $this->adapter),
        ];

        Promise\settle($promises)->wait();
        $end = microtime(true);
        echo "\ntime: " . ($end - $start) . "\n";

        $metricsResult = $this->client->get('/examples/metrics.php?adapter=' . $this->adapter);
        $body = (string)$metricsResult->getBody();

        self::assertThat($body, self::stringContains(<<<EOF
test_some_histogram_bucket{type="blue",le="0.1"} 1
test_some_histogram_bucket{type="blue",le="1"} 2
test_some_histogram_bucket{type="blue",le="2"} 3
test_some_histogram_bucket{type="blue",le="3.5"} 4
test_some_histogram_bucket{type="blue",le="4"} 5
test_some_histogram_bucket{type="blue",le="5"} 6
test_some_histogram_bucket{type="blue",le="6"} 7
test_some_histogram_bucket{type="blue",le="7"} 8
test_some_histogram_bucket{type="blue",le="8"} 9
test_some_histogram_bucket{type="blue",le="9"} 10
test_some_histogram_bucket{type="blue",le="+Inf"} 10
test_some_histogram_count{type="blue"} 10
test_some_histogram_sum{type="blue"} 45
EOF
        ));
    }
}
