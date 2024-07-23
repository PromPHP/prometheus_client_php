<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use Prometheus\Counter;
use Prometheus\Gauge;
use Prometheus\Histogram;
use Prometheus\Math;
use Prometheus\MetricFamilySamples;
use Prometheus\Summary;

class PDO implements Adapter
{
    /**
     * @var \PDO
     */
    protected $database;

    /**
     * @var string
     */
    protected $prefix;

    /**
     * @var array{0: int, 1: int}
     */
    protected $precision;

    /**
     * @param \PDO $database
     *  PDO database connection.
     * @param string $prefix
     *  Database table prefix (default: "prometheus_").
     */
    public function __construct(\PDO $database, string $prefix = 'prometheus_')
    {
        if (!in_array($database->getAttribute(\PDO::ATTR_DRIVER_NAME), ['mysql', 'sqlite'], true)) {
            throw new \RuntimeException('Only MySQL and SQLite are supported.');
        }

        $this->database = $database;
        $this->prefix = $prefix;

        $this->createTables();
    }

    /**
     * @return MetricFamilySamples[]
     */
    public function collect(): array
    {
        $metrics = $this->collectHistograms();
        $metrics = array_merge($metrics, $this->collectGauges());
        $metrics = array_merge($metrics, $this->collectCounters());
        return array_merge($metrics, $this->collectSummaries());
    }

    /**
     * @inheritDoc
     */
    public function wipeStorage(): void
    {
        $this->database->query("DELETE FROM `{$this->prefix}_metadata`");
        $this->database->query("DELETE FROM `{$this->prefix}_values`");
        $this->database->query("DELETE FROM `{$this->prefix}_summaries`");
        $this->database->query("DELETE FROM `{$this->prefix}_histograms`");
    }

    public function deleteTables(): void
    {
        $this->database->query("DROP TABLE `{$this->prefix}_metadata`");
        $this->database->query("DROP TABLE `{$this->prefix}_values`");
        $this->database->query("DROP TABLE `{$this->prefix}_summaries`");
        $this->database->query("DROP TABLE `{$this->prefix}_histograms`");
    }

    /**
     * @return MetricFamilySamples[]
     */
    protected function collectHistograms(): array
    {
        $result = [];

        $meta_query = $this->database->prepare("SELECT name, metadata FROM `{$this->prefix}_metadata` WHERE type = :type");
        $meta_query->execute([':type' => Histogram::TYPE]);

        while ($row = $meta_query->fetch(\PDO::FETCH_ASSOC)) {
            $data = json_decode($row['metadata'], true);
            $data['samples'] = [];

            // Add the Inf bucket, so we can compute it later on.
            $data['buckets'][] = '+Inf';

            $values_query = $this->database->prepare("SELECT name, labels_hash, labels, value, bucket FROM `{$this->prefix}_histograms` WHERE name = :name");
            $values_query->execute([':name' => $data['name']]);

            $values = [];
            while ($value_row = $values_query->fetch(\PDO::FETCH_ASSOC)) {
                $values[$value_row['labels_hash']][] = $value_row;
            }

            $histogram_buckets = [];
            foreach ($values as $_hash => $value) {
                foreach ($value as $bucket_value) {
                    $histogram_buckets[$bucket_value['labels']][$bucket_value['bucket']] = $bucket_value['value'];
                }
            }

            // Compute all buckets
            $labels = array_keys($histogram_buckets);
            sort($labels);
            foreach ($labels as $label_values) {
                $acc = 0;
                $decoded_values = json_decode($label_values, true);  /** @phpstan-ignore-line */
                foreach ($data['buckets'] as $bucket) {
                    $bucket = (string)$bucket;
                    if (!isset($histogram_buckets[$label_values][$bucket])) {
                        $data['samples'][] = [
                            'name' => $data['name'] . '_bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($decoded_values, [$bucket]),
                            'value' => $acc,
                        ];
                    } else {
                        $acc += $histogram_buckets[$label_values][$bucket];
                        $data['samples'][] = [
                            'name' => $data['name'] . '_' . 'bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($decoded_values, [$bucket]),
                            'value' => $acc,
                        ];
                    }
                }

                // Add the count
                $data['samples'][] = [
                    'name' => $data['name'] . '_count',
                    'labelNames' => [],
                    'labelValues' => $decoded_values,
                    'value' => $acc,
                ];

                // Add the sum
                $data['samples'][] = [
                    'name' => $data['name'] . '_sum',
                    'labelNames' => [],
                    'labelValues' => $decoded_values,
                    'value' => $histogram_buckets[$label_values]['sum'],
                ];
            }
            $result[] = new MetricFamilySamples($data);
        }

        return $result;
    }

    /**
     * @return MetricFamilySamples[]
     */
    protected function collectSummaries(): array
    {
        $math = new Math();
        $result = [];

        $meta_query = $this->database->prepare("SELECT name, metadata FROM `{$this->prefix}_metadata` WHERE type = :type");
        $meta_query->execute([':type' => Summary::TYPE]);

        while ($row = $meta_query->fetch(\PDO::FETCH_ASSOC)) {
            $data = json_decode($row['metadata'], true);
            $data['samples'] = [];

            $values_query = $this->database->prepare("SELECT name, labels_hash, labels, value, time FROM `{$this->prefix}_summaries` WHERE name = :name");
            $values_query->execute([':name' => $data['name']]);

            $values = [];
            while ($value_row = $values_query->fetch(\PDO::FETCH_ASSOC)) {
                $values[$value_row['labels_hash']][] = $value_row;
            }

            foreach ($values as $_hash => $samples) {
                $decoded_labels = json_decode(reset($samples)['labels'], true);

                // Remove old data
                $samples = array_filter($samples, function (array $value) use ($data): bool {
                    return time() - $value['time'] <= $data['maxAgeSeconds'];
                });
                if (count($samples) === 0) {
                    continue;
                }

                // Compute quantiles
                usort($samples, function (array $value1, array $value2) {
                    if ($value1['value'] === $value2['value']) {
                        return 0;
                    }
                    return ($value1['value'] < $value2['value']) ? -1 : 1;
                });

                foreach ($data['quantiles'] as $quantile) {
                    $data['samples'][] = [
                        'name' => $data['name'],
                        'labelNames' => ['quantile'],
                        'labelValues' => array_merge($decoded_labels, [$quantile]),
                        'value' => $math->quantile(array_column($samples, 'value'), $quantile),
                    ];
                }

                // Add the count
                $data['samples'][] = [
                    'name' => $data['name'] . '_count',
                    'labelNames' => [],
                    'labelValues' => $decoded_labels,
                    'value' => count($samples),
                ];

                // Add the sum
                $data['samples'][] = [
                    'name' => $data['name'] . '_sum',
                    'labelNames' => [],
                    'labelValues' => $decoded_labels,
                    'value' => array_sum(array_column($samples, 'value')),
                ];
            }

            if (count($data['samples']) > 0) {
                $result[] = new MetricFamilySamples($data);
            }
        }

        return $result;
    }

    /**
     * @return MetricFamilySamples[]
     */
    protected function collectCounters(): array
    {
        return $this->collectStandard(Counter::TYPE);
    }

    /**
     * @return MetricFamilySamples[]
     */
    protected function collectStandard(string $type): array
    {
        $result = [];

        $meta_query = $this->database->prepare("SELECT name, metadata FROM `{$this->prefix}_metadata` WHERE type = :type");
        $meta_query->execute([':type' => $type]);

        while ($row = $meta_query->fetch(\PDO::FETCH_ASSOC)) {
            $data = json_decode($row['metadata'], true);
            $data['samples'] = [];

            $values_query = $this->database->prepare("SELECT name, labels, value FROM `{$this->prefix}_values` WHERE name = :name AND type = :type");
            $values_query->execute([
                ':name' => $data['name'],
                ':type' => $type,
            ]);
            while ($value_row = $values_query->fetch(\PDO::FETCH_ASSOC)) {
                $data['samples'][] = [
                    'name' => $value_row['name'],
                    'labelNames' => [],
                    'labelValues' => json_decode($value_row['labels'], true),
                    'value' => $value_row['value'],
                ];
            }

            usort($data['samples'], function ($a, $b): int {
                return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
            });

            $result[] = new MetricFamilySamples($data);
        }

        return $result;
    }

    /**
     * @return MetricFamilySamples[]
     */
    protected function collectGauges(): array
    {
        return $this->collectStandard(Gauge::TYPE);
    }

    /**
     * @param mixed[] $data
     * @return void
     */
    public function updateHistogram(array $data): void
    {
        $this->updateMetadata($data, Histogram::TYPE);

        switch ($this->database->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            case 'sqlite':
                $values_sql = <<<SQL
INSERT INTO  `{$this->prefix}_histograms`(`name`, `labels_hash`, `labels`, `value`, `bucket`)
  VALUES(:name,:hash,:labels,:value,:bucket)
  ON CONFLICT(name, labels_hash, bucket) DO UPDATE SET
    `value` = `value` + excluded.value;
SQL;
                break;

            case 'mysql':
                $values_sql = <<<SQL
INSERT INTO  `{$this->prefix}_histograms`(`name`, `labels_hash`, `labels`, `value`, `bucket`)
  VALUES(:name,:hash,:labels,:value,:bucket)
  ON DUPLICATE KEY UPDATE
    `value` = `value` + VALUES(`value`);
SQL;
                break;

            default:
                throw new \RuntimeException('Unsupported database type');
        }


        $statement = $this->database->prepare($values_sql);
        $label_values = $this->encodeLabelValues($data);
        $statement->execute([
            ':name' => $data['name'],
            ':hash' => hash('sha256', $label_values),
            ':labels' => $label_values,
            ':value' => $data['value'],
            ':bucket' => 'sum',
        ]);

        $bucket_to_increase = '+Inf';
        foreach ($data['buckets'] as $bucket) {
            if ($data['value'] <= $bucket) {
                $bucket_to_increase = $bucket;
                break;
            }
        }

        $statement->execute([
            ':name' => $data['name'],
            ':hash' => hash('sha256', $label_values),
            ':labels' => $label_values,
            ':value' => 1,
            ':bucket' => $bucket_to_increase,
        ]);
    }

    /**
     * @param mixed[] $data
     * @return void
     */
    public function updateSummary(array $data): void
    {
        $this->updateMetadata($data, Summary::TYPE);

        $values_sql = <<<SQL
INSERT INTO  `{$this->prefix}_summaries`(`name`, `labels_hash`, `labels`, `value`, `time`)
  VALUES(:name,:hash,:labels,:value,:time)
SQL;

        $statement = $this->database->prepare($values_sql);
        $label_values = $this->encodeLabelValues($data);
        $statement->execute([
            ':name' => $data['name'],
            ':hash' => hash('sha256', $label_values),
            ':labels' => $label_values,
            ':value' => $data['value'],
            ':time' => time(),
        ]);
    }

    /**
     * @param mixed[] $data
     */
    public function updateGauge(array $data): void
    {
        $this->updateStandard($data, Gauge::TYPE);
    }

    /**
     * @param mixed[] $data
     */
    public function updateCounter(array $data): void
    {
        $this->updateStandard($data, Counter::TYPE);
    }

    /**
     * @param mixed[] $data
     */
    protected function updateMetadata(array $data, string $type): void
    {
        // TODO do we update metadata at all? If metadata changes then the old labels might not be correct any more?
        switch ($this->database->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            case 'sqlite':
                $metadata_sql = <<<SQL
INSERT INTO  `{$this->prefix}_metadata`
  VALUES(:name, :type, :metadata)
  ON CONFLICT(name, type) DO UPDATE SET
    `metadata` = excluded.metadata;
SQL;
                break;

            case 'mysql':
                $metadata_sql = <<<SQL
INSERT INTO  `{$this->prefix}_metadata`
  VALUES(:name, :type, :metadata)
  ON DUPLICATE KEY UPDATE
    `metadata` = VALUES(`metadata`);
SQL;
                break;

            default:
                throw new \RuntimeException('Unsupported database type');
        }
        $statement = $this->database->prepare($metadata_sql);
        $statement->execute([
            ':name' => $data['name'],
            ':type' => $type,
            ':metadata' => $this->encodeMetadata($data),
        ]);
    }

    /**
     * @param mixed[] $data
     */
    protected function updateStandard(array $data, string $type): void
    {
        $this->updateMetadata($data, $type);

        switch ($this->database->getAttribute(\PDO::ATTR_DRIVER_NAME)) {
            case 'sqlite':
                if ($data['command'] === Adapter::COMMAND_SET) {
                    $values_sql = <<<SQL
INSERT INTO  `{$this->prefix}_values`(`name`, `type`, `labels_hash`, `labels`, `value`)
  VALUES(:name,:type,:hash,:labels,:value)
  ON CONFLICT(name, type, labels_hash) DO UPDATE SET
    `value` = excluded.value;
SQL;
                } else {
                    $values_sql = <<<SQL
INSERT INTO  `{$this->prefix}_values`(`name`, `type`, `labels_hash`, `labels`, `value`)
  VALUES(:name,:type,:hash,:labels,:value)
  ON CONFLICT(name, type, labels_hash) DO UPDATE SET
    `value` = `value` + excluded.value;
SQL;
                }
                break;

            case 'mysql':
                if ($data['command'] === Adapter::COMMAND_SET) {
                    $values_sql = <<<SQL
INSERT INTO  `{$this->prefix}_values`(`name`, `type`, `labels_hash`, `labels`, `value`)
  VALUES(:name,:type,:hash,:labels,:value)
  ON DUPLICATE KEY UPDATE
    `value` = VALUES(`value`);
SQL;
                } else {
                    $values_sql = <<<SQL
INSERT INTO  `{$this->prefix}_values`(`name`, `type`, `labels_hash`, `labels`, `value`)
  VALUES(:name,:type,:hash,:labels,:value)
  ON DUPLICATE KEY UPDATE
    `value` = `value` + VALUES(`value`);
SQL;
                }
                break;

            default:
                throw new \RuntimeException('Unsupported database type');
        }

        $statement = $this->database->prepare($values_sql);
        $label_values = $this->encodeLabelValues($data);
        $statement->execute([
            ':name' => $data['name'],
            ':type' => $type,
            ':hash' => hash('sha256', $label_values),
            ':labels' => $label_values,
            ':value' => $data['value'],
        ]);
    }

    protected function createTables(): void
    {
        $driver = $this->database->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->prefix}_metadata` (
    `name` varchar(255) NOT NULL,
    `type` varchar(9) NOT NULL,
    `metadata` text NOT NULL,
    PRIMARY KEY (`name`, `type`)
)
SQL;
        $this->database->query($sql);

        $hash_size = $driver == 'sqlite' ? 32 : 64;
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->prefix}_values` (
    `name` varchar(255) NOT NULL,
    `type` varchar(9) NOT NULL,
    `labels_hash` varchar({$hash_size}) NOT NULL,
    `labels` TEXT NOT NULL,
    `value` double DEFAULT 0.0,
    PRIMARY KEY (`name`, `type`, `labels_hash`)
)
SQL;
        $this->database->query($sql);

        $timestamp_type = $driver == 'sqlite' ? 'timestamp' : 'int';
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->prefix}_summaries` (
    `name` varchar(255) NOT NULL,
    `labels_hash` varchar({$hash_size}) NOT NULL,
    `labels` TEXT NOT NULL,
    `value` double DEFAULT 0.0,
    `time` {$timestamp_type} NOT NULL
SQL;
        switch ($driver) {
            case 'sqlite':
                $sql .= "); CREATE INDEX `name` ON `{$this->prefix}_summaries`(`name`);";
                break;

            case 'mysql':
                $sql .= ", KEY `name` (`name`));";
                break;
        }

        $this->database->query($sql);

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->prefix}_histograms` (
    `name` varchar(255) NOT NULL,
    `labels_hash` varchar({$hash_size}) NOT NULL,
    `labels` TEXT NOT NULL,
    `value` double DEFAULT 0.0,
    `bucket` varchar(255) NOT NULL,
    PRIMARY KEY (`name`, `labels_hash`, `bucket`)
); 
SQL;
        $this->database->query($sql);
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    protected function encodeMetadata(array $data): string
    {
        unset($data['value'], $data['command'], $data['labelValues']);
        $json = json_encode($data);
        if (false === $json) {
            throw new \RuntimeException(json_last_error_msg());
        }
        return $json;
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    protected function encodeLabelValues(array $data): string
    {
        $json = json_encode($data['labelValues']);
        if (false === $json) {
            throw new \RuntimeException(json_last_error_msg());
        }
        return $json;
    }
}
