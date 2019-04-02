<?php

namespace ImageMatch;

class ImageSignature
{

    public static function imageToColors($path, $grayscale = false)
    {
        $image = imagecreatefromjpeg($path);
        $width = imagesx($image);
        $height = imagesy($image);
        $colors = array();

        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $rgb = imagecolorat($image, $x, $y);
                $rgba = imagecolorsforindex($image, imagecolorat($image, $x, $y));

                $colors[$y][$x] = $rgba;
                if ($grayscale) {
                    $colors[$y][$x] = (0.2125 * $rgba['red'] + 0.7154 * $rgba['green'] + 0.0721 * $rgba['blue']) / 255;
                }
            }
        }

        return $colors;
    }

    public static function cropImage($image, $lowerPercentile = 5, $upperPercentile = 95, $fixRatio = false)
    {
        $shape = Matrix::shape($image);
        $rw = Matrix::cumsum(Matrix::sum(Matrix::abs(Matrix::diff($image, 1)), 1));
        $cw = Matrix::cumsum(Matrix::sum(Matrix::abs(Matrix::diff($image, 0)), 0));

        $upperColLimit = Matrix::percentileIndex($cw, $upperPercentile, 'left');
        $lowerColLimit = Matrix::percentileIndex($cw, $lowerPercentile, 'right');

        $upperRowLimit = Matrix::percentileIndex($rw, $upperPercentile, 'left');
        $lowerRowLimit = Matrix::percentileIndex($rw, $lowerPercentile, 'right');

        // if image is nearly featureless, use default region
        if ($lowerRowLimit > $upperRowLimit) {
            $lowerRowLimit = round($lowerPercentile/100 * $shape[0]);
            $upperRowLimit = round($upperPercentile/100 * $shape[0]);
        }

        if ($lowerColLimit > $upperColLimit) {
            $lowerColLimit = round($lowerPercentile/100 * $shape[1]);
            $upperColLimit = round($upperPercentile/100 * $shape[1]);
        }

        if ($fixRatio) {
            if (( $upperRowLimit - $lowerRowLimit ) > ( $upperColLimit - $lowerColLimit )) {
                return [
                    [$lowerRowLimit, $upperRowLimit],
                    [$lowerRowLimit, $upperRowLimit]
                ];
            }

            return [
                [$lowerColLimit, $upperColLimit],
                [$lowerColLimit, $upperColLimit]
            ];
        }

        // otherwise, proceed as normal
        return [
            [$lowerRowLimit, $upperRowLimit],
            [$lowerColLimit, $upperColLimit]
        ];
    }

    public static function computeGridPoints($image, $n = 9, $window = false)
    {
        $shape = Matrix::shape($image);
        if (!$window) {
            $window = [[0, $shape[0]],[0, $shape[1]]];
        }

        $xCoords = Matrix::linspace($window[0][0], $window[0][1], $n+2);
        $yCoords = Matrix::linspace($window[1][0], $window[1][1], $n+2);

        return [Matrix::slice($xCoords, [1,-1]), Matrix::slice($yCoords, [1,-1])];
    }

    public static function computeMeanLevel($image, $coords, $P = false)
    {
        $shape = Matrix::shape($image);
        if (!$P) {
            $P = max([2.0, (int)(0.5 + min($shape)/20)]);
        }

        $xCoords = $coords[0];
        $yCoords = $coords[1];

        $avgGrey = Matrix::zeros([count($xCoords), count($yCoords)]);

        foreach ($xCoords as $i => $x) {
            $lowerXLim = (int) max([$x - $P/2, 0]);
            $upperXLim = (int) min([$lowerXLim + $P, $shape[0]]);

            foreach ($yCoords as $j => $y) {
                $lowerYLim = (int) max([$y - $P/2, 0]);
                $upperYLim = (int) min([$lowerYLim + $P, $shape[1]]);

                $grid = Matrix::slice($image, [$lowerXLim, $upperXLim], [$lowerYLim, $upperYLim]);
                $gridShape = Matrix::shape($grid);
                $avgGrey[$i][$j] = array_sum(Matrix::sum($grid, 1))/($gridShape[0]*$gridShape[1]);
            }
        }

        return $avgGrey;
    }

    public static function computeDifferentials($greyMatrix, $diagonalNeighbors = true)
    {
        $shape = Matrix::shape($greyMatrix);
        $diff = Matrix::diff($greyMatrix);
        $zeros = Matrix::zeros([$shape[0],1]);

        $rightNeighbors = Matrix::multiply(Matrix::concatenate($diff, $zeros), -1);

        $leftNeighbors  = Matrix::multiply(Matrix::concatenate(Matrix::slice($rightNeighbors, [0], [-1]), Matrix::slice($rightNeighbors, [0], [0, -1])), -1);

        $diff = Matrix::diff($greyMatrix, 0);
        $zeros = Matrix::zeros([1,$shape[0]]);

        $downNeighbors = Matrix::multiply(Matrix::concatenate($diff, $zeros, 0), -1);
        $upNeighbors   = Matrix::multiply(Matrix::concatenate(Matrix::slice($downNeighbors, [-1]), Matrix::slice($downNeighbors, [0, -1]), 0), -1);

        if ($diagonalNeighbors) {
            list($upperLeftNeighbors, $lowerRightNeighbors) = self::computeDiagonalNeighbors($greyMatrix);

            $flipped = Matrix::fliplr($greyMatrix);

            list($upperRightNeighbors, $lowerLeftNeighbors) = self::computeDiagonalNeighbors($flipped);

            return Matrix::dstack([
                $upperLeftNeighbors,
                $upNeighbors,
                Matrix::fliplr($upperRightNeighbors),
                $leftNeighbors,
                $rightNeighbors,
                Matrix::fliplr($lowerLeftNeighbors),
                $downNeighbors,
                $lowerRightNeighbors
            ]);
        }

        return Matrix::dstack([
            $upNeighbors,
            $leftNeighbors,
            $rightNeighbors,
            $downNeighbors
        ]);
    }

    public static function computeDiagonalNeighbors($greyMatrix)
    {
        $shape = Matrix::shape($greyMatrix);
        $diagonals = range(-$shape[0] + 1, $shape[0] - 1);

        $diagflat = [];

        foreach ($diagonals as $i) {
            $diagDiff = Matrix::diff(Matrix::diag($greyMatrix, $i));
            array_splice($diagDiff, 0, 0, 0);
            $diagflat[] = Matrix::diagflat($diagDiff, $i);
        }

        $upperNeighbors = Matrix::sum($diagflat, 0);
        $slice = Matrix::slice($upperNeighbors, [1], [1]);
        $sliceShape = Matrix::shape($slice);

        $slice = Matrix::concatenate($slice, Matrix::zeros([1, $sliceShape[0]]), 0);
        $lowerNeighbors = Matrix::concatenate($slice, Matrix::zeros([$sliceShape[0]+1, 1]));
        $lowerNeighbors = Matrix::multiply($lowerNeighbors, -1);

        return [$upperNeighbors, $lowerNeighbors];
    }

    public static function normalizeAndThreshold($diffArray, $identitalTolerance = 2/255.0, $nLevels = 2)
    {
        $masked = Matrix::map($diffArray, function ($v) use ($identitalTolerance) {
            return abs($v) < $identitalTolerance ? 0 : $v;
        });

        $positive = Matrix::flatten(Matrix::filter($masked, function ($v) {
            return $v > 0;
        }));
        $negative = Matrix::flatten(Matrix::filter($masked, function ($v) {
            return $v < 0;
        }));

        $positiveCutoffs = Matrix::percentile($positive, Matrix::linspace(0, 100, $nLevels+1));
        $negativeCutoffs = Matrix::percentile($negative, Matrix::linspace(100, 0, $nLevels+1));

        $enumerate = array_map(function ($i) use ($positiveCutoffs) {
            return array_slice($positiveCutoffs, $i, $i+2);
        }, range(0, count($positiveCutoffs)-2));

        foreach ($enumerate as $level => $interval) {
            $masked =  Matrix::map($masked, function ($v) use ($level, $interval) {
                if ($v >= $interval[0] && $v <= $interval[1]) {
                    return $level + 1;
                }
                return $v;
            });
        }

        $enumerate = array_map(function ($i) use ($negativeCutoffs) {
            return array_slice($negativeCutoffs, $i, $i+2);
        }, range(0, count($negativeCutoffs)-2));

        foreach ($enumerate as $level => $interval) {
            $masked =  Matrix::map($masked, function ($v) use ($level, $interval) {
                if ($v <= $interval[0] && $v >= $interval[1]) {
                    return -($level + 1);
                }

                return $v;
            });
        }

        return $masked;
    }

    public static function generateSignature($path, $image)
    {
        # Step 1:    Load image as array of grey-levels
        if (empty($image)) {
            $image = self::imageToColors($path, true);
        }

        # Step 2a:   Determine cropping boundaries
        $imageLimits = self::cropImage($image);

        # Step 2b:   Generate grid centers
        $coords = self::computeGridPoints($image, 9, $imageLimits);

        # Step 3:    Compute grey level mean of each P x P
        #           square centered at each grid point
        $avgGrey = self::computeMeanLevel($image, $coords);

        # Step 4a:   Compute array of differences for each
        #           grid point vis-a-vis each neighbor
        $diffMatrix = self::computeDifferentials($avgGrey);

        # Step 4b: Bin differences to only 2n+1 values
        $normalMatrix = self::normalizeAndThreshold($diffMatrix);

        # Step 5: Flatten array and return signature
        return array_map('intval', Matrix::flatten($normalMatrix));
    }
}
