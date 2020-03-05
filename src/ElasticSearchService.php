<?php

namespace Somar\Search;

use SilverStripe\Core\Environment;
use Elasticsearch\ClientBuilder;

class ElasticSearchService
{
    private $client;
    private $index;

    public function __construct(bool $readOnly = false)
    {
        if ($readOnly) {
            $apiID = Environment::getEnv('ELASTIC_READONLY_API_ID');
            $apiKey = Environment::getEnv('ELASTIC_READONLY_API_KEY');
        } else {
            $apiID = Environment::getEnv('ELASTIC_WRITABLE_API_ID');
            $apiKey = Environment::getEnv('ELASTIC_WRITABLE_API_KEY');
        }

        $cloudID = Environment::getEnv('ELASTIC_CLOUD_ID');
        $index = Environment::getEnv('ELASTIC_INDEX');

        if (empty($cloudID) || empty($index) || empty($apiID) || empty($apiKey)) {
            throw new \RuntimeException('Please set elastic cloudID, index, apiID and apiKey in .env file');
        }

        $this->index = $index;

        $this->client = ClientBuilder::create()
            ->setElasticCloudId($cloudID)
            ->setApiKey($apiID, $apiKey)
            ->build();
    }

    public function getIndexName(): string
    {
        return $this->index;
    }

    /**
     * Creates an index if it doesn't exist
     *
     * @return boolean successfully created
     */
    public function createIndex(): bool
    {
        $params = [
            'index' => $this->index
        ];

        $exists = $this->client->indices()->exists($params);
        if (!$exists) {
            $this->client->indices()->create($params);
            return true;
        }

        return false;
    }

    public function setIndexMappings()
    {
        return $this->client->indices()->putMapping([
            'index' => $this->index,
            'body' => [
                '_source' => [
                    'enabled' => true,
                ],
                'properties' => [
                    'id' => [
                        'type' => 'text',
                        'store' => true,
                    ],
                    'title' => [
                        'type' => 'text',
                        'analyzer' => 'standard',
                    ],
                    'content' => [
                        'type' => 'text',
                        'analyzer' => 'standard',
                        'store' => true,
                    ],
                    'url' => [
                        'type' => 'text',
                        'store' => true,
                    ],
                    'type' => [
                        'type' => 'keyword',
                        'store' => true,
                    ],
                    'created' => [
                        'type' => 'date',
                        'store' => true,
                    ],
                    'last_edited' => [
                        'type' => 'date',
                        'store' => true,
                    ],
                    'code' => [
                        'type' => 'text',
                        'store' => true,
                    ],
                    'mode' => [
                        'type' => 'keyword',
                        'store' => true,
                    ],
                    'severity' => [
                        'type' => 'keyword',
                        'store' => true,
                    ],
                ],
            ],
        ]);
    }

    public function putDocument($document)
    {
        return $this->client->index([
            'index' => $this->index,
            'id' => $document['guid'],
            'body' => $document,
        ]);
    }

    public function removeDocument($id)
    {
        return $this->client->delete([
            'index' => $this->index,
            'id' => $id,
        ]);
    }

    public function searchDocuments(array $query)
    {
        return $this->client->search([
            'index' => $this->index,
            'explain' => isset($query['explain']) ? $query['explain'] : false,
            'body' => [
                'query' => [
                    'bool' => [
                        'must' => [
                            'multi_match' => [
                                'query' => $query['term'],
                                'type' => 'most_fields',
                                'fuzziness' => 'AUTO:3,6',
                                'fields' => [
                                    'title',
                                    'content',
                                    'code',
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
