<?php

declare(strict_types=1);

namespace Test\Prometheus;

use PHPUnit\Framework\TestCase;
use Prometheus\CollectorRegistry;
use Prometheus\Storage\InMemory;

class CollectorTest extends TestCase
{
    /**
     * @var CollectorRegistry
     */
    public $registry;

    public function setUp(): void
    {
        $this->registry = new CollectorRegistry(new InMemory(), false);
    }

    public function testInvalidNamespace(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->getOrRegisterCounter('bad%namespace', 'counter', 'counter-help-text');
    }

    public function testInvalidMetricName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->getOrRegisterCounter('mynamespace', 'coun^ter', 'counter-help-text');
    }

    public function testInvalidLabelName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->registry->getOrRegisterCounter('mynamespace', 'counter', 'counter-help-text', ['label1','label:2']);
    }
}
