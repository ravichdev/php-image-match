<?php

abstract class SignatureDatabaseBase
{

    protected $gis;
    protected $k;
    protected $N;
    protected $cropPercentile;
    protected $distanceCutoff;

    public function __construct($k = 16, $N = 63, $nGrid = 9, $cropPercentile = [5,95], $distanceCutoff = 0.5)
    {
        $this->gis = new ImageSignature();
        $this->k = $k;
        $this->N = $N;
        $this->cropPercentile = $cropPercentile;
        $this->distanceCutoff = $distanceCutoff;
    }

    abstract public function search();

    abstract public function insert();

    public function add($image, $metadata = [])
    {
    }

    public function makeRecord($path, $metadata = [])
    {
        $record = [
            'path' => $path
        ];

        if (!empty($metadata)) {
            $record['metadata'] = $metadata;
        }

        $signature = ImageSignature::generateSignature($path);

        $words = $this->getWords($signature, $this->k, $this->N);
        $words = $this->maxContrast($words);
        $words = $this->wordsToInt($words);
        $record = [];
        for ($i = 0; $i < $this->N; $i++) {
            $record["simple_word_$i"] = $words[$i];
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
}
