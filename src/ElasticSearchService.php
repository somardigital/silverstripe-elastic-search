<?php

namespace Somar\Search;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Environment;
use Elasticsearch\ClientBuilder;
use Exception;
use SilverStripe\Control\Director;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injector;
use Somar\Search\Log\SearchLogger;

class ElasticSearchService
{
    use Configurable;
    use Extensible;

    private static $mappingProperties = [];

    private $client;
    private $index;

    public function __construct()
    {
        $apiID = Environment::getEnv('ELASTIC_API_ID');
        $apiKey = Environment::getEnv('ELASTIC_API_KEY');

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

    public function createAttachmentPipeline()
    {
        $params = [
            'id' => 'attachment',
            'body' => [
                'description' => 'Extract attachment information',
                'processors' => [
                    [
                        'attachment' => [
                            'field' => 'attachment',
                            'properties' => ["content", "content_length", "content_type"],
                            'indexed_chars' => -1,
                        ]
                    ]
                ]
            ]
        ];
        return $this->client->ingest()->putPipeline($params);
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

    /**
     * @param string $indexName
     * @return bool
     */
    public function getIndex(string $indexName)
    {
        return $this->client->getIndex($indexName);
    }

    public function setIndexMappings()
    {
        return $this->client->indices()->putMapping([
            'index' => $this->index,
            'body' => [
                '_source' => [
                    'enabled' => true,
                ],
                'properties' => $this->config()->mappingProperties,
            ],
        ]);
    }

    public function putDocuments($documents)
    {
        $body = [];
        foreach ($documents as $doc) {
            $body[] = [
                'index' => ['_index' => $this->index, '_id' => $doc['id']]
            ];
            $body[] = $doc['searchData'];
        }

        $params = [
            'index' => $this->index,
            'body' => $body,
        ];

        if (!empty(array_column(array_column($documents, 'searchData'), 'attachment'))) {
            $params['pipeline'] = 'attachment';
        }

        return $this->client->bulk($params);
    }

    public function putDocument($id, $document)
    {
        $params = [
            'index' => $this->index,
            'id' => $id,
            'body' => $document,
        ];

        if (!empty($document['attachment'])) {
            $params['pipeline'] = 'attachment';
        }

        return $this->client->index($params);
    }

    public function removeDocument($id)
    {
        return $this->client->delete([
            'index' => $this->index,
            'id' => $id,
        ]);
    }

    public function searchDocuments(array $params)
    {
        $body = [
            '_source' => [
                'excludes' => ['attachment']
            ]
        ];

        if (!empty($params['term'])) {
            $body['query'] =        [
                'bool' => [
                    'must' => [
                        'multi_match' => [
                            'query' => $params['term'],
                            'type' => 'most_fields',
                            'fuzziness' => 'AUTO:3,6',
                            'fields' => $this->config()->searchFields,
                        ],
                    ],
                ],
            ];
        }

        if (!empty($params['filter'])) {
            foreach ($params['filter'] as $field => $value) {
                if (!empty($value)) {
                    if (strpos($field, ':not') === false) {
                        $body['query']['bool']['filter'][] = ['terms' => [$field => $value]];
                    } else {
                        $body['query']['bool']['must_not'][] = ['terms' => [rtrim($field, ':not') => $value]];
                    }
                }
            }
        }

        if (!empty($params['sort'])) {
            foreach ($params['sort'] as $field => $direction) {
                $body['sort'][] = [$field => ['order' => $direction]];
            }
        }

        if (!empty($params['range'])) {
            foreach ($params['range'] as $field => $values) {
                $range = [];
                if (!empty($values['from'])) {
                    $range['range'][$field]['gte'] = $values['from'];
                }
                if (!empty($values['to'])) {
                    $range['range'][$field]['lte'] = $values['to'];
                }

                $body['query']['bool']['filter'][] = $range;
            }
        }

        $this->extend('updateSearchRequestBody', $body);

        try {
            $results = $this->client->search([
                'index' => $this->index,
                'explain' => isset($params['explain']) ? $params['explain'] : false,
                'body' => $body
            ]);
        } catch (Exception $e) {
            Injector::inst()->get(SearchLogger::class)->error($e->getMessage());
            $results = Director::isLive() ? ['error' => true] : json_decode($e->getMessage(), true);
        }

        return $results;
    }
}
