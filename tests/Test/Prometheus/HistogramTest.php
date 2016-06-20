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
                        'name' => 'test_some_metric',
                        'labels' => array(
                            array('name' => 'foo', 'value' => 'lalal'),
                            array('name' => 'bar', 'value' => 'lululu'),
                            array('name' => 'le', 'value' => 100)
                        ),
                        'value' => 0,
                        'help' => 'this is for testing',
                        'type' => Histogram::TYPE
                    ),
                    array(
                        'name' => 'test_some_metric',
                        'labels' => array(
                            array('name' => 'foo', 'value' => 'lalal'),
                            array('name' => 'bar', 'value' => 'lululu'),
                            array('name' => 'le', 'value' => 200)
                        ),
                        'value' => 1,
                        'help' => 'this is for testing',
                        'type' => Histogram::TYPE
                    ),
                    array(
                        'name' => 'test_some_metric',
                        'labels' => array(
                            array('name' => 'foo', 'value' => 'lalal'),
                            array('name' => 'bar', 'value' => 'lululu'),
                            array('name' => 'le', 'value' => 300)
                        ),
                        'value' => 2,
                        'help' => 'this is for testing',
                        'type' => Histogram::TYPE
                    ),
                    array(
                        'name' => 'test_some_metric_sum',
                        'labels' => array(
                            array('name' => 'foo', 'value' => 'lalal'),
                            array('name' => 'bar', 'value' => 'lululu'),
                        ),
                        'value' => 368,
                        'help' => 'this is for testing',
                        'type' => Histogram::TYPE
                    ),
                    array(
                        'name' => 'test_some_metric_count',
                        'labels' => array(
                            array('name' => 'foo', 'value' => 'lalal'),
                            array('name' => 'bar', 'value' => 'lululu'),
                        ),
                        'value' => 2,
                        'help' => 'this is for testing',
                        'type' => Histogram::TYPE
                    ),
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
                        'name' => 'test_some_metric',
                        'labels' => array(
                            array('name' => 'le', 'value' => 100)
                        ),
                        'value' => 0,
                        'help' => 'this is for testing',
                        'type' => Histogram::TYPE
                    ),
                    array(
                        'name' => 'test_some_metric',
                        'labels' => array(
                            array('name' => 'le', 'value' => 200)
                        ),
                        'value' => 0,
                        'help' => 'this is for testing',
                        'type' => Histogram::TYPE
                    ),
                    array(
                        'name' => 'test_some_metric',
                        'labels' => array(
                            array('name' => 'le', 'value' => 300)
                        ),
                        'value' => 1,
                        'help' => 'this is for testing',
                        'type' => Histogram::TYPE
                    ),
                    array(
                        'name' => 'test_some_metric_sum',
                        'labels' => array(),
                        'value' => 245,
                        'help' => 'this is for testing',
                        'type' => Histogram::TYPE
                    ),
                    array(
                        'name' => 'test_some_metric_count',
                        'labels' => array(),
                        'value' => 1,
                        'help' => 'this is for testing',
                        'type' => Histogram::TYPE
                    ),
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
