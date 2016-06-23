<?php
namespace Test;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use PHPUnit_Framework_TestCase;

class BlackBoxTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Client
     */
    private $client;

    public function setUp()
    {
        $this->client = new Client(['base_uri' => 'http://localhost:8080/']);
        $this->client->get('/examples/flush_redis.php');
    }

    /**
     * @test
     */
    public function gaugShouldBeOverwritten()
    {
        $start = microtime(true);
        $promises = [
            $this->client->getAsync('/examples/some_gauge.php?c=0'),
            $this->client->getAsync('/examples/some_gauge.php?c=1'),
            $this->client->getAsync('/examples/some_gauge.php?c=2'),

        ];

        Promise\settle($promises)->wait();
        $end = microtime(true);
        echo "\ntime: " . ($end - $start) . "\n";

        $metricsResult = $this->client->get('/examples/metrics.php');
        $body = (string)$metricsResult->getBody();
        echo "\nbody: " . $body . "\n";
        $this->assertThat(
            $body,
            $this->logicalOr(
                $this->stringContains('test_some_gauge{type="blue"} 0'),
                $this->stringContains('test_some_gauge{type="blue"} 1'),
                $this->stringContains('test_some_gauge{type="blue"} 2')
            )
        );
    }

    /**
     * @test
     */
    public function countersShouldIncrementAtomically()
    {
        $start = microtime(true);
        $promises = [
            $this->client->getAsync('/examples/some_counter.php?c=0'),
            $this->client->getAsync('/examples/some_counter.php?c=1'),
            $this->client->getAsync('/examples/some_counter.php?c=2'),
            $this->client->getAsync('/examples/some_counter.php?c=3'),
            $this->client->getAsync('/examples/some_counter.php?c=4'),
            $this->client->getAsync('/examples/some_counter.php?c=5'),
            $this->client->getAsync('/examples/some_counter.php?c=6'),
            $this->client->getAsync('/examples/some_counter.php?c=7'),
            $this->client->getAsync('/examples/some_counter.php?c=8'),
            $this->client->getAsync('/examples/some_counter.php?c=9'),
        ];

        Promise\settle($promises)->wait();
        $end = microtime(true);
        echo "\ntime: " . ($end - $start) . "\n";

        $metricsResult = $this->client->get('/examples/metrics.php');
        $body = (string)$metricsResult->getBody();

        $this->assertThat($body, $this->stringContains('test_some_counter{type="blue"} 45'));
    }

    /**
     * @test
     */
    public function histogramsShouldIncrementAtomically()
    {
        $start = microtime(true);
        $promises = [
            $this->client->getAsync('/examples/some_histogram.php?c=0'),
            $this->client->getAsync('/examples/some_histogram.php?c=1'),
            $this->client->getAsync('/examples/some_histogram.php?c=2'),
            $this->client->getAsync('/examples/some_histogram.php?c=3'),
            $this->client->getAsync('/examples/some_histogram.php?c=4'),
            $this->client->getAsync('/examples/some_histogram.php?c=5'),
            $this->client->getAsync('/examples/some_histogram.php?c=6'),
            $this->client->getAsync('/examples/some_histogram.php?c=7'),
            $this->client->getAsync('/examples/some_histogram.php?c=8'),
            $this->client->getAsync('/examples/some_histogram.php?c=9'),
        ];

        Promise\settle($promises)->wait();
        $end = microtime(true);
        echo "\ntime: " . ($end - $start) . "\n";

        $metricsResult = $this->client->get('/examples/metrics.php');
        $body = (string)$metricsResult->getBody();

        $this->assertThat($body, $this->stringContains(<<<EOF
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
