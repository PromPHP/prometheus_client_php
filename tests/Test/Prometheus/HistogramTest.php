<?php


namespace Test\Prometheus;

use PHPUnit_Framework_TestCase;
use Prometheus\Histogram;
use Prometheus\MetricFamilySamples;
use Prometheus\Storage\InMemory;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class HistogramTest extends PHPUnit_Framework_TestCase
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
    public function itShouldObserveWithLabels()
    {
        $gauge = new Histogram(
            $this->storage,
            'test',
            'some_metric',
            'this is for testing',
            array('foo', 'bar'),
            array(100, 200, 300)
        );
        $gauge->observe(123, array('lalal', 'lululu'));
        $gauge->observe(245, array('lalal', 'lululu'));
        $this->assertThat(
            $this->storage->collect(),
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
        $gauge = new Histogram(
            $this->storage,
            'test',
            'some_metric',
            'this is for testing',
            array(),
            array(100, 200, 300)
        );
        $gauge->observe(245);
        $this->assertThat(
            $this->storage->collect(),
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
        $gauge = new Histogram(
            $this->storage,
            'test',
            'some_metric',
            'this is for testing',
            array(),
            array(0.1, 0.2, 0.3)
        );
        $gauge->observe(0.11);
        $gauge->observe(0.3);
        $this->assertThat(
            $this->storage->collect(),
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

        $gauge = new Histogram(
            $this->storage,
            'test',
            'some_metric',
            'this is for testing',
            array()

        );
        $gauge->observe(0.11);
        $gauge->observe(0.03);
        $this->assertThat(
            $this->storage->collect(),
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
     */
    public function itShouldThrowAnExceptionWhenTheBucketSizesAreNotIncreasing()
    {
        new Histogram($this->storage, 'test', 'some_metric', 'this is for testing', array(), array(1, 1));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function itShouldThrowAnExceptionWhenThereIsLessThanOneBucket()
    {
        new Histogram($this->storage, 'test', 'some_metric', 'this is for testing', array(), array());
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function itShouldThrowAnExceptionWhenThereIsALabelNamedLe()
    {
        new Histogram($this->storage, 'test', 'some_metric', 'this is for testing', array('le'), array());
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function itShouldRejectInvalidMetricsNames()
    {
        new Histogram($this->storage, 'test', 'some invalid metric', 'help', array(), array(1));
    }

    /**
     * @test
     * @expectedException \InvalidArgumentException
     */
    public function itShouldRejectInvalidLabelNames()
    {
        new Histogram($this->storage, 'test', 'some_metric', 'help', array('invalid label'), array(1));
    }
}
