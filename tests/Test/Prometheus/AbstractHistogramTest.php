<?php


namespace Test\Prometheus;

use PHPUnit_Framework_TestCase;
use Prometheus\Histogram;
use Prometheus\MetricFamilySamples;
use Prometheus\Sample;
use Prometheus\Storage\Adapter;


/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
abstract class AbstractHistogramTest extends PHPUnit_Framework_TestCase
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
    public function itShouldObserveWithLabels()
    {
        $histogram = new Histogram(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            array('foo', 'bar'),
            array(100, 200, 300)
        );
        $histogram->observe(123, array('lalal', 'lululu'));
        $histogram->observe(245, array('lalal', 'lululu'));
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Histogram::TYPE,
                            'labelNames' => array('foo', 'bar'),
                            'samples' => array(
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array('lalal', 'lululu', 100),
                                    'value' => 0,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array('lalal', 'lululu', 200),
                                    'value' => 1,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array('lalal', 'lululu', 300),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array('lalal', 'lululu', '+Inf'),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => array(),
                                    'labelValues' => array('lalal', 'lululu'),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => array(),
                                    'labelValues' => array('lalal', 'lululu'),
                                    'value' => 368,
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
    public function itShouldObserveWithoutLabelWhenNoLabelsAreDefined()
    {
        $histogram = new Histogram(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            array(),
            array(100, 200, 300)
        );
        $histogram->observe(245);
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Histogram::TYPE,
                            'labelNames' => array(),
                            'samples' => array(
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
        $histogram = new Histogram(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            array(),
            array(0.1, 0.2, 0.3)
        );
        $histogram->observe(0.11);
        $histogram->observe(0.3);
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Histogram::TYPE,
                            'labelNames' => array(),
                            'samples' => array(
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(0.1),
                                    'value' => 0,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(0.2),
                                    'value' => 1,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(0.3),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array('+Inf'),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => array(),
                                    'labelValues' => array(),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => array(),
                                    'labelValues' => array(),
                                    'value' => 0.41,
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
    public function itShouldProvideDefaultBuckets()
    {
        // .005, .01, .025, .05, .075, .1, .25, .5, .75, 1.0, 2.5, 5.0, 7.5, 10.0

        $histogram = new Histogram(
            $this->adapter,
            'test',
            'some_metric',
            'this is for testing',
            array()

        );
        $histogram->observe(0.11);
        $histogram->observe(0.03);
        $this->assertThat(
            $this->adapter->collect(),
            $this->equalTo(
                array(
                    new MetricFamilySamples(
                        array(
                            'name' => 'test_some_metric',
                            'help' => 'this is for testing',
                            'type' => Histogram::TYPE,
                            'labelNames' => array(),
                            'samples' => array(
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(0.005),
                                    'value' => 0,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(0.01),
                                    'value' => 0,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(0.025),
                                    'value' => 0,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(0.05),
                                    'value' => 1,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(0.075),
                                    'value' => 1,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(0.1),
                                    'value' => 1,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(0.25),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(0.5),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(0.75),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(1.0),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(2.5),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(5),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(7.5),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array(10),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_bucket',
                                    'labelNames' => array('le'),
                                    'labelValues' => array('+Inf'),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_count',
                                    'labelNames' => array(),
                                    'labelValues' => array(),
                                    'value' => 2,
                                ),
                                array(
                                    'name' => 'test_some_metric_sum',
                                    'labelNames' => array(),
                                    'labelValues' => array(),
                                    'value' => 0.14,
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
     * @expectedExceptionMessage Histogram buckets must be in increasing order
     */
    public function itShouldThrowAnExceptionWhenTheBucketSizesAreNotIncreasing()
    {
        new Histogram($this->adapter, 'test', 'some_metric', 'this is for testing', array(), array(1, 1));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Histogram must have at least one bucket
     */
    public function itShouldThrowAnExceptionWhenThereIsLessThanOneBucket()
    {
        new Histogram($this->adapter, 'test', 'some_metric', 'this is for testing', array(), array());
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Histogram cannot have a label named
     */
    public function itShouldThrowAnExceptionWhenThereIsALabelNamedLe()
    {
        new Histogram($this->adapter, 'test', 'some_metric', 'this is for testing', array('le'), array(1));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid metric name
     */
    public function itShouldRejectInvalidMetricsNames()
    {
        new Histogram($this->adapter, 'test', 'some invalid metric', 'help', array(), array(1));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     * @expectedExceptionMessage Invalid label name
     */
    public function itShouldRejectInvalidLabelNames()
    {
        new Histogram($this->adapter, 'test', 'some_metric', 'help', array('invalid label'), array(1));
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
        $histogram = new Histogram($this->adapter, 'test', 'some_metric', 'help', array($label), array(1));
        $histogram->observe(1, array($value));

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
