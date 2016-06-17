<?php


namespace Test\Prometheus;


use PHPUnit_Framework_TestCase;
use Prometheus\Client;

class ClientTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $redis = new \Redis();
        $redis->connect('192.168.59.100');
        $redis->del(Client::PROMETHEUS_SAMPLE_KEYS);
    }

    /**
     * @test
     */
    public function itShouldSaveGaugesInRedis()
    {
        $client = new Client();
        $metric = $client->registerGauge('test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $metric->set(14, array('foo' => 'lalal', 'bar' => 'lululu'));
        $client->getGauge('test', 'some_metric')->set(34, array('foo' => 'lalal', 'bar' => 'lululu'));
        $client->flush();

        $client = new Client();
        $this->assertThat(
            $client->toText(),
            $this->equalTo(<<<EOF
# HELP test_some_metric this is for testing
# TYPE test_some_metric gauge
test_some_metric{foo="lalal",bar="lululu"} 34

EOF
            )
        );
    }

}
