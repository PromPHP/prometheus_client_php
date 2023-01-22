<?php

namespace Prometheus\Storage\RedisTxn;

use InvalidArgumentException;
use Prometheus\Storage\Adapter;

/**
 * Fluent-builder for the {@see \Prometheus\Storage\RedisTxn\Metadata} structure.
 */
class MetadataBuilder
{
    /**
     * @var string|null
     */
    private $name;

    /**
     * @var string|null
     */
    private $type;

    /**
     * @var string|null
     */
    private $help;

    /**
     * @var string[]|null
     */
    private $labelNames;

    /**
     * @var mixed[]|null
     */
    private $labelValues;

    /**
     * @var int|null
     */
    private $maxAgeSeconds;

    /**
     * @var float[]|null
     */
    private $quantiles;

    /**
     * @var int|null
     */
    private $command;

    /**
     * @param array $metadata
     * @return MetadataBuilder
     */
    public static function fromArray(array $metadata): MetadataBuilder
    {
        return Metadata::newBuilder()
            ->withName($metadata['name'])
            ->withType($metadata['type'])
            ->withHelp($metadata['help'] ?? null)
            ->withLabelNames($metadata['labelNames'] ?? null)
            ->withLabelValues($metadata['labelValues'] ?? null)
            ->withMaxAgeSeconds($metadata['maxAgeSeconds'] ?? null)
            ->withQuantiles($metadata['quantiles'] ?? null)
            ->withCommand($metadata['command'] ?? null);
    }

    /**
     * @param string $name
     * @return MetadataBuilder
     */
    public function withName(string $name): MetadataBuilder
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param string $type
     * @return MetadataBuilder
     */
    public function withType(string $type): MetadataBuilder
    {
        $this->type = $type;
        return $this;
    }

    /**
     * @param string|null $help
     * @return MetadataBuilder
     */
    public function withHelp(?string $help): MetadataBuilder
    {
        $this->help = $help;
        return $this;
    }

    /**
     * @param string[]|null $labelNames
     * @return MetadataBuilder
     */
    public function withLabelNames(?array $labelNames): MetadataBuilder
    {
        $this->labelNames = $labelNames;
        return $this;
    }

    /**
     * @param string|array|null $labelValues
     * @return MetadataBuilder
     */
    public function withLabelValues($labelValues): MetadataBuilder
    {
        if (($labelValues === null) || is_array($labelValues)) {
            $this->labelValues = $labelValues;
        } else {
            // See Metadata::getLabelNamesEncoded() for the inverse operation on this data.
            $this->labelValues = json_decode(base64_decode($labelValues));
        }
        return $this;
    }

    /**
     * @param int|null $maxAgeSeconds
     * @return MetadataBuilder
     */
    public function withMaxAgeSeconds(?int $maxAgeSeconds): MetadataBuilder
    {
        $this->maxAgeSeconds = $maxAgeSeconds;
        return $this;
    }

    /**
     * @param float[]|null $quantiles
     * @return MetadataBuilder
     */
    public function withQuantiles(?array $quantiles): MetadataBuilder
    {
        $this->quantiles = $quantiles;
        return $this;
    }

    /**
     * @param int|null $command
     * @return MetadataBuilder
     */
    public function withCommand(?int $command): MetadataBuilder
    {
        $this->command = $command;
        return $this;
    }

    /**
     * @return Metadata
     */
    public function build(): Metadata
    {
        $this->validate();
        return new Metadata(
            $this->name,
            $this->type ?? '',
            $this->help ?? '',
            $this->labelNames ?? [],
            $this->labelValues ?? [],
            $this->maxAgeSeconds ?? 0,
            $this->quantiles ?? [],
            $this->command ?? Adapter::COMMAND_INCREMENT_FLOAT
        );
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if ($this->name === null) {
            throw new InvalidArgumentException('Metadata name field is required');
        }
    }
}
