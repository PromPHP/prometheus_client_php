<?php


namespace Test\Prometheus;

use PHPUnit_Framework_TestCase;
use Prometheus\Counter;
use Prometheus\MetricFamilySamples;
use Prometheus\Sample;
use Prometheus\Storage\Adapter;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
abstract class AbstractCounterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @var Adapter
     */
    public $adapter;

    public function setUp()
    {
        $this->configureAdapter();
    }

    /**
     * @test
     */
    public function itShouldIncreaseWithLabels()
    {
        $gauge = new Counter($this->adapter, 'test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->inc(array('lalal', 'lululu'));
        $gauge->inc(array('lalal', 'lululu'));
        $gauge->inc(array('lalal', 'lululu'));
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'type' => Counter::TYPE,
                            'help' => 'this is for testing',
                            'name' => 'test_some_metric',
                            'labelNames' => array('foo', 'bar'),
                            'samples' => array(
                                array(
                                    'labelValues' => array('lalal', 'lululu'),
                                    'value' => 3,
                                    'name' => 'test_some_metric',
                                    'labelNames' => array()
                                ),
                            )
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
        $gauge = new Counter($this->adapter, 'test', 'some_metric', 'this is for testing');
        $gauge->inc();
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'type' => Counter::TYPE,
                            'help' => 'this is for testing',
                            'name' => 'test_some_metric',
                            'labelNames' => array(),
                            'samples' => array(
                                array(
                                    'labelValues' => array(),
                                    'value' => 1,
                                    'name' => 'test_some_metric',
                                    'labelNames' => array()
                                ),
                            )
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
        $gauge = new Counter($this->adapter, 'test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->inc(array('lalal', 'lululu'));
        $gauge->incBy(123, array('lalal', 'lululu'));
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'type' => Counter::TYPE,
                            'help' => 'this is for testing',
                            'name' => 'test_some_metric',
                            'labelNames' => array('foo', 'bar'),
                            'samples' => array(
                                array(
                                    'labelValues' => array('lalal', 'lululu'),
                                    'value' => 124,
                                    'name' => 'test_some_metric',
                                    'labelNames' => array()
                                ),
                            )
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
        new Counter($this->adapter, 'test', 'some metric invalid metric', 'help');
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function itShouldRejectInvalidLabelNames()
    {
        new Counter($this->adapter, 'test', 'some_metric', 'help', array('invalid label'));
    }

    /**
     * @test
     * @dataProvider labelValuesDataProvider
     *
     * @param mixed $value The label value
     */
    public function isShouldAcceptAnySequenceOfBasicLatinCharactersForLabelValues($value)
    {
        $label = 'foo';
        $histogram = new Counter($this->adapter, 'test', 'some_metric', 'help', array($label));
        $histogram->inc(array($value));

        $metrics = $this->adapter->collect();
        self::assertInternalType('array', $metrics);
        self::assertCount(1, $metrics);
        self::assertContainsOnlyInstancesOf(MetricFamilySamples::class, $metrics);

        $metric = reset($metrics);
        $samples = $metric->getSamples();
        self::assertContainsOnlyInstancesOf(Sample::class, $samples);

        foreach ($samples as $sample) {
            $labels = array_combine(
                array_merge($metric->getLabelNames(), $sample->getLabelNames()),
                $sample->getLabelValues()
            );
            self::assertEquals($value, $labels[$label]);
        }
    }

    /**
     * @see isShouldAcceptArbitraryLabelValues
     * @return array
     */
    public function labelValuesDataProvider()
    {
        $cases = [];
        // Basic Latin
        // See https://en.wikipedia.org/wiki/List_of_Unicode_characters#Basic_Latin
        for ($i = 32; $i <= 121; $i++) {
            $cases['ASCII code ' . $i] = array(chr($i));
        }
        return $cases;
    }

    public abstract function configureAdapter();
}
