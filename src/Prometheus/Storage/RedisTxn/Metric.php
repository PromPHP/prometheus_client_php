<?php

namespace Prometheus\Storage\RedisTxn;

/**
 * This structure represents all the data associated with a single, unique metric that this library
 * should present to a Prometheus scraper.
 *
 * This data generally comprises a set of "metadata" related to the definition of the metric and a
 * set of "samples" which are the observed values for the metric over some trailing window of time.
 */
class Metric
{
    /**
     * @var Metadata
     */
    private $metadata;

    /**
     * @var double[]|float[]|int[]
     */
    private $samples;

    /**
     * @return SummaryMetricBuilder
     */
    public static function newSummaryMetricBuilder(): SummaryMetricBuilder
    {
        return new SummaryMetricBuilder();
    }

    /**
     * @return ScalarMetricBuilder
     */
    public static function newScalarMetricBuilder(): ScalarMetricBuilder
    {
        return new ScalarMetricBuilder();
    }

    /**
     * @param Metadata $metadata
     * @param array|int|float $samples
     */
    public function __construct(Metadata $metadata, $samples)
    {
        $this->metadata = $metadata;
        $this->samples = $samples;
    }

    /**
     * @return Metadata
     */
    public function getMetadata(): Metadata
    {
        return $this->metadata;
    }

    /**
     * Represents this data structure as a PHP associative array.
     *
     * This array generally conforms to the expectations of the {@see \Prometheus\MetricFamilySamples} structure.
     *
     * @return array
     */
    public function toArray(): array
    {
        return [
            'name' => $this->metadata->getName(),
            'help' => $this->metadata->getHelp(),
            'type' => $this->metadata->getType(),
            'labelNames' => $this->metadata->getLabelNames(),
            'maxAgeSeconds' => $this->metadata->getMaxAgeSeconds(),
            'quantiles' => $this->metadata->getQuantiles() ?? [],
            'samples' => $this->samples,
        ];
    }
}