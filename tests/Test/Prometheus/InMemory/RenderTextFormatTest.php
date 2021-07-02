<?php

declare(strict_types=1);

namespace Test\Prometheus\InMemory;

use Prometheus\Storage\InMemory;
use Test\Prometheus\AbstractRenderTextFormatTest;

class RenderTextFormatTest extends AbstractRenderTextFormatTest
{
    public function configureAdapter(): void
    {
        $this->adapter = new InMemory();
        $this->adapter->wipeStorage();
    }
}
