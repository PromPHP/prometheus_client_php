<?php


namespace Test\Prometheus;

use PHPUnit_Framework_TestCase;
use Prometheus\Gauge;
use Prometheus\Sample;
use Prometheus\Storage\InMemory;

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
        $gauge = new Gauge(new InMemory(), 'test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->set(123, array('lalal', 'lululu'));
        $this->assertThat(
            $gauge->getSamples(),
            $this->equalTo(
                array(
                    new Sample(
                        array(
                            'name' => 'test_some_metric',
                            'labelNames' => array('foo', 'bar'),
                            'labelValues' => array('lalal', 'lululu'),
                            'value' => 123,
                        )
                    )
                )
            )
        );
        $this->assertThat($gauge->getHelp(), $this->equalTo('this is for testing'));
        $this->assertThat($gauge->getType(), $this->equalTo(Gauge::TYPE));
    }

    /**
     * @test
     */
    public function itShouldAllowSetWithoutLabelWhenNoLabelsAreDefined()
    {
        $gauge = new Gauge(new InMemory(), 'test', 'some_metric', 'this is for testing');
        $gauge->set(123);
        $this->assertThat(
            $gauge->getSamples(),
            $this->equalTo(
                array(
                    new Sample(
                        array(
                            'name' => 'test_some_metric',
                            'labelNames' => array(),
                            'labelValues' => array(),
                            'value' => 123,
                        )
                    )
                )
            )
        );
        $this->assertThat($gauge->getHelp(), $this->equalTo('this is for testing'));
        $this->assertThat($gauge->getType(), $this->equalTo(Gauge::TYPE));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function itShouldRejectInvalidMetricsNames()
    {
        new Gauge(new InMemory(), 'test', 'some metric invalid metric', 'help');
    }
}
