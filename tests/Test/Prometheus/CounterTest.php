<?php


namespace Test\Prometheus;
use PHPUnit_Framework_TestCase;
use Prometheus\Counter;
use Prometheus\Gauge;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class CounterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @test
     */
    public function itShouldIncreaseWithLabels()
    {
        $gauge = new Counter('test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->increase(array('foo' => 'lalal', 'bar' => 'lululu'));
        $gauge->increase(array('foo' => 'lalal', 'bar' => 'lululu'));
        $gauge->increase(array('foo' => 'lalal', 'bar' => 'lululu'));
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
                        'value' => 3,
                        'help' => 'this is for testing',
                        'type' => Counter::TYPE
                    )
                )
            )
        );
    }

    /**
     * @test
     */
    public function itShouldIncreaseWithoutLabelWhenNoLabelsAreDefined()
    {
        $gauge = new Counter('test', 'some_metric', 'this is for testing');
        $gauge->increase();
        $this->assertThat(
            $gauge->getSamples(),
            $this->equalTo(
                array(
                    array(
                        'name' => 'test_some_metric',
                        'labels' => array(),
                        'value' => 1,
                        'help' => 'this is for testing',
                        'type' => Counter::TYPE
                    )
                )
            )
        );
    }

    /**
     * @test
     */
    public function itShouldIncreaseTheCounterByAnArbitraryInteger()
    {
        $gauge = new Counter('test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->increase(array('foo' => 'lalal', 'bar' => 'lululu'));
        $gauge->increaseBy(123, array('foo' => 'lalal', 'bar' => 'lululu'));
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
                        'value' => 124,
                        'help' => 'this is for testing',
                        'type' => Counter::TYPE
                    )
                )
            )
        );
    }
}
