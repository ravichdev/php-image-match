<?php

namespace ImageMatch\Database;

use ImageMatch\ImageSignature;
use ImageMatch\Matrix;

abstract class SignatureDatabaseBase
{
    protected $k;
    protected $N;
    protected $cropPercentile;
    protected $distanceCutoff;

    abstract public function insert($record);

    abstract public function search($record);

    public function __construct($k = 16, $N = 63, $nGrid = 9, $cropPercentile = [5,95], $distanceCutoff = 0.45)
    {
        $this->k = $k;
        $this->N = $N;
        $this->cropPercentile = $cropPercentile;
        $this->distanceCutoff = $distanceCutoff;
    }

    public function addImage($image, $metadata = [])
    {
        $record = $this->makeRecord($image, null, $metadata);
        return $this->insert($record);
    }

    public function searchImage($path, $allOrientations = false)
    {
        $image = ImageSignature::imageToColors($path, true);

        $orientations = [
            $image
        ];

        if ($allOrientations) {
            foreach (range(1, 3) as $rotAxis) {
                $orientations[] = Matrix::rot90($image, $rotAxis);
            }
        }

        $results = [];

        foreach ($orientations as $img) {
            $record = $this->makeRecord($path, $img);
            $results = array_merge($results, $this->search($record));
        }

        usort($results, function ($a, $b) {
            return $a['dist'] > $b['dist'] ? 1 : -1;
        });

        $ids = [];
        $unique = [];

        foreach ($results as $item) {
            if (!isset($ids[$item['id']])) {
                $ids[$item['id']] = true;
                $unique[] = $item;
            }
        }

        return $unique;
    }

    public function makeRecord($path, $imageArray = null, $metadata = [])
    {
        $record = [
            'path' => $path
        ];

        if (!empty($metadata)) {
            $record['metadata'] = $metadata;
        }

        $signature = ImageSignature::generateSignature($path, $imageArray);
        $record['signature'] = $signature;

        $words = $this->getWords($signature, $this->k, $this->N);
        $words = $this->maxContrast($words);
        $words = $this->wordsToInt($words);

        for ($i = 0; $i < $this->N; $i++) {
            $record["sig_$i"] = $words[$i];
        }

        return $record;
    }

    public function getWords($signature, $k, $N)
    {
        $shape = Matrix::shape($signature);
        $wordPositions = Matrix::linspace(0, $shape[0], $N, false);
        $words = Matrix::zeros([$N, $k]);

        foreach ($wordPositions as $i => $pos) {
            if ($pos + $k <= $shape[0]) {
                $words[$i] = array_slice($signature, $pos, $k);
            } else {
                $temp = array_slice($signature, $pos);
                $pad = array_fill(0, $k - count($temp), 0);
                $words[$i] = array_merge($temp, $pad);
            }
        }

        return $words;
    }

    public function maxContrast($words)
    {
        return Matrix::map($words, function ($v) {
            return $v > 0 ? 1 : ( $v < 0 ? -1 : 0 );
        });
    }

    public function wordsToInt($words)
    {
        $shape = Matrix::shape($words);
        $width = $shape[1];
        $codingVector = array_map(function ($v) {
            return pow(3, $v);
        }, range(0, $width-1));

        $words = Matrix::map($words, function ($v) {
            return $v + 1;
        });
        return Matrix::dotProduct($words, $codingVector);
    }

    public function normalizedDistance($targetArray, $vector)
    {
        $subtract = Matrix::subtract($targetArray, $vector);
        $topVector = Matrix::norm($subtract);
        $norm1 = Matrix::norm($vector, 0);
        $norm2 = Matrix::norm($targetArray, 1);
        $finVector = array_map(function ($v1, $v2) {
            return $v1/$v2;
        }, $topVector, Matrix::sum([array_fill(0, count($norm2), $norm1), $norm2], 1));
        return $finVector;
    }

    public function cleanRecord($record)
    {
        foreach (['path', 'signature', 'metadata'] as $field) {
            if (isset($record[$field])) {
                unset($record[$field]);
            }
        }

        return $record;
    }
}
