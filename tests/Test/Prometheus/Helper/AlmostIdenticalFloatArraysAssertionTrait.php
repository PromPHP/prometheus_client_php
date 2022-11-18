<?php

declare(strict_types=1);

namespace Test\Prometheus\Helper;

use PHPUnit\Framework\Assert;
use PHPUnit\Framework\Constraint\IsEqual;

trait AlmostIdenticalFloatArraysAssertionTrait
{
    public static function assertIsAlmostEqual($expected, $actual): void
    {
        $constraint = self::almostEqualTo($expected);

        Assert::assertThat($actual, $constraint);
    }

    public static function almostEqualTo($expected): IsEqual
    {
        return new IsEqual($expected, 0.000001);
    }
}
