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
            $client->getAsync('/examples/some_request_uri.php?0'),
            $client->getAsync('/examples/some_request_uri.php?1'),
            $client->getAsync('/examples/some_request_uri.php?2'),
            $client->getAsync('/examples/some_request_uri.php?3'),
            $client->getAsync('/examples/some_request_uri.php?4'),
            $client->getAsync('/examples/some_request_uri.php?5'),
            $client->getAsync('/examples/some_request_uri.php?6'),
            $client->getAsync('/examples/some_request_uri.php?7'),
            $client->getAsync('/examples/some_request_uri.php?8'),
            $client->getAsync('/examples/some_request_uri.php?9'),
        ];

        Promise\settle($promises)->wait();
        $end = microtime(true);
        echo "time: " . ($end - $start) . "\n";

        $metricsResult = $client->get('/examples/metrics.php');
        var_dump((string)$metricsResult->getBody());
    }
}
