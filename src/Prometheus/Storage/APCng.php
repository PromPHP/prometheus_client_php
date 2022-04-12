<?php

declare(strict_types=1);

namespace Prometheus\Storage;

use APCUIterator;
use Prometheus\Exception\StorageException;
use Prometheus\Math;
use Prometheus\MetricFamilySamples;
use RuntimeException;

class APCng implements Adapter
{
    /** @var string Default prefix to use for APCu keys. */
    const PROMETHEUS_PREFIX = 'prom';

    /** @var int Number of retries before we give on apcu_cas(). This can prevent an infinite-loop if we fill up APCu. */
    const CAS_LOOP_RETRIES = 2000;

    /** @var int Number of seconds for cache object to live in APCu. When new metrics are created by other threads, this is the maximum delay until they are discovered.
                 Setting this to a value less than 1 will disable the cache, which will negatively impact performance when making multiple collect*() function-calls.
                 If more than a few thousand metrics are being tracked, disabling cache will be faster, due to apcu_store/fetch serialization being slow. */
    private $metainfoCacheTTL = 1;

    /** @var string APCu key where array of all discovered+created metainfo keys is stored */
    private $metainfoCacheKey;

    /** @var string Prefix to use for APCu keys. */
    private $prometheusPrefix;

    /**
     * APCng constructor.
     *
     * @param string $prometheusPrefix Prefix for APCu keys (defaults to {@see PROMETHEUS_PREFIX}).
     *
     * @throws StorageException
     */
    public function __construct(string $prometheusPrefix = self::PROMETHEUS_PREFIX)
    {
        if (!extension_loaded('apcu')) {
            throw new StorageException('APCu extension is not loaded');
        }
        if (!apcu_enabled()) {
            throw new StorageException('APCu is not enabled');
        }

        $this->prometheusPrefix = $prometheusPrefix;
        $this->metainfoCacheKey = implode(':', [ $this->prometheusPrefix, 'metainfocache' ]);
    }

    /**
     * @return MetricFamilySamples[]
     */
    public function collect(): array
    {
        $metrics = $this->collectHistograms();
        $metrics = array_merge($metrics, $this->collectGauges());
        $metrics = array_merge($metrics, $this->collectCounters());
        $metrics = array_merge($metrics, $this->collectSummaries());
        return $metrics;
    }

    /**
     * @param mixed[] $data
     * @throws RuntimeException
     */
    public function updateHistogram(array $data): void
    {
        // Initialize or atomically increment the sum
        // Taken from https://github.com/prometheus/client_golang/blob/66058aac3a83021948e5fb12f1f408ff556b9037/prometheus/value.go#L91
        $sumKey = $this->histogramBucketValueKey($data, 'sum');
        $done = false;
        $loopCatcher = self::CAS_LOOP_RETRIES;
        while (!$done && $loopCatcher-- > 0) {
            $old = apcu_fetch($sumKey);
            if ($old !== false) {
                $done = apcu_cas($sumKey, $old, $this->toBinaryRepresentationAsInteger($this->fromBinaryRepresentationAsInteger($old) + $data['value']));
            } else {
                // If sum does not exist, initialize it, store the metadata for the new histogram
                apcu_add($sumKey, $this->toBinaryRepresentationAsInteger(0));
                apcu_store($this->metaKey($data), json_encode($this->metaData($data)));
                $this->storeLabelKeys($data);
            }
        }
        if ($loopCatcher <= 0) {
            throw new RuntimeException('Caught possible infinite loop in ' . __METHOD__ . '()');
        }

        // Figure out in which bucket the observation belongs
        $bucketToIncrease = '+Inf';
        foreach ($data['buckets'] as $bucket) {
            if ($data['value'] <= $bucket) {
                $bucketToIncrease = $bucket;
                break;
            }
        }

        // Initialize and increment the bucket
        apcu_add($this->histogramBucketValueKey($data, $bucketToIncrease), 0);
        apcu_inc($this->histogramBucketValueKey($data, $bucketToIncrease));
    }

    /**
     * For each second, store an incrementing counter which points to each individual observation, like this:
     *     prom:bla..blabla:value:16781560:observations = 199
     * Then we know that for the 1-second period at unix timestamp 16781560, 199 observations are stored, and they can
     * be retrieved using APC keynames "prom:...:16781560.0" thorough "prom:...:16781560.198"
     * We can deterministically calculate the intervening timestamps by subtracting maxAge, to get a range of seconds
     * when generating a summary, e.g. 16781560 back to 16780960 for a 600sec maxAge. Then collect observation counts
     * for each second, programmatically generate the APC keys for each individual observation, and we're able to avoid
     * performing a full APC key scan, which can block for several seconds if APCu contains a few million keys.
     *
     * @param mixed[] $data
     * @throws RuntimeException
     */
    public function updateSummary(array $data): void
    {
        // store value key; store metadata & labels if new
        $valueKey = $this->valueKey($data);
        $new = apcu_add($valueKey, $this->encodeLabelValues($data['labelValues']));
        if ($new) {
            apcu_add($this->metaKey($data), $this->metaData($data));
            $this->storeLabelKeys($data);
        }
        $sampleKeyPrefix = $valueKey . ':' . time();
        $sampleCountKey = $sampleKeyPrefix . ':observations';

        // Check if sample counter for this timestamp already exists, so we can deterministically store observations+counts, one key per second
        // Atomic increment of the observation counter, or initialize if new
        $done = false;
        $loopCatcher = self::CAS_LOOP_RETRIES;
        while (!$done && $loopCatcher-- > 0) {
            $sampleCount = apcu_fetch($sampleCountKey);
            if ($sampleCount !== false) {
                $done = apcu_cas($sampleCountKey, $sampleCount, $sampleCount + 1);
            } else {
                apcu_add($sampleCountKey, 0, $data['maxAgeSeconds']);
            }
        }
        if ($loopCatcher <= 0) {
            throw new RuntimeException('Caught possible infinite loop in ' . __METHOD__ . '()');
        }

        // We now have a deterministic keyname for this observation; let's save the observed value
        $sampleKey = $sampleKeyPrefix . '.' . $sampleCount;
        apcu_add($sampleKey, $data['value'], $data['maxAgeSeconds']);
    }

    /**
     * @param mixed[] $data
     * @throws RuntimeException
     */
    public function updateGauge(array $data): void
    {
        $valueKey = $this->valueKey($data);
        if ($data['command'] === Adapter::COMMAND_SET) {
            apcu_store($valueKey, $this->toBinaryRepresentationAsInteger($data['value']));
            apcu_store($this->metaKey($data), json_encode($this->metaData($data)));
            $this->storeLabelKeys($data);
        } else {
            // Taken from https://github.com/prometheus/client_golang/blob/66058aac3a83021948e5fb12f1f408ff556b9037/prometheus/value.go#L91
            $done = false;
            $loopCatcher = self::CAS_LOOP_RETRIES;
            while (!$done && $loopCatcher-- > 0) {
                $old = apcu_fetch($valueKey);
                if ($old !== false) {
                    $done = apcu_cas($valueKey, $old, $this->toBinaryRepresentationAsInteger($this->fromBinaryRepresentationAsInteger($old) + $data['value']));
                } else {
                    apcu_add($valueKey, $this->toBinaryRepresentationAsInteger(0));
                    apcu_store($this->metaKey($data), json_encode($this->metaData($data)));
                    $this->storeLabelKeys($data);
                }
            }
            if ($loopCatcher <= 0) {
                throw new RuntimeException('Caught possible infinite loop in ' . __METHOD__ . '()');
            }
        }
    }

    /**
     * @param mixed[] $data
     * @throws RuntimeException
     */
    public function updateCounter(array $data): void
    {
        // Taken from https://github.com/prometheus/client_golang/blob/66058aac3a83021948e5fb12f1f408ff556b9037/prometheus/value.go#L91
        $valueKey = $this->valueKey($data);
        $done = false;
        $loopCatcher = self::CAS_LOOP_RETRIES;
        while (!$done && $loopCatcher-- > 0) {
            $old = apcu_fetch($valueKey);
            if ($old !== false) {
                $done = apcu_cas($valueKey, $old, $this->toBinaryRepresentationAsInteger($this->fromBinaryRepresentationAsInteger($old) + $data['value']));
            } else {
                apcu_add($valueKey, 0);
                apcu_store($this->metaKey($data), json_encode($this->metaData($data)));
                $this->storeLabelKeys($data);
            }
        }
        if ($loopCatcher <= 0) {
            throw new RuntimeException('Caught possible infinite loop in ' . __METHOD__ . '()');
        }
    }

    /**
     * @param array<string> $metaData
     * @param string $labels
     * @return string
     */
    private function assembleLabelKey(array $metaData, string $labels): string
    {
        return implode(':', [ $this->prometheusPrefix, $metaData['type'], $metaData['name'], $labels, 'label' ]);
    }

    /**
     * Store ':label' keys for each metric's labelName in APCu.
     *
     * @param array<mixed> $data
     * @return void
     */
    private function storeLabelKeys(array $data): void
    {
        // Store labelValues in each labelName key
        foreach ($data['labelNames'] as $seq => $label) {
            $this->addItemToKey(implode(':', [
                $this->prometheusPrefix,
                $data['type'],
                $data['name'],
                $label,
                'label'
            ]), isset($data['labelValues']) ? $data['labelValues'][$seq] : ''); // may not need the isset check
        }
    }

    /**
     * Ensures an array serialized into APCu contains exactly one copy of a given string
     *
     * @return void
     * @throws RuntimeException
     */
    private function addItemToKey(string $key, string $item): void
    {
        // Modify serialized array stored in $key
        $arr = apcu_fetch($key);
        if (false === $arr) {
            $arr = [];
        }
        $_item = $this->encodeLabelKey($item);
        if (!array_key_exists($_item, $arr)) {
            $arr[$_item] = 1;
            apcu_store($key, $arr);
        }
    }

    /**
     * Removes all previously stored data from apcu
     *
     * NOTE: This is non-atomic: while it's iterating APCu, another thread could write a new Prometheus key that doesn't get erased.
     * In case this happens, getMetas() calls scanAndBuildMetainfoCache before reading metainfo back: this will ensure "orphaned"
     * metainfo gets enumerated.
     *
     * @return void
     */
    public function wipeStorage(): void
    {
        //                   /      / | PCRE expresion boundary
        //                    ^       | match from first character only
        //                     %s:    | common prefix substitute with colon suffix
        //                        .+  | at least one additional character
        $matchAll = sprintf('/^%s:.+/', $this->prometheusPrefix);

        foreach (new APCUIterator($matchAll) as $key => $value) {
            apcu_delete($key);
        }
    }

    /**
     * Sets the metainfo cache TTL; how long to retain metainfo before scanning APCu keyspace again (default 1 second)
     *
     * @param int $ttl
     * @return void
     */
    public function setMetainfoTTL(int $ttl): void
    {
        $this->metainfoCacheTTL = $ttl;
    }

    /**
     * Scans the APCu keyspace for all metainfo keys. A new metainfo cache array is built,
     * which references all metadata keys in APCu at that moment. This prevents a corner-case
     * where an orphaned key, while remaining writable, is rendered permanently invisible when reading
     * or enumerating metrics.
     *
     * Writing the cache to APCu allows it to be shared by other threads and by subsequent calls to getMetas(). This
     * reduces contention on APCu from repeated scans, and provides about a 2.5x speed-up when calling $this->collect().
     * The cache TTL is very short (default: 1sec), so if new metrics are tracked after the cache is built, they will
     * be readable at most 1 second after being written.
     *
     * Setting $apc_ttl less than 1 will disable the cache.
     *
     * @param int $apc_ttl
     * @return array<string>
     */
    private function scanAndBuildMetainfoCache(int $apc_ttl = 1): array
    {
        $arr = [];
        $matchAllMeta = sprintf('/^%s:.*:meta/', $this->prometheusPrefix);
        foreach (new APCUIterator($matchAllMeta) as $apc_record) {
            $arr[] = $apc_record['key'];
        }
        if ($apc_ttl >= 1) {
            apcu_store($this->metainfoCacheKey, $arr, $apc_ttl);
        }
        return $arr;
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    private function metaKey(array $data): string
    {
        return implode(':', [$this->prometheusPrefix, $data['type'], $data['name'], 'meta']);
    }

    /**
     * @param mixed[] $data
     * @return string
     */
    private function valueKey(array $data): string
    {
        return implode(':', [
            $this->prometheusPrefix,
            $data['type'],
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            'value',
        ]);
    }

    /**
     * @param mixed[] $data
     * @param string|int $bucket
     * @return string
     */
    private function histogramBucketValueKey(array $data, $bucket): string
    {
        return implode(':', [
            $this->prometheusPrefix,
            $data['type'],
            $data['name'],
            $this->encodeLabelValues($data['labelValues']),
            $bucket,
            'value',
        ]);
    }

    /**
     * @param mixed[] $data
     * @return mixed[]
     */
    private function metaData(array $data): array
    {
        $metricsMetaData = $data;
        unset($metricsMetaData['value'], $metricsMetaData['command'], $metricsMetaData['labelValues']);
        return $metricsMetaData;
    }

    /**
     * When given a ragged 2D array $labelValues of arbitrary size, and a 1D array $labelNames containing one
     * string labeling each row of $labelValues, return an array-of-arrays containing all possible permutations
     * of labelValues, with the sub-array elements in order of labelName.
     *
     * Example input:
     *  $labelNames:  ['endpoint', 'method', 'result']
     *  $labelValues: [0] => ['/', '/private', '/metrics'], // "endpoint"
     *                [1] => ['put', 'get', 'post'],        // "method"
     *                [2] => ['success', 'fail']            // "result"
     * Returned array:
     *  [0] => ['/', 'put', 'success'],          [1] => ['/', 'put', 'fail'],             [2] => ['/', 'get', 'success'],
     *  [3] => ['/', 'get', 'fail'],             [4] => ['/', 'post', 'success'],         [5] => ['/', 'post', 'fail'],
     *  [6] => ['/private', 'put', 'success'],   [7] => ['/private', 'put', 'fail'],      [8] => ['/private', 'get', 'success'],
     *  [9] => ['/private', 'get', 'fail'],     [10] => ['/private', 'post', 'success'], [11] => ['/private', 'post', 'fail'],
     *  [12] => ['/metrics', 'put', 'success'], [13] => ['/metrics', 'put', 'fail'],     [14] => ['/metrics', 'get', 'success'],
     *  [15] => ['/metrics', 'get', 'fail'],    [16] => ['/metrics', 'post', 'success'], [17] => ['/metrics', 'post', 'fail']
     * @param array<string> $labelNames
     * @param array<array> $labelValues
     * @return array<array>
     */
    private function buildPermutationTree(array $labelNames, array $labelValues): array /** @phpstan-ignore-line */
    {
        $treeRowCount = count(array_keys($labelNames));
        $numElements = 1;
        $treeInfo = [];
        for ($i = $treeRowCount - 1; $i >= 0; $i--) {
            $treeInfo[$i]['numInRow'] = count($labelValues[$i]);
            $numElements *= $treeInfo[$i]['numInRow'];
            $treeInfo[$i]['numInTree'] = $numElements;
        }

        $map = array_fill(0, $numElements, []);
        for ($row = 0; $row < $treeRowCount; $row++) {
            $col = $i = 0;
            while ($i < $numElements) {
                $val = $labelValues[$row][$col];
                $map[$i] = array_merge($map[$i], array($val));
                if (++$i % ($treeInfo[$row]['numInTree'] / $treeInfo[$row]['numInRow']) == 0) {
                    $col = ++$col % $treeInfo[$row]['numInRow'];
                }
            }
        }
        return $map;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectCounters(): array
    {
        $counters = [];
        foreach ($this->getMetas('counter') as $counter) {
            $metaData = json_decode($counter['value'], true);
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'samples' => [],
            ];
            foreach ($this->getValues('counter', $metaData) as $value) {
                $parts = explode(':', $value['key']);
                $labelValues = $parts[3];
                $data['samples'][] = [
                    'name' => $metaData['name'],
                    'labelNames' => [],
                    'labelValues' => $this->decodeLabelValues($labelValues),
                    'value' => $this->fromBinaryRepresentationAsInteger($value['value']),
                ];
            }
            $this->sortSamples($data['samples']);
            $counters[] = new MetricFamilySamples($data);
        }
        return $counters;
    }

    /**
     * When given a type ('histogram', 'gauge', or 'counter'), return an iterable array of matching records retrieved from APCu
     *
     * @param string $type
     * @return array<array>
     */
    private function getMetas(string $type): array /** @phpstan-ignore-line */
    {
        $arr = [];
        $metaCache = apcu_fetch($this->metainfoCacheKey);
        if (!is_array($metaCache)) {
            $metaCache = $this->scanAndBuildMetainfoCache($this->metainfoCacheTTL);
        }
        foreach ($metaCache as $metaKey) {
            if ((1 === preg_match('/' . $this->prometheusPrefix . ':' . $type . ':.*:meta/', $metaKey)) && false !== ($gauge = apcu_fetch($metaKey))) {
                $arr[] = [ 'key' => $metaKey, 'value' => $gauge ];
            }
        }
        return $arr;
    }

    /**
     * When given a type ('histogram', 'gauge', or 'counter') and metaData array, return an iterable array of matching records retrieved from APCu
     *
     * @param string $type
     * @param array<mixed> $metaData
     * @return array<array>
     */
    private function getValues(string $type, array $metaData): array /** @phpstan-ignore-line */
    {
        $labels = $arr = [];
        foreach (array_values($metaData['labelNames']) as $label) {
            $labelKey = $this->assembleLabelKey($metaData, $label);
            if (is_array($tmp = apcu_fetch($labelKey))) {
                $labels[] = array_map([$this, 'decodeLabelKey'], array_keys($tmp));
            }
        }
        // Append the histogram bucket-list and the histogram-specific label 'sum' to labels[] then generate the permutations
        if (isset($metaData['buckets'])) {
            $metaData['buckets'][] = 'sum';
            $labels[] = $metaData['buckets'];
            $metaData['labelNames'][] = '__histogram_buckets';
        }
        $labelValuesList = $this->buildPermutationTree($metaData['labelNames'], $labels);
        unset($labels);
        $histogramBucket = '';
        foreach ($labelValuesList as $labelValues) {
            // Extract bucket value from permuted element, if present, then construct the key and retrieve
            if (isset($metaData['buckets'])) {
                $histogramBucket = ':' . array_pop($labelValues);
            }
            $key = $this->prometheusPrefix . ":{$type}:{$metaData['name']}:" . $this->encodeLabelValues($labelValues) . $histogramBucket . ':value';
            if (false !== ($value = apcu_fetch($key))) {
                $arr[] = [ 'key' => $key, 'value' => $value ];
            }
        }
        return $arr;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectGauges(): array
    {
        $gauges = [];
        foreach ($this->getMetas('gauge') as $gauge) {
            $metaData = json_decode($gauge['value'], true);
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'samples' => [],
            ];
            foreach ($this->getValues('gauge', $metaData) as $value) {
                $parts = explode(':', $value['key']);
                $labelValues = $parts[3];
                $data['samples'][] = [
                    'name' => $metaData['name'],
                    'labelNames' => [],
                    'labelValues' => $this->decodeLabelValues($labelValues),
                    'value' => $this->fromBinaryRepresentationAsInteger($value['value']),
                ];
            }
            $this->sortSamples($data['samples']);
            $gauges[] = new MetricFamilySamples($data);
        }
        return $gauges;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectHistograms(): array
    {
        $histograms = [];
        foreach ($this->getMetas('histogram') as $histogram) {
            $metaData = json_decode($histogram['value'], true);

            // Add the Inf bucket so we can compute it later on
            $metaData['buckets'][] = '+Inf';

            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'buckets' => $metaData['buckets'],
            ];

            $histogramBuckets = [];
            foreach ($this->getValues('histogram', $metaData) as $value) {
                $parts = explode(':', $value['key']);
                $labelValues = $parts[3];
                $bucket = $parts[4];
                // Key by labelValues
                $histogramBuckets[$labelValues][$bucket] = $value['value'];
            }

            // Compute all buckets
            $labels = array_keys($histogramBuckets);
            sort($labels);
            foreach ($labels as $labelValues) {
                $acc = 0;
                $decodedLabelValues = $this->decodeLabelValues($labelValues);
                foreach ($data['buckets'] as $bucket) {
                    $bucket = (string)$bucket;
                    if (!isset($histogramBuckets[$labelValues][$bucket])) {
                        $data['samples'][] = [
                            'name' => $metaData['name'] . '_bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($decodedLabelValues, [$bucket]),
                            'value' => $acc,
                        ];
                    } else {
                        $acc += $histogramBuckets[$labelValues][$bucket];
                        $data['samples'][] = [
                            'name' => $metaData['name'] . '_' . 'bucket',
                            'labelNames' => ['le'],
                            'labelValues' => array_merge($decodedLabelValues, [$bucket]),
                            'value' => $acc,
                        ];
                    }
                }

                // Add the count
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_count',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => $acc,
                ];

                // Add the sum
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_sum',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => $this->fromBinaryRepresentationAsInteger($histogramBuckets[$labelValues]['sum']),
                ];
            }
            $histograms[] = new MetricFamilySamples($data);
        }
        return $histograms;
    }

    /**
     * @return MetricFamilySamples[]
     */
    private function collectSummaries(): array
    {
        $math = new Math();
        $summaries = [];
        foreach ($this->getMetas('summary') as $summary) {
            $metaData = $summary['value'];
            $data = [
                'name' => $metaData['name'],
                'help' => $metaData['help'],
                'type' => $metaData['type'],
                'labelNames' => $metaData['labelNames'],
                'maxAgeSeconds' => $metaData['maxAgeSeconds'],
                'quantiles' => $metaData['quantiles'],
                'samples' => [],
            ];

            foreach ($this->getValues('summary', $metaData) as $value) {
                $encodedLabelValues = $value['value'];
                $decodedLabelValues = $this->decodeLabelValues($encodedLabelValues);
                $samples = [];

                // Deterministically generate keys for all the sample observations, and retrieve them. Pass arrays to apcu_fetch to reduce calls to APCu.
                $end = time();
                $begin = $end - $metaData['maxAgeSeconds'];
                $valueKeyPrefix = $this->valueKey(array_merge($metaData, ['labelValues' => $decodedLabelValues]));

                $sampleCountKeysToRetrieve = [];
                for ($ts = $begin; $ts <= $end; $ts++) {
                    $sampleCountKeysToRetrieve[] = $valueKeyPrefix . ':' . $ts . ':observations';
                }
                $sampleCounts = apcu_fetch($sampleCountKeysToRetrieve);
                unset($sampleCountKeysToRetrieve);
                if (is_array($sampleCounts)) {
                    foreach ($sampleCounts as $k => $sampleCountThisSecond) {
                        $tstamp = explode(':', $k)[5];
                        $sampleKeysToRetrieve = [];
                        for ($i = 0; $i < $sampleCountThisSecond; $i++) {
                            $sampleKeysToRetrieve[] = $valueKeyPrefix . ':' . $tstamp . '.' . $i;
                        }
                        $newSamples = apcu_fetch($sampleKeysToRetrieve);
                        unset($sampleKeysToRetrieve);
                        if (is_array($newSamples)) {
                            $samples = array_merge($samples, $newSamples);
                        }
                    }
                }
                unset($sampleCounts);

                if (count($samples) === 0) {
                    apcu_delete($value['key']);
                    continue;
                }

                // Compute quantiles
                sort($samples);
                foreach ($data['quantiles'] as $quantile) {
                    $data['samples'][] = [
                        'name' => $metaData['name'],
                        'labelNames' => ['quantile'],
                        'labelValues' => array_merge($decodedLabelValues, [$quantile]),
                        'value' => $math->quantile($samples, $quantile),
                    ];
                }

                // Add the count
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_count',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => count($samples),
                ];

                // Add the sum
                $data['samples'][] = [
                    'name' => $metaData['name'] . '_sum',
                    'labelNames' => [],
                    'labelValues' => $decodedLabelValues,
                    'value' => array_sum($samples),
                ];
            }

            if (count($data['samples']) > 0) {
                $summaries[] = new MetricFamilySamples($data);
            } else {
                apcu_delete($summary['key']);
            }
        }
        return $summaries;
    }

    /**
     * @param mixed $val
     * @return int
     * @throws RuntimeException
     */
    private function toBinaryRepresentationAsInteger($val): int
    {
        $packedDouble = pack('d', $val);
        if ((bool)$packedDouble !== false) {
            $unpackedData = unpack("Q", $packedDouble);
            if (is_array($unpackedData)) {
                return $unpackedData[1];
            }
        }
        throw new RuntimeException("Formatting from binary representation to integer did not work");
    }

    /**
     * @param mixed $val
     * @return float
     * @throws RuntimeException
     */
    private function fromBinaryRepresentationAsInteger($val): float
    {
        $packedBinary = pack('Q', $val);
        if ((bool)$packedBinary !== false) {
            $unpackedData = unpack("d", $packedBinary);
            if (is_array($unpackedData)) {
                return $unpackedData[1];
            }
        }
        throw new RuntimeException("Formatting from integer to binary representation did not work");
    }

    /**
     * @param mixed[] $samples
     */
    private function sortSamples(array &$samples): void
    {
        usort($samples, function ($a, $b): int {
            return strcmp(implode("", $a['labelValues']), implode("", $b['labelValues']));
        });
    }

    /**
     * @param mixed[] $values
     * @return string
     * @throws RuntimeException
     */
    private function encodeLabelValues(array $values): string
    {
        $json = json_encode($values);
        if (false === $json) {
            throw new RuntimeException(json_last_error_msg());
        }
        return base64_encode($json);
    }

    /**
     * @param string $values
     * @return mixed[]
     * @throws RuntimeException
     */
    private function decodeLabelValues(string $values): array
    {
        $json = base64_decode($values, true);
        if (false === $json) {
            throw new RuntimeException('Cannot base64 decode label values');
        }
        $decodedValues = json_decode($json, true);
        if (false === $decodedValues) {
            throw new RuntimeException(json_last_error_msg());
        }
        return $decodedValues;
    }

    /**
     * @param string $keyString
     * @return string
     */
    private function encodeLabelKey(string $keyString): string
    {
        return base64_encode($keyString);
    }

    /**
     * @param string $str
     * @return string
     * @throws RuntimeException
     */
    private function decodeLabelKey(string $str): string
    {
        $decodedKey = base64_decode($str, true);
        if (false === $decodedKey) {
            throw new RuntimeException('Cannot base64 decode label key');
        }
        return $decodedKey;
    }
}
