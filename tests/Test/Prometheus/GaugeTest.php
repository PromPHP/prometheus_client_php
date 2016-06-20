<?php


namespace Test\Prometheus;
use PHPUnit_Framework_TestCase;
use Prometheus\Gauge;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class GaugeTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function itShouldAllowSetWithLabels()
    {
        $gauge = new Gauge('test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->set(123, array('foo' => 'lalal', 'bar' => 'lululu'));
        $this->assertThat(
            $gauge->getSamples(),
            $this->equalTo(
                array(
                    array(
                        'name' => 'test_some_metric',
                        'labels' => array(
                            array('name' => 'foo', 'value' => 'lalal'),
                            array('name' => 'bar', 'value' => 'lululu')
                        ),
                        'value' => 123,
                        'help' => 'this is for testing',
                        'type' => Gauge::TYPE
                    )
                )
            )
        );
    }

    /**
     * @test
     */
    public function itShouldAllowSetWithoutLabelWhenNoLabelsAreDefined()
    {
        $gauge = new Gauge('test', 'some_metric', 'this is for testing');
        $gauge->set(123);
        $this->assertThat(
            $gauge->getSamples(),
            $this->equalTo(
                array(
                    array(
                        'name' => 'test_some_metric',
                        'labels' => array(),
                        'value' => 123,
                        'help' => 'this is for testing',
                        'type' => Gauge::TYPE
                    )
                )
            )
        );
    }
}
