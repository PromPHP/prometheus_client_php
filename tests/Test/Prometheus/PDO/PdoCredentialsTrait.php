<?php

declare(strict_types=1);

namespace Test\Prometheus\PDO;

trait PdoCredentialsTrait
{
    private function getEnvironmentWithDefault(string $name, ?string $default = null): ?string
    {
        $env = getenv($name);
        return $env === false ? $default : $env;
    }

    private function getDsn(): string
    {
        return $this->getEnvironmentWithDefault('TEST_PDO_DSN', 'sqlite::memory:');
    }

    private function getUsername(): ?string
    {
        return $this->getEnvironmentWithDefault('TEST_PDO_USERNAME');
    }

    private function getPassword(): ?string
    {
        return $this->getEnvironmentWithDefault('TEST_PDO_PASSWORD');
    }
}
