<?php

namespace Somar\Search\Task;

use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use SilverStripe\PolyExecution\PolyOutput;
use Somar\Search\ElasticSearchService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class CreateIndexTask extends BuildTask
{
    protected string $title = 'Create Elasticsearch index';

    protected static string $description = "Creates index if it doesn't exist, and sets mapping config & ingest pipeline";

    private static bool $is_enabled = true;

    protected function execute(InputInterface $input, PolyOutput $output): int
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

        return Command::SUCCESS;
    }
}
