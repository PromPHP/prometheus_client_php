<?php

declare(strict_types=1);

namespace Test\Prometheus\APC;

use Prometheus\Storage\APC;
use Test\Prometheus\AbstractRenderTextFormatTest;

/**
 * @requires extension apcu
 */
class RenderTextFormatTest extends AbstractRenderTextFormatTest
{

    public function configureAdapter(): void
    {
        $this->adapter = new APC();
        $this->adapter->wipeStorage();
    }
}
