<?php

declare(strict_types=1);

namespace Prometheus;

use InvalidArgumentException;
use Prometheus\Storage\Adapter;

abstract class Collector
{
    const RE_METRIC_LABEL_NAME = '/^[a-zA-Z_:][a-zA-Z0-9_:]*$/';

    /**
     * @var Adapter
     */
    protected $storageAdapter;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $help;

    /**
     * @var string[]
     */
    protected $labels;

    /**
     * @param Adapter $storageAdapter
     * @param string  $namespace
     * @param string  $name
     * @param string  $help
     * @param string[]   $labels
     */
    public function __construct(Adapter $storageAdapter, string $namespace, string $name, string $help, array $labels = [])
    {
        $this->storageAdapter = $storageAdapter;
        $metricName = ($namespace !== '' ? $namespace . '_' : '') . $name;
        if (preg_match(self::RE_METRIC_LABEL_NAME, $metricName) !== 1) {
            throw new InvalidArgumentException("Invalid metric name: '" . $metricName . "'");
        }
        $this->name = $metricName;
        $this->help = $help;
        foreach ($labels as $label) {
            if (preg_match(self::RE_METRIC_LABEL_NAME, $label) !== 1) {
                throw new InvalidArgumentException("Invalid label name: '" . $label . "'");
            }
        }
        $this->labels = $labels;
    }

    /**
     * @return string
     */
    abstract public function getType(): string;

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string[]
     */
    public function getLabelNames(): array
    {
        return $this->labels;
    }

    /**
     * @return string
     */
    public function getHelp(): string
    {
        return $this->help;
    }

    /**
     * @return string
     */
    public function getKey(): string
    {
        return sha1($this->getName() . serialize($this->getLabelNames()));
    }

    /**
     * @param string[] $labels
     */
    protected function assertLabelsAreDefinedCorrectly(array $labels): void
    {
        if (count($labels) !== count($this->labels)) {
            throw new InvalidArgumentException(sprintf('Labels are not defined correctly: %s', print_r($labels, true)));
        }
    }
}
