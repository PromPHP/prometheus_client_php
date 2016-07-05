<?php

namespace Prometheus;


use Prometheus\Storage\Adapter;

class Histogram extends Metric
{
    const TYPE = 'histogram';

    private $buckets;

    /**
     * @param Adapter $adapter
     * @param string $namespace
     * @param string $name
     * @param string $help
     * @param array $labels
     * @param array $buckets
     */
    public function __construct(Adapter $adapter, $namespace, $name, $help, $labels = array(), $buckets = array())
    {
        parent::__construct($adapter, $namespace, $name, $help, $labels);

        if (0 == count($buckets)) {
            throw new \InvalidArgumentException("Histogram must have at least one bucket.");
        }
        for ($i = 0; $i < count($buckets) - 1; $i++) {
            if ($buckets[$i] >= $buckets[$i + 1]) {
                throw new \InvalidArgumentException(
                    "Histogram buckets must be in increasing order: " .
                    $buckets[$i] . " >= " . $buckets[$i + 1]
                );
            }
        }
        foreach ($buckets as $bucket) {
            if ($bucket == 'le') {
                throw new \InvalidArgumentException("Histogram cannot have a label named 'le'.");
            }
        }
        $this->buckets = $buckets;
    }

    /**
     * @param double $value e.g. 123
     * @param array $labels e.g. ['status', 'opcode']
     */
    public function observe($value, $labels = array())
    {
        $this->assertLabelsAreDefinedCorrectly($labels);

        foreach ($this->buckets as $bucket) {
            if ($value <= $bucket) {
                $this->storageAdapter->storeSample(
                    Adapter::COMMAND_INCREMENT_INTEGER,
                    $this,
                    new Sample(
                        array(
                            'name' => $this->getName() . '_bucket',
                            'labelNames' => array_merge($this->getLabelNames(), array('le')),
                            'labelValues' => array_merge($labels, array($bucket)),
                            'value' => 1
                        )
                    )
                );
            } else {
                $this->storageAdapter->storeSample(
                    Adapter::COMMAND_INCREMENT_INTEGER,
                    $this,
                    new Sample(
                        array(
                            'name' => $this->getName() . '_bucket',
                            'labelNames' => array_merge($this->getLabelNames(), array('le')),
                            'labelValues' => array_merge($labels, array($bucket)),
                            'value' => 0
                        )
                    )
                );
            }
        }
        $this->storageAdapter->storeSample(
            Adapter::COMMAND_INCREMENT_INTEGER,
            $this,
            new Sample(
                array(
                    'name' => $this->getName() . '_bucket',
                    'labelNames' => array_merge($this->getLabelNames(), array('le')),
                    'labelValues' => array_merge($labels, array('+Inf')),
                    'value' => 1
                )
            )
        );
        $this->storageAdapter->storeSample(
            Adapter::COMMAND_INCREMENT_INTEGER,
            $this,
            new Sample(
                array(
                    'name' => $this->getName() . '_count',
                    'labelNames' => $this->getLabelNames(),
                    'labelValues' => $labels,
                    'value' => 1
                )
            )
        );
        $this->storageAdapter->storeSample(
            Adapter::COMMAND_INCREMENT_FLOAT,
            $this,
            new Sample(
                array(
                    'name' => $this->getName() . '_sum',
                    'labelNames' => $this->getLabelNames(),
                    'labelValues' => $labels,
                    'value' => $value
                )
            )
        );
    }

    /**
     * @return string
     */
    public function getType()
    {
        return self::TYPE;
    }
}
