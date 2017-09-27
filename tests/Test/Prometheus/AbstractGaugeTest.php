<?php


namespace Test\Prometheus;

use PHPUnit_Framework_TestCase;
use Prometheus\Gauge;
use Prometheus\MetricFamilySamples;
use Prometheus\Sample;
use Prometheus\Storage\Adapter;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
abstract class AbstractGaugeTest extends PHPUnit_Framework_TestCase
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
    public function itShouldAllowSetWithLabels()
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->set(123, array('lalal', 'lululu'));
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => array('foo', 'bar'),
                            'samples' => array(
                                array(
                                    'name' => 'test_some_metric',
                                    'labelNames' => array(),
                                    'labelValues' => array('lalal', 'lululu'),
                                    'value' => 123,
                                )
                            )
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
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing');
        $gauge->set(123);
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => array(),
                            'samples' => array(
                                array(
                                    'name' => 'test_some_metric',
                                    'labelNames' => array(),
                                    'labelValues' => array(),
                                    'value' => 123,
                                )
                            )
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
    public function itShouldAllowSetWithAFloatValue()
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing');
        $gauge->set(123.5);
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => array(),
                            'samples' => array(
                                array(
                                    'name' => 'test_some_metric',
                                    'labelNames' => array(),
                                    'labelValues' => array(),
                                    'value' => 123.5,
                                )
                            )
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
    public function itShouldIncrementAValue()
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->inc(array('lalal', 'lululu'));
        $gauge->incBy(123, array('lalal', 'lululu'));
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => array('foo', 'bar'),
                            'samples' => array(
                                array(
                                    'name' => 'test_some_metric',
                                    'labelNames' => array(),
                                    'labelValues' => array('lalal', 'lululu'),
                                    'value' => 124,
                                )
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
    public function itShouldIncrementWithFloatValue()
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->inc(array('lalal', 'lululu'));
        $gauge->incBy(123.5, array('lalal', 'lululu'));
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => array('foo', 'bar'),
                            'samples' => array(
                                array(
                                    'name' => 'test_some_metric',
                                    'labelNames' => array(),
                                    'labelValues' => array('lalal', 'lululu'),
                                    'value' => 124.5,
                                )
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
    public function itShouldDecrementAValue()
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->dec(array('lalal', 'lululu'));
        $gauge->decBy(123, array('lalal', 'lululu'));
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => array('foo', 'bar'),
                            'samples' => array(
                                array(
                                    'name' => 'test_some_metric',
                                    'labelNames' => array(),
                                    'labelValues' => array('lalal', 'lululu'),
                                    'value' => -124,
                                )
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
    public function itShouldDecrementWithFloatValue()
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->dec(array('lalal', 'lululu'));
        $gauge->decBy(123, array('lalal', 'lululu'));
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => array('foo', 'bar'),
                            'samples' => array(
                                array(
                                    'name' => 'test_some_metric',
                                    'labelNames' => array(),
                                    'labelValues' => array('lalal', 'lululu'),
                                    'value' => -124,
                                )
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
    public function itShouldOverwriteWhenSettingTwice()
    {
        $gauge = new Gauge($this->adapter, 'test', 'some_metric', 'this is for testing', array('foo', 'bar'));
        $gauge->set(123, array('lalal', 'lululu'));
        $gauge->set(321, array('lalal', 'lululu'));
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Gauge::TYPE,
                            'labelNames' => array('foo', 'bar'),
                            'samples' => array(
                                array(
                                    'name' => 'test_some_metric',
                                    'labelNames' => array(),
                                    'labelValues' => array('lalal', 'lululu'),
                                    'value' => 321,
                                )
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
        new Gauge($this->adapter, 'test', 'some metric invalid metric', 'help');
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function itShouldRejectInvalidLabelNames()
    {
        new Gauge($this->adapter, 'test', 'some_metric', 'help', array('invalid label'));
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
        $histogram = new Gauge($this->adapter, 'test', 'some_metric', 'help', array($label));
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
