<?php


namespace Test\Prometheus;

use PHPUnit_Framework_TestCase;
use Prometheus\Histogram;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class HistogramTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function itShouldObserveWithLabels()
    {
        $gauge = new Histogram('test', 'some_metric', 'this is for testing', array('foo', 'bar'), array(100, 200, 300));
        $gauge->observe(123, array('foo' => 'lalal', 'bar' => 'lululu'));
        $gauge->observe(245, array('foo' => 'lalal', 'bar' => 'lululu'));
        $this->assertThat(
            $gauge->getSamples(),
            $this->equalTo(
                array(
                    array(
                        'name' => 'test_some_metric_bucket',
                        'labelNames' => array('foo', 'bar', 'le'),
                        'labelValues' => array('lalal', 'lululu', 100),
                        'value' => 0,
                    ),
                    array(
                        'name' => 'test_some_metric_bucket',
                        'labelNames' => array('foo', 'bar', 'le'),
                        'labelValues' => array('lalal', 'lululu', 200),
                        'value' => 1,
                    ),
                    array(
                        'name' => 'test_some_metric_bucket',
                        'labelNames' => array('foo', 'bar', 'le'),
                        'labelValues' => array('lalal', 'lululu', 300),
                        'value' => 2,
                    ),
                    array(
                        'name' => 'test_some_metric_bucket',
                        'labelNames' => array('foo', 'bar', 'le'),
                        'labelValues' => array('lalal', 'lululu', '+Inf'),
                        'value' => 2,
                    ),
                    array(
                        'name' => 'test_some_metric_count',
                        'labelNames' => array('foo', 'bar'),
                        'labelValues' => array('lalal', 'lululu'),
                        'value' => 2,
                    ),
                    array(
                        'name' => 'test_some_metric_sum',
                        'labelNames' => array('foo', 'bar'),
                        'labelValues' => array('lalal', 'lululu'),
                        'value' => 368,
                    )
                )
            )
        );
    }

    /**
     * @test
     */
    public function itShouldObserveWithoutLabelWhenNoLabelsAreDefined()
    {
        $gauge = new Histogram('test', 'some_metric', 'this is for testing', array(), array(100, 200, 300));
        $gauge->observe(245);
        $this->assertThat(
            $gauge->getSamples(),
            $this->equalTo(
                array(
                    array(
                        'name' => 'test_some_metric_bucket',
                        'labelNames' => array('le'),
                        'labelValues' => array(100),
                        'value' => 0,
                    ),
                    array(
                        'name' => 'test_some_metric_bucket',
                        'labelNames' => array('le'),
                        'labelValues' => array(200),
                        'value' => 0,
                    ),
                    array(
                        'name' => 'test_some_metric_bucket',
                        'labelNames' => array('le'),
                        'labelValues' => array(300),
                        'value' => 1,
                    ),
                    array(
                        'name' => 'test_some_metric_bucket',
                        'labelNames' => array('le'),
                        'labelValues' => array('+Inf'),
                        'value' => 1,
                    ),
                    array(
                        'name' => 'test_some_metric_count',
                        'labelNames' => array(),
                        'labelValues' => array(),
                        'value' => 1,
                    ),
                    array(
                        'name' => 'test_some_metric_sum',
                        'labelNames' => array(),
                        'labelValues' => array(),
                        'value' => 245,
                    )
                )
            )
        );
    }

    /**
     * @test
     */
    public function itShouldObserveValuesOfTypeDouble()
    {

    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function itShouldThrowAnExceptionWhenTheBucketSizesAreNotIncreasing()
    {

    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function itShouldThrowAnExceptionWhenTheBucketsAreNotNumeric()
    {

    }
}
