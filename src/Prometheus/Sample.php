<?php

declare(strict_types=1);

namespace Prometheus;

class Sample
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var array
     */
    private $labelNames;

    /**
     * @var array
     */
    private $labelValues;

    /**
     * @var int|double
     */
    private $value;

    /**
     * Sample constructor.
     * @param array $data
     */
    public function __construct(array $data)
    {
        $this->name = $data['name'];
        $this->labelNames = $data['labelNames'];
        $this->labelValues = $data['labelValues'];
        $this->value = $data['value'];
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array
     */
    public function getLabelNames(): array
    {
        return (array)$this->labelNames;
    }

    /**
     * @return array
     */
    public function getLabelValues(): array
    {
        return (array)$this->labelValues;
    }

    /**
     * @return int|double
     */
    public function getValue(): string
    {
        return (string) $this->value;
    }

    /**
     * @return bool
     */
    public function hasLabelNames(): bool
    {
        return !empty($this->labelNames);
    }
}
