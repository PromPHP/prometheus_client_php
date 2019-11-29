<?php

namespace Test\Prometheus\APC;

use Prometheus\Storage\APC;
use Test\Prometheus\AbstractHistogramTest;

/**
 * See https://prometheus.io/docs/instrumenting/exposition_formats/
 * @requires extension apc
 */
class HistogramTest extends AbstractHistogramTest
{
    public function configureAdapter()
    {
        $this->adapter = new APC();
        $this->adapter->flushAPC();
    }
}
