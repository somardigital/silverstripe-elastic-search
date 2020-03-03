<?php

namespace Somar\Search\Task;

use SilverStripe\Core\Environment;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DB;
use Somar\Search\ElasticSearchService;

class ElasticSearchIndexMapTask extends BuildTask
{
    protected $title = 'Elastic Search Index Map Task';
    protected $description = 'Set index mapping for elastic search index.';

    public function run($request)
    {
        $index = Environment::getEnv('ELASTIC_INDEX');

        DB::alteration_message("Setting mappings on index $index...");

        $service = new ElasticSearchService();
        $service->setIndexMappings();

        DB::alteration_message("Done.");
    }
}
