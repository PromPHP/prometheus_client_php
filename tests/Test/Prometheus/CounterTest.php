<?php


namespace Test\Prometheus;

use PHPUnit_Framework_TestCase;
use Prometheus\Counter;
use Prometheus\Sample;
use Prometheus\Storage\InMemory;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class CounterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var InMemory
     */
    private $storage;

    public function setUp()
    {
        $this->storage = new InMemory();
    }

    /**
     * @test
     */
    public function itShouldIncreaseWithLabels()
    {
        $gauge = new Counter($this->storage, 'test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->inc(array('lalal', 'lululu'));
        $gauge->inc(array('lalal', 'lululu'));
        $gauge->inc(array('lalal', 'lululu'));
        $this->assertThat(
            $this->storage->fetchSamples(),
            $this->equalTo(
                array(
                    new Sample(
                        array(
                            'name' => 'test_some_metric',
                            'labelNames' => array('foo', 'bar'),
                            'labelValues' => array('lalal', 'lululu'),
                            'value' => 3,
                        )
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
        $gauge = new Counter($this->storage, 'test', 'some_metric', 'this is for testing');
        $gauge->inc();
        $this->assertThat(
            $this->storage->fetchSamples(),
            $this->equalTo(
                array(
                    new Sample(
                        array(
                            'name' => 'test_some_metric',
                            'labelNames' => array(),
                            'labelValues' => array(),
                            'value' => 1,
                        )
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
        $gauge = new Counter($this->storage, 'test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->inc(array('lalal', 'lululu'));
        $gauge->incBy(123, array('lalal', 'lululu'));
        $this->assertThat(
            $this->storage->fetchSamples(),
            $this->equalTo(
                array(
                    new Sample(
                        array(
                            'name' => 'test_some_metric',
                            'labelNames' => array('foo', 'bar'),
                            'labelValues' => array('lalal', 'lululu'),
                            'value' => 124,
                        )
                    )
                )
            )
        );
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function itShouldRejectInvalidMetricsNames()
    {
        new Counter($this->storage, 'test', 'some metric invalid metric', 'help');
    }
}
