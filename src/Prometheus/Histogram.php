<?php

namespace Prometheus;


use Prometheus\Storage\Adapter;

class Histogram extends Collector
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
    public function __construct(Adapter $adapter, $namespace, $name, $help, $labels = array(), $buckets = null)
    {
        parent::__construct($adapter, $namespace, $name, $help, $labels);

        if (null === $buckets) {
            $buckets = self::getDefaultBuckets();
        }

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
        foreach ($labels as $label) {
            if ($label === 'le') {
                throw new \InvalidArgumentException("Histogram cannot have a label named 'le'.");
            }
        }
        $this->buckets = $buckets;
    }

    /**
     * List of default buckets suitable for typical web application latency metrics
     * @return array
     */
    public static function getDefaultBuckets()
    {
        return array(
            0.005, 0.01, 0.025, 0.05, 0.075, 0.1, 0.25, 0.5, 0.75, 1.0, 2.5, 5.0, 7.5, 10.0
        );
    }

    /**
     * @param double $value e.g. 123
     * @param array $labels e.g. ['status', 'opcode']
     */
    public function observe($value, $labels = array())
    {
        $this->assertLabelsAreDefinedCorrectly($labels);

        $this->storageAdapter->updateHistogram(
            array(
                'value' => $value,
                'name' => $this->getName(),
                'help' => $this->getHelp(),
                'type' => $this->getType(),
                'labelNames' => $this->getLabelNames(),
                'labelValues' => $labels,
                'buckets' => $this->buckets,
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
