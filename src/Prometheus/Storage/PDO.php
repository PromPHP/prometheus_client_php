<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use Prometheus\Counter;
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
     * @param array{0: int, 1: int} $precision
     *  Precision of the 'value' DECIMAL column in the database table (default: 16, 2).
     */
    public function __construct(\PDO $database, string $prefix = 'prometheus_', array $precision = [16, 2])
    {
        $this->database = $database;
        $this->prefix = $prefix;
        $this->precision = $precision;

        $this->createTables();
    }

    /**
     * @return MetricFamilySamples[]
     */
    public function collect(bool $sortMetrics = true): array
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
    }

    /**
     * @return MetricFamilySamples[]
     */
    protected function collectHistograms(): array
    {
        return [];
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

            $values_query = $this->database->prepare("SELECT name, labels_hash, labels, value, time FROM `{$this->prefix}_summaries` WHERE name = :name AND type = :type");
            $values_query->execute([
                ':name' => $data['name'],
                ':type' => Summary::TYPE,
            ]);

            $values = [];
            while ($value_row = $values_query->fetch(\PDO::FETCH_ASSOC)) {
                $values[$value_row['labels_hash']][] = $value_row;
            }

            foreach ($values as $labels_hash => $samples) {
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
        $result = [];

        $meta_query = $this->database->prepare("SELECT name, metadata FROM `{$this->prefix}_metadata` WHERE type = :type");
        $meta_query->execute([':type' => Counter::TYPE]);

        while ($row = $meta_query->fetch(\PDO::FETCH_ASSOC)) {
            $data = json_decode($row['metadata'], true);
            $data['samples'] = [];

            $values_query = $this->database->prepare("SELECT name, labels, value FROM `{$this->prefix}_values` WHERE name = :name AND type = :type");
            $values_query->execute([
                ':name' => $data['name'],
                ':type' => Counter::TYPE,
            ]);
            while ($value_row = $values_query->fetch(\PDO::FETCH_ASSOC)) {
                $data['samples'][] = [
                    'name' => $value_row['name'],
                    'labelNames' => [],
                    'labelValues' => json_decode($value_row['labels'], true),
                    'value' => $value_row['value'],
                ];
            }

            $result[] = new MetricFamilySamples($data);
        }

        return $result;
    }

    /**
     * @return MetricFamilySamples[]
     */
    protected function collectGauges(): array
    {
        return [];
    }

    /**
     * @param mixed[] $data
     * @return void
     */
    public function updateHistogram(array $data): void
    {
        // TODO.
    }

    /**
     * @param mixed[] $data
     * @return void
     */
    public function updateSummary(array $data): void
    {
        // TODO do we update metadata at all? If metadata changes then the old labels might not be correct any more?
        $metadata_sql = <<<SQL
INSERT INTO  `{$this->prefix}_metadata`
  VALUES(:name, :type, :metadata)
  ON CONFLICT(name, type) DO UPDATE SET
    metadata=excluded.metadata;
SQL;

        $statement = $this->database->prepare($metadata_sql);
        $statement->execute([
            ':name' => $data['name'],
            ':type' => Summary::TYPE,
            ':metadata' => $this->encodeMetadata($data),
        ]);

            $values_sql = <<<SQL
INSERT INTO  `{$this->prefix}_summaries`(`name`, `type`, `labels_hash`, `labels`, `value`, `time`)
  VALUES(:name,:type,:hash,:labels,:value,:time)
SQL;

        $statement = $this->database->prepare($values_sql);
        $label_values = $this->encodeLabelValues($data);
        $statement->execute([
            ':name' => $data['name'],
            ':type' => Summary::TYPE,
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
        // TODO.
    }

    /**
     * @param mixed[] $data
     */
    public function updateCounter(array $data): void
    {
        // TODO do we update metadata at all? If metadata changes then the old labels might not be correct any more?
        $metadata_sql = <<<SQL
INSERT INTO  `{$this->prefix}_metadata`
  VALUES(:name, :type, :metadata)
  ON CONFLICT(name, type) DO UPDATE SET
    metadata=excluded.metadata;
SQL;

        $statement = $this->database->prepare($metadata_sql);
        $statement->execute([
            ':name' => $data['name'],
            ':type' => Counter::TYPE,
            ':metadata' => $this->encodeMetadata($data),
        ]);

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

        $statement = $this->database->prepare($values_sql);
        $label_values = $this->encodeLabelValues($data);
        $statement->execute([
            ':name' => $data['name'],
            ':type' => Counter::TYPE,
            ':hash' => hash('sha256', $label_values),
            ':labels' => $label_values,
            ':value' => $data['value'],
        ]);
    }

    protected function createTables(): void
    {
        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->prefix}_metadata` (
    `name` varchar(255) NOT NULL,
    `type` varchar(9) NOT NULL,
    `metadata` text NOT NULL,
    PRIMARY KEY (`name`, `type`)
)
SQL;
        $this->database->query($sql);

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->prefix}_values` (
    `name` varchar(255) NOT NULL,
    `type` varchar(9) NOT NULL,
    `labels_hash` varchar(32) NOT NULL,
    `labels` TEXT NOT NULL,
    `value` DECIMAL({$this->precision[0]},{$this->precision[1]}) DEFAULT 0.0,
    PRIMARY KEY (`name`, `type`, `labels_hash`)
)
SQL;
        $this->database->query($sql);

        $sql = <<<SQL
CREATE TABLE IF NOT EXISTS `{$this->prefix}_summaries` (
    `name` varchar(255) NOT NULL,
    `type` varchar(9) NOT NULL,
    `labels_hash` varchar(32) NOT NULL,
    `labels` TEXT NOT NULL,
    `value` DECIMAL({$this->precision[0]},{$this->precision[1]}) DEFAULT 0.0,
    `time` timestamp NOT NULL
); 
CREATE INDEX `name_type` ON `{$this->prefix}_summaries`(`name`, `type`);
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
