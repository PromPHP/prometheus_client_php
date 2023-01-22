<?php

namespace Prometheus\Storage\RedisTxn\RedisScript;

use InvalidArgumentException;

class RedisScriptBuilder
{
    /**
     * @var string
     */
    private $script;

    /**
     * @var array
     */
    private $args;

    /**
     * @var int
     */
    private $numKeys;

    /**
     * @param string $script
     * @return RedisScriptBuilder
     */
    public function withScript(string $script): RedisScriptBuilder
    {
        $this->script = $script;
        return $this;
    }

    /**
     * @param array $args
     * @return RedisScriptBuilder
     */
    public function withArgs(array $args): RedisScriptBuilder
    {
        $this->args = $args;
        return $this;
    }

    /**
     * @param int $numKeys
     * @return RedisScriptBuilder
     */
    public function withNumKeys(int $numKeys): RedisScriptBuilder
    {
        $this->numKeys = $numKeys;
        return $this;
    }

    /**
     * @return RedisScript
     */
    public function build(): RedisScript
    {
        $this->validate();
        return new RedisScript(
            $this->script,
            $this->args ?? [],
            $this->numKeys ?? 0
        );
    }

    /**
     * @return void
     * @throws InvalidArgumentException
     */
    private function validate(): void
    {
        if ($this->script === null) {
            throw new InvalidArgumentException('A Redis script is required.');
        }
    }
}
