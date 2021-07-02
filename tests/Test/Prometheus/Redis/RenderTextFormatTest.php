<?php

declare(strict_types=1);

namespace Test\Prometheus\Redis;

use Prometheus\Storage\Redis;
use Test\Prometheus\AbstractRenderTextFormatTest;

/**
 * @requires extension redis
 */
class RenderTextFormatTest extends AbstractRenderTextFormatTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new Redis(['host' => REDIS_HOST]);
        $this->adapter->wipeStorage();
    }
}
