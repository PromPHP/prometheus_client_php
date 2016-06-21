<?php


namespace Test\Prometheus;


use PHPUnit_Framework_TestCase;
use Prometheus\Registry;
use Prometheus\RedisAdapter;

class RegistryTest extends PHPUnit_Framework_TestCase
{
    public function setUp()
    {
        $this->newRedisAdapter()->flushRedis();
    }

    /**
     * @test
     */
    public function itShouldSaveGaugesInRedis()
    {
        $client = new Registry($this->newRedisAdapter());
        $metric = $client->registerGauge('test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $metric->set(14, array('foo' => 'lalal', 'bar' => 'lululu'));
        $client->getGauge('test', 'some_metric', array('foo', 'bar'))->set(34, array('foo' => 'lalal', 'bar' => 'lululu'));

        $g = $client->registerGauge('test', 'some_metric', 'this is for testing', array('foo'));
        $g->set(32, array('foo' => 'lalal'));
        $g->set(35, array('foo' => 'lalab'));

        $client->flush();

        $client = new Registry($this->newRedisAdapter());
        $this->assertThat(
            $client->toText(),
            $this->equalTo(<<<EOF
# HELP test_some_metric this is for testing
# TYPE test_some_metric gauge
test_some_metric{foo="lalal"} 32
test_some_metric{foo="lalab"} 35
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
        $client = new Registry($this->newRedisAdapter());
        $metric = $client->registerCounter('test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $metric->increaseBy(2, array('foo' => 'lalal', 'bar' => 'lululu'));
        $client->getCounter('test', 'some_metric', array('foo', 'bar'))->increase(array('foo' => 'lalal', 'bar' => 'lululu'));
        $client->flush();

        $client = new Registry($this->newRedisAdapter());
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

    /**
     * @test
     */
    public function itShouldSaveHistogramsInRedis()
    {
        $client = new Registry($this->newRedisAdapter());
        $metric = $client->registerHistogram('test', 'some_metric', 'this is for testing', array('foo', 'bar'), array(0.1, 1, 5, 10));
        $metric->observe(2, array('foo' => 'lalal', 'bar' => 'lululu'));
        $client->getHistogram('test', 'some_metric', array('foo', 'bar'))->observe(13, array('foo' => 'lalal', 'bar' => 'lululu'));
        $client->getHistogram('test', 'some_metric', array('foo', 'bar'))->observe(7, array('foo' => 'lalal', 'bar' => 'lululu'));
        $client->flush();

        $client = new Registry($this->newRedisAdapter());
        $this->assertThat(
            $client->toText(),
            $this->equalTo(<<<EOF
# HELP test_some_metric this is for testing
# TYPE test_some_metric histogram
test_some_metric_bucket{foo="lalal",bar="lululu",le="0.1"} 0
test_some_metric_bucket{foo="lalal",bar="lululu",le="1"} 0
test_some_metric_bucket{foo="lalal",bar="lululu",le="5"} 1
test_some_metric_bucket{foo="lalal",bar="lululu",le="10"} 2
test_some_metric_bucket{foo="lalal",bar="lululu",le="+Inf"} 3
test_some_metric_count{foo="lalal",bar="lululu"} 3
test_some_metric_sum{foo="lalal",bar="lululu"} 22

EOF
            )
        );
    }

    private function newRedisAdapter()
    {
        return new RedisAdapter(REDIS_HOST);
    }
}
