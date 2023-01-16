<?php

namespace Test\Benchmark;

use InvalidArgumentException;

class MetricType
{
	const COUNTER = 1<<0;
	const GAUGE = 1<<1;
	const HISTOGRAM = 1<<2;
	const SUMMARY = 1<<3;

	/**
	 * @param int $type
	 * @return string
	 */
	public static function toString(int $type): string
	{
		switch ($type) {
			case MetricType::COUNTER:
				return 'counter';
			case MetricType::GAUGE:
				return 'gauge';
			case MetricType::HISTOGRAM:
				return 'histogram';
			case MetricType::SUMMARY:
				return 'timer';
		}

		throw new InvalidArgumentException("Invalid adapter type: {$type}");
	}
}
