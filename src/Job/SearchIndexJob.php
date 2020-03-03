<?php

namespace Somar\Search\Job;

use Somar\Search\ElasticSearchService;
use Page;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\Versioned\Versioned;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;

/**
 * Re-index all content in the site to Elastic Search.
 */
class SearchIndexJob extends AbstractQueuedJob
{
    public function __construct($params = null)
    {
        $pages = $this->pagesToIndex();

        $this->currentStep = 0;
        $this->totalSteps = $pages->count() ?: 1;
        $this->complete = false;
    }

    public function getTitle()
    {
        return 'Search Index';
    }

    public function process()
    {
        ++$this->currentStep;

        $service = new ElasticSearchService();
        $page = $this->pagesToIndex()->limit(1, $this->currentStep - 1)->first();

        if ($page) {
            $searchData = $page->searchData();

            if ($searchData) {
                try {
                    $service->putDocument($searchData);
                    $this->messages[] = 'Indexed page id: ' . $searchData['id'];
                } catch (\Exception $e) {
                    $this->messages[] = 'Exception: ' . $e->getMessage();
                }
            }
        }

        if ($this->currentStep >= $this->totalSteps) {
            $this->messages[] = 'Indexed #' . $this->totalSteps . ' pages';
            $this->isComplete = true;
            $this->requeue();

            // Set last index
            $conf = SiteConfig::current_site_config();
            $conf->LastSearchIndex = date('Y-m-d H:i:s');
            $conf->write();
        }
    }

    private function requeue()
    {
        singleton(QueuedJobService::class)
            ->queueJob(new self(), date('Y-m-d H:i:s', time() + 300));
    }

    private function pagesToIndex()
    {
        $conf = SiteConfig::current_site_config();
        $lastIndex = $conf->LastSearchIndex;

        $original_stage = Versioned::get_stage();
        Versioned::set_stage(Versioned::LIVE);

        $pages = Page::get();

        if ($lastIndex) {
            return $pages->filter([
                'LastEdited:GreaterThan' => $lastIndex,
            ]);
        }

        Versioned::set_stage($original_stage);

        return $pages;
    }
}
