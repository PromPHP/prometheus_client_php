<?php


namespace Test\Prometheus;
use PHPUnit_Framework_TestCase;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class cc_Status_Prometheus_GaugeTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function itShouldAllowSetWithLabels()
    {
        $gauge = new Gauge('test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->set(123, array('foo' => 'lalal', 'bar' => 'lululu'));
        $this->assertThat(
            $gauge->getMetricLabelVariations(),
            $this->equalTo(
                array(
                    array(
                        'name' => 'test_some_metric',
                        'labels' => array(
                            array('name' => 'foo', 'value' => 'lalal'),
                            array('name' => 'bar', 'value' => 'lululu')
                        ),
                        'value' => 123
                    )
                )
            )
        );
    }

    /**
     * @test
     */
    public function itShouldAllowSetWithoutLabelWhenNotLabelsAreDefined()
    {
        $gauge = new Gauge('test', 'some_metric', 'this is for testing');
        $gauge->set(123);
        $this->assertThat(
            $gauge->getMetricLabelVariations(),
            $this->equalTo(
                array(
                    array(
                        'name' => 'test_some_metric',
                        'labels' => array(),
                        'value' => 123
                    )
                )
            )
        );
    }
}
