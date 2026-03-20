<?php

declare(strict_types=1);

namespace Test\Prometheus\PDO;

use Prometheus\Storage\PDO;
use Test\Prometheus\AbstractCounterTestCase;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 */
class CounterTest extends AbstractCounterTestCase
{
    use PdoCredentialsTrait;

    /**
     * @var \PDO|null
     */
    private $pdo;

    public function configureAdapter(): void
    {
        $this->pdo = new \PDO($this->getDsn(), $this->getUsername(), $this->getPassword());
        $prefix = 'test' . substr(hash('sha256', uniqid()), 0, 6) . '_';
        $this->adapter = new PDO($this->pdo, $prefix);
        $this->adapter->wipeStorage();
    }

    protected function tearDown(): void
    {
        parent::tearDown();
        $this->adapter->deleteTables(); /** @phpstan-ignore-line */
        $this->adapter = null; /** @phpstan-ignore-line */
        $this->pdo = null;
    }
}
