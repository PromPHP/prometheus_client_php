<?php


namespace Test\Prometheus;


use PHPUnit_Framework_TestCase;
use Prometheus\Client;
use Prometheus\RedisAdapter;

class ClientTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->newRedisAdapter()->deleteMetrics();
    }

    /**
     * @test
     */
    public function itShouldSaveGaugesInRedis()
    {
        $client = new Client($this->newRedisAdapter());
        $metric = $client->registerGauge('test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $metric->set(14, array('foo' => 'lalal', 'bar' => 'lululu'));
        $client->getGauge('test', 'some_metric')->set(34, array('foo' => 'lalal', 'bar' => 'lululu'));
        $client->flush();

        $client = new Client($this->newRedisAdapter());
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

    /**
     * @test
     */
    public function itShouldSaveCountersInRedis()
    {
        $client = new Client($this->newRedisAdapter());
        $metric = $client->registerCounter('test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $metric->increaseBy(2, array('foo' => 'lalal', 'bar' => 'lululu'));
        $client->getCounter('test', 'some_metric')->increase(array('foo' => 'lalal', 'bar' => 'lululu'));
        $client->flush();

        $client = new Client($this->newRedisAdapter());
        $this->assertThat(
            $client->toText(),
            $this->equalTo(<<<EOF
# HELP test_some_metric this is for testing
# TYPE test_some_metric counter
test_some_metric{foo="lalal",bar="lululu"} 3

EOF
            )
        );
    }

    private function newRedisAdapter()
    {
        return new RedisAdapter('192.168.59.100');
    }
}
