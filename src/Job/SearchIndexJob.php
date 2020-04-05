<?php

namespace Somar\Search\Job;

use Exception;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Symbiote\QueuedJobs\Services\QueuedJobService;
use Page;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Versioned\Versioned;
use Somar\Search\ElasticSearchService;


/**
 * Re-index all content in the site to Elastic Search.
 */
class SearchIndexJob extends AbstractQueuedJob
{
    use Configurable;

    /**
     * How many records are processed each step of the job
     * @var int
     */
    private static $limit = 500;

    public function __construct($params = null)
    {
        $pages = $this->pagesToIndex();
        $limit = $this->config()->get('limit');
        $count = $pages->count();

        $this->currentStep = 0;
        $this->totalSteps = $count ? ceil($count / $limit) : 1;
        $this->complete = false;
    }

    public function getTitle()
    {
        return 'Bulk Search Index';
    }

    public function process()
    {
        ++$this->currentStep;

        $this->update($this->config()->get('limit'));

        if ($this->currentStep >= $this->totalSteps) {
            $this->messages[] = 'Done.';
            $this->isComplete = true;
            $this->requeue();
        }
    }

    private function update($limit)
    {
        $service = new ElasticSearchService();
        $pages = $this->pagesToIndex()->limit($limit, ($this->currentStep - 1) * $limit);
        $documents = [];

        if ($pages->count()) {
            foreach ($pages as $page) {
                if (!$page->isIndexed()) {
                    continue;
                }

                $searchData = $page->searchData();


                if ($searchData) {
                    $documents[] = ['id' => $page->GUID, 'searchData' => $searchData];
                }
            }
        }

        if (!empty($documents)) {
            try {
                $result = $service->putDocuments($documents);

                if ($result['errors']) {
                    $error = $result['items'][0]['index']['error'];
                    throw new Exception(implode(': ', $error));
                }

                $this->messages[] = sprintf(
                    'Indexed %s pages',
                    count($documents)
                );
            } catch (\Exception $e) {
                $this->messages[] = 'Exception: ' . $e->getMessage();
                throw $e;
            }
        } else {
            $this->messages[] = "No documents to index";
        }
    }

    private function requeue()
    {
        singleton(QueuedJobService::class)
            ->queueJob(new self(), date('Y-m-d H:i:s', time() + 300));
    }

    private function pagesToIndex()
    {
        $original_stage = Versioned::get_stage();
        Versioned::set_stage(Versioned::LIVE);

        $pages = Page::get();

        Versioned::set_stage($original_stage);

        return $pages;
    }
}
