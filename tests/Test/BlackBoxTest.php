<?php
namespace Test;

use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use PHPUnit_Framework_TestCase;

class BlackBoxTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function countersShouldIncrementAtomically()
    {
        $client = new Client(['base_uri' => 'http://localhost:8080/']);
        $client->get('/examples/flush_redis.php');

        $start = microtime(true);
        $promises = [
            $client->getAsync('/examples/some_request_uri.php?c=0'),
            $client->getAsync('/examples/some_request_uri.php?c=1'),
            $client->getAsync('/examples/some_request_uri.php?c=2'),
            $client->getAsync('/examples/some_request_uri.php?c=3'),
            $client->getAsync('/examples/some_request_uri.php?c=4'),
            $client->getAsync('/examples/some_request_uri.php?c=5'),
            $client->getAsync('/examples/some_request_uri.php?c=6'),
            $client->getAsync('/examples/some_request_uri.php?c=7'),
            $client->getAsync('/examples/some_request_uri.php?c=8'),
            $client->getAsync('/examples/some_request_uri.php?c=9'),
        ];

        Promise\settle($promises)->wait();
        $end = microtime(true);
        echo "time: " . ($end - $start) . "\n";

        $metricsResult = $client->get('/examples/metrics.php');
        var_dump((string)$metricsResult->getBody());
    }
}
