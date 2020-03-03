<?php

namespace Somar\Search\Extension;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\ORM\DataExtension;

/**
 * Allow a Page to be indexed in Elastic.
 */
class SearchablePageExtension extends DataExtension
{
    private static $db = [
        'GUID' => 'Varchar(40)',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName([
            'GUID',
        ]);
    }

    /**
     * Generate an ID for elastic.
     */
    public function onBeforeWrite()
    {
        parent::onBeforeWrite();

        if (empty($this->owner->GUID)) {
            $uuid = Uuid::uuid4();
            $this->owner->GUID = $uuid->toString();
        }
    }

    /**
     * Index this page's content.
     */
    public function onAfterPublish()
    {
        $searchData = $this->owner->searchData();

        if ($searchData) {
            try {
                $service = new ElasticSearchService();
                $service->putDocument($searchData);
            } catch (\Exception $e) {
            }
        }
    }

    /**
     * Remove this page from elastic index.
     */
    public function onBeforeDelete()
    {
        parent::onBeforeDelete();

        try {
            $service = new ElasticSearchService();
            $service->removeDocument($this->owner->GUID);
        } catch (\Exception $e) {
        }
    }

    /**
     * Flattened representation of Page content to push to Elastic.
     */
    public function searchData()
    {
        // cannot index a document without a GUID
        // write this Page to generate a GUID
        if (empty($this->owner->GUID)) {
            $this->logger()->error("Attempted to index a page, but it had no GUID. Page ID: {$this->owner->ID}, Title: {$this->owner->Title}");

            return null;
        }

        return [
            'guid' => $this->owner->GUID,
            'id' => $this->owner->ID,
            'title' => $this->owner->Title,
            'content' => $this->owner->getElementsForSearch(),
            'url' => $this->owner->Link(),
            'type' => $this->owner->ClassName,
            'created' => date(\DateTime::ISO8601, strtotime($this->owner->Created)),
            'last_edited' => date(\DateTime::ISO8601, strtotime($this->owner->LastEdited)),
        ];
    }

    /**
     * Get logger singleton.
     */
    private function logger()
    {
        return Injector::inst()->get(LoggerInterface::class);
    }
}
