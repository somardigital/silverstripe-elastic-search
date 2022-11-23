<?php

namespace Somar\Search\Job;

use Exception;
use Symbiote\QueuedJobs\Services\AbstractQueuedJob;
use Page;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\ORM\DataObject;
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

    // index to the records array currently being indexed
    private $currentIndex = 0;

    private $records = [];

    public function __construct($params = null)
    {
        $this->records = $this->recordsToIndex();

        $this->currentStep = 0;
        $this->totalSteps = array_reduce($this->records, function ($sum, $list) {
            return $sum + $list['count'];
        }, 0);
        $this->complete = false;
    }

    public function getTitle()
    {
        return 'Bulk Search Index';
    }

    public function process()
    {
        $this->update($this->config()->limit);

        if ($this->currentStep >= $this->totalSteps) {
            $this->messages[] = 'Done.';
            $this->isComplete = true;
        }
    }

    private function update($limit)
    {
        $service = new ElasticSearchService();

        $indexedTypes = array_filter($this->records, function ($i) {
            return $i < $this->currentIndex;
        }, ARRAY_FILTER_USE_KEY);

        $indexedTypesCount = array_reduce($indexedTypes, function ($sum, $list) {
            return $sum + $list['count'];
        }, 0);

        $records = $this->records[$this->currentIndex]['list']
            ->limit($limit, ($this->currentStep - $indexedTypesCount));
        
        $documents = [];
        $skipped = 0;

        if ($records->count()) {
            foreach ($records as $record) {
                ++$this->currentStep;

                if (!$record->isIndexed()) {
                    $skipped++;
                    continue;
                }

                if (!$record->GUID) {
                    $record->assignGUID();
                }

                $documents[] = [
                    'id' => $record->GUID,  // This doesn't include locale!!!
                    'searchData' => $record->searchData()
                ];
            }
        }

        if ($this->currentStep - $indexedTypesCount == $this->records[$this->currentIndex]['count']) {
            $this->currentIndex++;
        }

        if (!empty($documents)) {
            $service->putDocuments($documents);
        }

        $this->messages[] = sprintf(
            'Indexed %s records of %s, %s records were skipped',
            count($documents),
            $records->dataclass(),
            $skipped
        );
    }

    private function recordsToIndex()
    {
        $records = [];
        $original_stage = Versioned::get_stage();
        Versioned::set_stage(Versioned::LIVE);

        if (DataObject::has_extension(Page::class, 'TractorCow\Fluent\Extension\FluentExtension')) {
            $locales = singleton(Page::class)->Locales()->toArray();

            array_walk($locales, function ($locale) use (&$records) {
                \TractorCow\Fluent\State\FluentState::singleton()->withState(
                    function (\TractorCow\Fluent\State\FluentState $state) use ($locale, &$records) {
                        $state->setLocale($locale->Locale);
                        $records[] = [
                            'list' => Page::get(),
                            'count' => Page::get()->count()
                        ];
                    }
                );
            });
        } else {
            $records[] = [
                'list' => Page::get(),
                'count' => Page::get()->count()
            ];
        }

        if (!empty($this->config()->IndexedClasses)) {
            foreach ($this->config()->IndexedClasses as $class) {
                if (DataObject::has_extension($class, 'TractorCow\Fluent\Extension\FluentExtension')) {
                    $locales = singleton($class)->Locales()->toArray();

                    array_walk($locales, function ($locale) use ($class, &$records) {
                        \TractorCow\Fluent\State\FluentState::singleton()->withState(
                            function (\TractorCow\Fluent\State\FluentState $state) use ($locale, $class, &$records) {
                                $state->setLocale($locale->Locale);
                                $records[] = [
                                    'list' =>  DataObject::get($class),
                                    'count' => DataObject::get($class)->count()
                                ];
                            }
                        );
                    });
                } else {
                    $records[] = [
                        'list' =>  DataObject::get($class),
                        'count' => DataObject::get($class)->count()
                    ];
                }
            }
        }

        Versioned::set_stage($original_stage);

        return $records;
    }
}
