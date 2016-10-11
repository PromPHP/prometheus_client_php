<?php
namespace Test;

use GuzzleHttp\Client;
use PHPUnit_Framework_TestCase;

use Prometheus\CollectorRegistry;
use Prometheus\PushGateway;
use Prometheus\Storage\APC;

class BlackBoxPushGatewayTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function pushGatewayShouldWork()
    {
        $adapter = new APC();
        $registry = new CollectorRegistry($adapter);

        $counter = $registry->registerCounter('test', 'some_counter', 'it increases', ['type']);
        $counter->incBy(6, ['blue']);

        $pushGateway = new PushGateway('pushgateway:9091');
        $pushGateway->push($registry, 'my_job', array('instance' => 'foo'));

        $httpClient = new Client();
        $metrics = $httpClient->get("http://pushgateway:9091/metrics")->getBody()->getContents();
        $this->assertContains(
            '# HELP test_some_counter it increases
# TYPE test_some_counter counter
test_some_counter{instance="foo",job="my_job",type="blue"} 6',
            $metrics
        );

        $pushGateway->delete('my_job', array('instance' => 'foo'));

        $httpClient = new Client();
        $metrics = $httpClient->get("http://pushgateway:9091/metrics")->getBody()->getContents();
        $this->assertNotContains(
            '# HELP test_some_counter it increases
# TYPE test_some_counter counter
test_some_counter{instance="foo",job="my_job",type="blue"} 6',
            $metrics
        );
    }
}
