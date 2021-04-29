<?php

declare(strict_types=1);

namespace Prometheus;

class Math
{

    /**
     * taken from https://www.php.net/manual/fr/function.stats-stat-percentile.php#79752
     * @param array $arr must be sorted
     * @param float $q
     * @return float
     */
    public function quantile(array $arr, float $q): float
    {
        $count = count($arr);
        $allindex = ($count-1)*$q;
        $intvalindex = (int) $allindex;
        $floatval = $allindex - $intvalindex;
        if(!is_float($floatval)){
            $result = $arr[$intvalindex];
        }else {
            if($count > $intvalindex+1)
                $result = $floatval*($arr[$intvalindex+1] - $arr[$intvalindex]) + $arr[$intvalindex];
            else
                $result = $arr[$intvalindex];
        }
        return $result;
    }
}
