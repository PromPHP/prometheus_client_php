<?php


namespace Test\Prometheus;


use PHPUnit_Framework_TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Exception\MetricsRegistrationException;
use Prometheus\Histogram;
use Prometheus\RenderTextFormat;
use Prometheus\Storage\Adapter;
use Prometheus\Storage\Redis;

abstract class AbstractCollectorRegistryTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Adapter
     */
    public $adapter;

    /**
     * @var RenderTextFormat
     */
    private $renderer;

    public function setUp()
    {
        $this->configureAdapter();
        $this->renderer = new RenderTextFormat();
    }

    /**
     * @test
     */
    public function itShouldSaveGaugesInRedis()
    {
        $registry = new CollectorRegistry($this->adapter);

        $g = $registry->registerGauge('test', 'some_metric', 'this is for testing', array('foo'));
        $g->set(32, array('lalal'));
        $g->set(35, array('lalab'));


        $registry = new CollectorRegistry($this->adapter);
        $this->assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            $this->equalTo(<<<EOF
# HELP test_some_metric this is for testing
# TYPE test_some_metric gauge
test_some_metric{foo="lalab"} 35
test_some_metric{foo="lalal"} 32

EOF
            )
        );
    }

    /**
     * @test
     */
    public function itShouldSaveCountersInRedis()
    {
        $registry = new CollectorRegistry($this->adapter);
        $metric = $registry->registerCounter('test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $metric->incBy(2, array('lalal', 'lululu'));
        $registry->getCounter('test', 'some_metric', array('foo', 'bar'))->inc(array('lalal', 'lululu'));
        $registry->getCounter('test', 'some_metric', array('foo', 'bar'))->inc(array('lalal', 'lvlvlv'));

        $registry = new CollectorRegistry($this->adapter);
        $this->assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            $this->equalTo(<<<EOF
# HELP test_some_metric this is for testing
# TYPE test_some_metric counter
test_some_metric{foo="lalal",bar="lululu"} 3
test_some_metric{foo="lalal",bar="lvlvlv"} 1

EOF
            )
        );
    }

    /**
     * @test
     */
    public function itShouldSaveHistogramsInRedis()
    {
        $registry = new CollectorRegistry($this->adapter);
        $metric = $registry->registerHistogram('test', 'some_metric', 'this is for testing', array('foo', 'bar'), array(0.1, 1, 5, 10));
        $metric->observe(2, array('lalal', 'lululu'));
        $registry->getHistogram('test', 'some_metric', array('foo', 'bar'))->observe(7.1, array('lalal', 'lvlvlv'));
        $registry->getHistogram('test', 'some_metric', array('foo', 'bar'))->observe(13, array('lalal', 'lululu'));
        $registry->getHistogram('test', 'some_metric', array('foo', 'bar'))->observe(7.1, array('lalal', 'lululu'));
        $registry->getHistogram('test', 'some_metric', array('foo', 'bar'))->observe(7.1, array('gnaaha', 'hihihi'));

        $registry = new CollectorRegistry($this->adapter);
        $this->assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            $this->equalTo(<<<EOF
# HELP test_some_metric this is for testing
# TYPE test_some_metric histogram
test_some_metric_bucket{foo="gnaaha",bar="hihihi",le="0.1"} 0
test_some_metric_bucket{foo="gnaaha",bar="hihihi",le="1"} 0
test_some_metric_bucket{foo="gnaaha",bar="hihihi",le="5"} 0
test_some_metric_bucket{foo="gnaaha",bar="hihihi",le="10"} 1
test_some_metric_bucket{foo="gnaaha",bar="hihihi",le="+Inf"} 1
test_some_metric_count{foo="gnaaha",bar="hihihi"} 1
test_some_metric_sum{foo="gnaaha",bar="hihihi"} 7.1
test_some_metric_bucket{foo="lalal",bar="lululu",le="0.1"} 0
test_some_metric_bucket{foo="lalal",bar="lululu",le="1"} 0
test_some_metric_bucket{foo="lalal",bar="lululu",le="5"} 1
test_some_metric_bucket{foo="lalal",bar="lululu",le="10"} 2
test_some_metric_bucket{foo="lalal",bar="lululu",le="+Inf"} 3
test_some_metric_count{foo="lalal",bar="lululu"} 3
test_some_metric_sum{foo="lalal",bar="lululu"} 22.1
test_some_metric_bucket{foo="lalal",bar="lvlvlv",le="0.1"} 0
test_some_metric_bucket{foo="lalal",bar="lvlvlv",le="1"} 0
test_some_metric_bucket{foo="lalal",bar="lvlvlv",le="5"} 0
test_some_metric_bucket{foo="lalal",bar="lvlvlv",le="10"} 1
test_some_metric_bucket{foo="lalal",bar="lvlvlv",le="+Inf"} 1
test_some_metric_count{foo="lalal",bar="lvlvlv"} 1
test_some_metric_sum{foo="lalal",bar="lvlvlv"} 7.1

EOF
            )
        );
    }

    /**
     * @test
     */
    public function itShouldSaveHistogramsWithoutLabels()
    {
        $registry = new CollectorRegistry($this->adapter);
        $metric = $registry->registerHistogram('test', 'some_metric', 'this is for testing');
        $metric->observe(2);
        $registry->getHistogram('test', 'some_metric')->observe(13);
        $registry->getHistogram('test', 'some_metric')->observe(7.1);

        $registry = new CollectorRegistry($this->adapter);
        $this->assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            $this->equalTo(<<<EOF
# HELP test_some_metric this is for testing
# TYPE test_some_metric histogram
test_some_metric_bucket{le="0.005"} 0
test_some_metric_bucket{le="0.01"} 0
test_some_metric_bucket{le="0.025"} 0
test_some_metric_bucket{le="0.05"} 0
test_some_metric_bucket{le="0.075"} 0
test_some_metric_bucket{le="0.1"} 0
test_some_metric_bucket{le="0.25"} 0
test_some_metric_bucket{le="0.5"} 0
test_some_metric_bucket{le="0.75"} 0
test_some_metric_bucket{le="1"} 0
test_some_metric_bucket{le="2.5"} 1
test_some_metric_bucket{le="5"} 1
test_some_metric_bucket{le="7.5"} 2
test_some_metric_bucket{le="10"} 2
test_some_metric_bucket{le="+Inf"} 3
test_some_metric_count 3
test_some_metric_sum 22.1

EOF
            )
        );
    }

    /**
     * @test
     */
    public function itShouldIncreaseACounterWithoutNamespace()
    {
        $registry = new CollectorRegistry( $this->adapter);
        $registry
            ->registerCounter('', 'some_quick_counter', 'just a quick measurement')
            ->inc();

        $this->assertThat(
            $this->renderer->render($registry->getMetricFamilySamples()),
            $this->equalTo(<<<EOF
# HELP some_quick_counter just a quick measurement
# TYPE some_quick_counter counter
some_quick_counter 1

EOF
            )
        );
    }

    /**
     * @test
     * @expectedException \Prometheus\Exception\MetricsRegistrationException
     */
    public function itShouldForbidRegisteringTheSameCounterTwice()
    {
        $registry = new CollectorRegistry( $this->adapter);
        $registry->registerCounter('foo', 'metric', 'help');
        $registry->registerCounter('foo', 'metric', 'help');
    }

    /**
     * @test
     * @expectedException \Prometheus\Exception\MetricsRegistrationException
     */
    public function itShouldForbidRegisteringTheSameCounterWithDifferentLabels()
    {
        $registry = new CollectorRegistry( $this->adapter);
        $registry->registerCounter('foo', 'metric', 'help', array("foo", "bar"));
        $registry->registerCounter('foo', 'metric', 'help', array("spam", "eggs"));
    }

    /**
     * @test
     * @expectedException \Prometheus\Exception\MetricsRegistrationException
     */
    public function itShouldForbidRegisteringTheSameHistogramTwice()
    {
        $registry = new CollectorRegistry( $this->adapter);
        $registry->registerHistogram('foo', 'metric', 'help');
        $registry->registerHistogram('foo', 'metric', 'help');
    }

    /**
     * @test
     * @expectedException \Prometheus\Exception\MetricsRegistrationException
     */
    public function itShouldForbidRegisteringTheSameHistogramWithDifferentLabels()
    {
        $registry = new CollectorRegistry( $this->adapter);
        $registry->registerCounter('foo', 'metric', 'help', array("foo", "bar"));
        $registry->registerCounter('foo', 'metric', 'help', array("spam", "eggs"));
    }

    /**
     * @test
     * @expectedException \Prometheus\Exception\MetricsRegistrationException
     */
    public function itShouldForbidRegisteringTheSameGaugeTwice()
    {
        $registry = new CollectorRegistry( $this->adapter);
        $registry->registerGauge('foo', 'metric', 'help');
        $registry->registerGauge('foo', 'metric', 'help');
    }

    /**
     * @test
     * @expectedException \Prometheus\Exception\MetricsRegistrationException
     */
    public function itShouldForbidRegisteringTheSameGaugeWithDifferentLabels()
    {
        $registry = new CollectorRegistry( $this->adapter);
        $registry->registerGauge('foo', 'metric', 'help', array("foo", "bar"));
        $registry->registerGauge('foo', 'metric', 'help', array("spam", "eggs"));
    }

    /**
     * @test
     * @expectedException \Prometheus\Exception\MetricNotFoundException
     */
    public function itShouldThrowAnExceptionWhenGettingANonExistentMetric()
    {
        $registry = new CollectorRegistry( $this->adapter);
        $registry->getGauge("not_here", "go_away");
    }

    public abstract function configureAdapter();
}
