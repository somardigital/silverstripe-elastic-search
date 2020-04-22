<?php

namespace Somar\Search\Tests;

use SilverStripe\Dev\SapphireTest;
use Elasticsearch\Common\Exceptions\Forbidden403Exception;
use Page;

use Somar\Search\ElasticSearchService;

/**
 * @internal
 * @covers \ElasticSearchService
 */
class ElasticSearchServiceTest extends SapphireTest
{
    public function testCanSearch()
    {
        // GIVEN an elastic client
        $elastic = new ElasticSearchService();

        // AND some known test data
        // TODO: setup/teardown test data

        // WHEN we search
        $term = 'test page';
        $results = $elastic->searchDocuments([
            'term' => $term,
        ]);

        // THEN we get a result
        $this->assertEquals(1, $results['_shards']['successful']);
    }

    public function testCanIndexDocument()
    {
        // GIVEN an elastic client
        $elastic = new ElasticSearchService();

        // AND a document to index
        $document = $this->aTestPage();

        // WHEN we index
        $result = $elastic->putDocument($document);

        // THEN the document is updated
        $this->assertContains($result['result'], ['created', 'updated']);
    }

    // Helpers

    private function aTestPage()
    {
        return [
            'guid' => 'test-guid',
            'title' => 'test page',
            'content' => 'test content',
            'url' => '/',
            'type' => Page::class,
        ];
    }
}
