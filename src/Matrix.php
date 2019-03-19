<?php

namespace ImageMatch;

use MathPHP\Functions\Map;

class Matrix
{

    public static function sum($array, $axis = false)
    {
        if (!is_array($array)) {
            return $array;
        }

        $shape = self::shape($array);
        if ($axis === false) {
            $axis = end($shape);
        }

        $sum = [];
        $lengthOfArrays = count($array);

        if ($axis === 0) {
            if (count($shape) > 2) {
                for ($i = 0; $i < $shape[1]; $i++) {
                    $cols = array_column($array, $i);
                    if (!empty($cols) && count($cols) > 1) {
                        $sum[] = self::sum($cols, 0);
                    }
                }
                return $sum;
            }
            return Map\Multi::add(...$array);
        }

        if (! is_array(reset($array))) {
            return array_sum($array);
        }

        for ($i = 0; $i < $lengthOfArrays; $i++) {
            $sum[] = array_sum($array[$i]);
        }

        return $sum;
    }

    public static function multiply($array, $number)
    {
        if (!is_array($array)) {
            return is_numeric($array) ? $array * $number : $array;
        }

        $multiply = [];
        $lengthOfArrays = count($array);
        for ($i = 0; $i < $lengthOfArrays; $i++) {
            $multiply[] = self::multiply($array[$i], $number);
        }

        return $multiply;
    }

    public static function cumsum($array)
    {
        $shape = self::shape($array);
        $sum = [];
        $lengthOfArrays = count($array);

        if (! is_array(reset($array))) {
            $arrSum = [];
            for ($i = 0; $i < $lengthOfArrays; $i++) {
                $arrSum[] = ( $i === 0 ? 0 : $arrSum[$i-1] ) + $array[$i];
            }
            return $arrSum;
        }

        for ($i = 0; $i < $lengthOfArrays; $i++) {
            $sum[] = self::cumsum($array[$i]);
            ;
        }
        return $sum;
    }

    public static function subtract($array1, $array2)
    {
        $shape1 = self::shape($array1);
        $shape2 = self::shape($array2);

        if (count($shape1) !== 2) {
            return $array1;
        }

        if (count($shape2) > 1 && $shape1[0] !== $shape2[0]) {
            return $array1;
        }

        if (count($shape2) === 2 && $shape2[1] === 1) {
            $array2 = array_unshift($array2);
        }

        $subtract = [];
        $lengthOfArrays = count($array1);

        for ($i = 0; $i < $lengthOfArrays; $i++) {
            $subtract[] = Map\Multi::Subtract($array2, $array1[$i]);
        }
        return $subtract;
    }

    public static function diff($array, $axis = false)
    {
        $shape = self::shape($array);

        if (count($shape) === 1) {
            return self::pairDiff($array);
        }

        if ($axis === false) {
            $axis = end($shape);
        }

        $differences = [];
        $lengthOfArrays = count($array);

        if ($axis === 0) {
            for ($i = 0; $i < $lengthOfArrays - 1; $i++) {
                $differences[] = Map\Multi::subtract($array[$i+1], $array[$i]);
            }
            return $differences;
        }

        for ($i = 0; $i < $lengthOfArrays; $i++) {
            $differences[] = self::pairDiff($array[$i]);
        }

        return $differences;
    }

    public static function pairDiff($array)
    {
        $diff = [];
        $length = count($array);
        for ($i = 0; $i < $length - 1; $i++) {
            $diff[] = $array[$i + 1] - $array[$i];
        }
        return $diff;
    }

    public static function abs($array)
    {
        if (! is_array($array)) {
            return abs($array);
        }
        return array_map('self::abs', $array);
    }

    public static function concatenate($array1, $array2, $axis = false)
    {
        $shape = self::shape($array1);
        if ($axis === false) {
            $axis = count($shape) - 1;
        }

        if ($axis === 0) {
            if (count(reset($array1)) !== count(reset($array2))) {
                return $array1;
            }
            return array_merge($array1, $array2);
        }

        if ($axis === 1) {
            if (count($array1) !== count($array2)) {
                return $array1;
            }

            for ($i=0; $i < count($array1); $i++) {
                if (isset($array2[$i]) && count($array2[$i])) {
                    $array1[$i] = array_merge($array1[$i], $array2[$i]);
                }
            }
        }

        return $array1;
    }

    /**
     * Calculate the shape of an array
     *
     * @param array   $array
     * @param array   $shape
     * @return array
     */
    public static function shape($array, &$shape = [])
    {
        if (is_array($array)) {
            $shape[] = count($array);
            self::shape(array_shift($array), $shape);
        }

        return $shape;
    }

    public static function percentile($array, $percentile)
    {

        if (is_array($percentile)) {
            $results = [];
            foreach ($percentile as $perc) {
                $results[] = self::percentile($array, $perc);
            }
            return $results;
        }

        $percentile = min(100, max(0, $percentile));
        $array = array_values($array);
        sort($array);
        $index = ($percentile / 100) * (count($array) - 1);
        $fractionPart = $index - floor($index);
        $intPart = floor($index);

        $percentile = $array[$intPart];
        $percentile += ($fractionPart > 0) ? $fractionPart * ($array[$intPart + 1] - $array[$intPart]) : 0;

        return $percentile;
    }

    public static function percentileIndex($array, $percentile, $side = 'left')
    {
        $perc = $percentile / 100 * count($array);
        return $side === 'left' ? floor($perc) : ceil($perc);
    }

    public static function linspace($start, $stop, $num, $endpoint = true)
    {
        $stop = $endpoint ? $stop : $stop - (($stop-$start)/($num-1));
        $step = ($stop-$start)/($num-1);
        return array_map('floor', range($start, $stop, $step));
    }

    public static function zeros($shape)
    {
        return array_fill(0, $shape[0], array_fill(0, $shape[1], 0));
    }

    public static function slice($array, $rows = [], $cols = [])
    {
        if (empty($rows[0])) {
            $rows[0] = 0;
        }

        if (empty($rows[1])) {
            $rows[1] = count($array);
        }

        $shape = Matrix::shape($array);
        if (count($shape) === 1) {
            return array_slice($array, $rows[0], $rows[1] < 0 ? $rows[1] : $rows[1] - $rows[0]);
        }

        if (empty($cols[0])) {
            $cols[0] = 0;
        }

        if (empty($cols[1])) {
            $cols[1] = count($array[0]);
        }

        return array_map(function ($row) use ($cols) {
            return array_slice($row, $cols[0], $cols[1] - $cols[0]);
        }, array_slice($array, $rows[0], $rows[1] - $rows[0]));
    }

    public static function flipDiagonally($arr)
    {
        $out = array();
        foreach ($arr as $key => $subarr) {
            $out[$subkey][$key] = $subvalue;
        }
        return $out;
    }

    public static function diag($array, $k = 0)
    {
        $row = $j = 0;
        $return = [];

        if ($k>=0) {
            $j = $k;
        } else {
            $row = abs($k);
        }

        for ($i=$row; $i < count($array); $i++) {
            if (isset($array[$i]) && isset($array[$i][$j])) {
                $return[] = $array[$i][$j];
            }
            $j++;
        }

        return $return;
    }

    public static function diagflat($array, $k = 0)
    {
        $row    = $j = 0;
        $return = [];
        $count  = count($array) + abs($k);
        $rAdd  = $k > 0 ? $k : 0;
        $cAdd  = $k < 0 ? abs($k) : 0;

        for ($i=0; $i < $count; $i++) {
            $return[$i] = [];
            for ($j=0; $j < $count; $j++) {
                if ($i+$rAdd === $j+$cAdd) {
                    $index = $k > 0 ? $i : $j;
                    $return[$i][$j] = isset($array[$index]) ? $array[$index] : 0;
                } else {
                    $return[$i][$j] = 0;
                }
            }
        }

        return $return;
    }

    public static function fliplr($array, $level = 0)
    {
        $flipped = $level !== 0 ? array_reverse($array) : $array;
        $lengthOfArrays = count($flipped);

        for ($i = 0; $i < $lengthOfArrays; $i++) {
            if (is_array($flipped[$i])) {
                $flipped[$i] = self::fliplr($flipped[$i], 1);
            }
        }
        return $flipped;
    }

    public static function dstack($array)
    {
        $shape = Matrix::shape($array);
        if (count($shape) !== 3) {
            return $array;
        }

        $return = [];
        for ($i=0; $i < $shape[1]; $i++) {
            $return[$i] = [];
            for ($j=0; $j < end($shape); $j++) {
                $return[$i][$j] = [];
                for ($k=0; $k < reset($shape); $k++) {
                    $return[$i][$j][$k] = $array[$k][$i][$j];
                }
            }
        }

        return $return;
    }

    public static function map($array, $callback)
    {
        if (!is_array($array)) {
            return call_user_func($callback, $array);
        }

        $result = [];
        $lengthOfArrays = count($array);
        for ($i = 0; $i < $lengthOfArrays; $i++) {
            $result[] = self::map($array[$i], $callback);
        }

        return $result;
    }

    public static function filter($array, $callback = null)
    {
        if (!is_array($array)) {
            if (is_callable($callback)) {
                return call_user_func($callback, $array) ? $array : false;
            }
            return !!$array ? $array : false;
        }

        $result = [];
        $lengthOfArrays = count($array);
        for ($i = 0; $i < $lengthOfArrays; $i++) {
            $filtered = is_array($array[$i]) ? array_filter(self::filter($array[$i], $callback)) : self::filter($array[$i], $callback);
            if (!empty($filtered)) {
                $result[] = $filtered;
            }
        }

        return $result;
    }

    public static function all($array)
    {
        try {
            self::map($array, function ($v) {
                if (!$v) {
                    throw new Exception;
                }
            });
        } catch (Exception $ex) {
            return false;
        }

        return true;
    }

    public static function flatten($array)
    {
        $return = array();
        array_walk_recursive($array, function ($a) use (&$return) {
            $return[] = $a;
        });
        return $return;
    }

    public static function dotProduct($array1, $array2)
    {

        $aShape = self::shape($array1);
        $bShape = self::shape($array2);
        if (count($bShape) > 1) {
            return $array1;
        }

        $matrix = [];

        for ($i = 0; $i < reset($aShape); $i++) {
            $matrix[$i] = [];

            $matrix[$i] = array_sum(array_map(
                function ($a, $b) {
                    return $a * $b;
                },
                $array1[$i],
                $array2
            ));
        }

        return $matrix;
    }

    public static function rot90($array, $axis = 1)
    {
        $shape = self::shape($array);
        if (count($shape) !== 2) {
            return $array;
        }

        $result = [];

        if ($axis === 1) {
            for ($i=$shape[1] - 1; $i >= 0; $i--) {
                $result[$i] = array_column($array, $i);
            }
        } elseif ($axis === 2) {
            $result = array_reverse(array_map('array_reverse', $array));
        } elseif ($axis === 3) {
            for ($i=0; $i < $shape[1]; $i++) {
                $result[$i] = array_reverse(array_column($array, $i));
            }
        }

        return empty($result) ? $array : $result;
    }

    public static function norm($array, $axis = 1)
    {
        $shape = self::shape($array);
        if (count($shape) === 1) {
            /**
             * The Euclidean Norm
             * http://mathonline.wikidot.com/the-euclidean-norm
             */
            return sqrt(array_sum(array_map(function ($v) {
                return abs($v) * abs($v);
            }, $array)));
        }

        if (count($shape) !== 2) {
            return $array;
        }

        if ($axis === 1) {
            $result = [];
            for ($i = 0; $i < $shape[0]; $i++) {
                $result[] = self::norm($array[$i]);
            }
            return $result;
        }

        if ($axis === 0) {
            $result = [];
            for ($i = 0; $i < $shape[1]; $i++) {
                $result[] = self::norm(array_column($array, $i));
            }
            return $result;
        }
    }
}
