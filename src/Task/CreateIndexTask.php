<?php

namespace Somar\Search\Task;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Somar\Search\ElasticSearchService;

class CreateIndexTask extends BuildTask
{
    protected $title = 'Create Elasticsearch index';
    protected $description = "Creates index if it doesn't exist, and sets mapping config & ingest pipeline";

    public function run($request)
    {
        $service = new ElasticSearchService();

        $index = $service->getIndexName();

        $created = $service->createIndex();
        if ($created) {
            DB::alteration_message("Created index $index");

            DB::alteration_message("Creating attachment pipeline for $index...");
            $service->createAttachmentPipeline();
        } else {
            DB::alteration_message("Index $index already exists");
        }

        DB::alteration_message("Setting mappings on index $index...");
        $service->setIndexMappings();

        DB::alteration_message("Done.");
    }
}
