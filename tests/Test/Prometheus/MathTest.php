<?php

declare(strict_types=1);

namespace Test\Prometheus;

use PHPUnit\Framework\TestCase;
use Prometheus\Math;

class MathTest extends TestCase
{

    /**
     * @dataProvider providerQuantileSuccess
     *
     * @param float[] $samples
     */
    public function testQuantileSuccess(array $samples, float $q, float $expected): void
    {
        $math = new Math();
        $result = $math->quantile($samples, $q);

        self::assertEquals($expected, $result);
    }

    /**
     * @return array[]
     */
    public function providerQuantileSuccess(): array
    {
        return [
            'Even serie' => [[0, 1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 0.5, 5],
            'Odd serie' => [[1, 2, 3, 4, 5, 6, 7, 8, 9, 10], 0.5, 5.5],
            'Even float serie' => [[0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 0.10], 0.5, 0.5],
            'Odd float serie' => [[0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.7, 0.8, 0.9, 0.10], 0.5, 0.55],
            'Empty serie' => [[], 0.5, 0],
        ];
    }
}
