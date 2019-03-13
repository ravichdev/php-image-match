<?php

namespace ImageMatch\Database;

use Elasticsearch\Client;

class ElasticSearchDatabase extends SignatureDatabaseBase
{
	private $client;
	private $index;
	private $docType;
	private $size;

	public function __construct(Client $client, $index='images', $docType='image', $size=100)
	{
		parent::__construct();

		$this->client = $client;
		$this->index = $index;
		$this->docType = $docType;
		$this->size = $size;
	}

	public function insert($record)
	{
		$record['timestamp'] = time();
		return $this->client->index([
			'index' => $this->index,
			'type' => $this->docType,
			'body' => $record,
		]);
	}

	public function search($record)
	{
		foreach (['path', 'signature', 'metadata'] as $field) {
			if (isset($record[$field])) {
				unset($record[$field]);
			}
		}

		$should = [];
		foreach ($record as $word => $value) {
			$should[] = ['term' => [$word => $value]];
		}

		return $this->client->search([
			'index' => $this->index,
			'type' => $this->docType,
			'body' => [
				'query' => [
					'bool' => [
						'should' => $should
					]
				]
			]
		]);

	}
}