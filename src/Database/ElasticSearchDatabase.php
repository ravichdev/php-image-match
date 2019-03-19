<?php

namespace ImageMatch\Database;

use Elasticsearch\Client;

class ElasticSearchDatabase extends SignatureDatabaseBase
{
    private $client;
    private $index;
    private $docType;
    private $size;

    public function __construct(Client $client, $index = 'images', $docType = 'image', $size = 100)
    {
        parent::__construct();

        $this->client = $client;
        $this->index = $index;
        $this->docType = $docType;
        $this->size = $size;
    }

    public function insert($record)
    {
        $record['timestamp'] = (new \Datetime())->format('c');
        return $this->client->index([
            'index' => $this->index,
            'type' => $this->docType,
            'body' => $record,
        ]);
    }

    public function search($record)
    {
        $signature = $record['signature'];
        foreach (['path', 'signature', 'metadata'] as $field) {
            if (isset($record[$field])) {
                unset($record[$field]);
            }
        }

        $should = [];
        foreach ($record as $word => $value) {
            $should[] = ['term' => [$word => $value]];
        }

        $results = $this->client->search([
            'index' => $this->index,
            'type' => $this->docType,
            'body' => [
                'query' => [
                    'bool' => [
                        'should' => $should
                    ]
                ],
                '_source' => ['excludes' => ['simple_word_*']]
            ]
        ]);

        $hits = empty($results['hits']) ? [] : $results['hits']['hits'];

        $signatures = array_map(function ($hit) {
            return $hit['_source']['signature'];
        }, $hits);

        if (count($signatures) === 0) {
            return [];
        }

        $dists = $this->normalizedDistance($signatures, $signature);

        $results = [];
        foreach ($hits as $i => $hit) {
            $results[] = [
                'id' => $hit['_id'],
                'score' => $hit['_score'],
                'metadata' => isset($hit['_source']['_metadata']) ? $hit['_source']['_metadata'] : [],
                'path' => $hit['_source']['path'],
                'dist' => isset($dists[$i]) ? $dists[$i] : 0
            ];
        }

        return array_filter($results, function ($y) {
            return $y['dist'] > $this->distanceCutoff;
        });
    }
}
