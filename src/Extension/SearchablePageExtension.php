<?php

namespace Somar\Search\Extension;

use SilverStripe\ORM\DataExtension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Core\Convert;

use Psr\Log\LoggerInterface;
use Ramsey\Uuid\Uuid;
use Somar\Search\ElasticSearchService;

/**
 * Allow a Page to be indexed in Elastic.
 */
class SearchablePageExtension extends DataExtension
{
    private static $db = [
        "LastIndexed" => "Datetime",
        'GUID' => 'Varchar(40)',
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName([
            'GUID',
        ]);
    }

    public function updateSettingsFields(FieldList $fields)
    {
        $fields->insertAfter(
            'Visibility',
            DatetimeField::create('LastIndexed')->setReadonly(true)
        );
    }

    public function getPlainContent(): string
    {
        if ($this->owner->hasExtension('DNADesign\Elemental\Extensions\ElementalPageExtension')) {
            $content = $this->owner->getElementsForSearch();
            // Strip line breaks from elemental markup
            $content = str_replace("\n", " ", $content);
            // Decode HTML entities back to plain text
            return trim(Convert::xml2raw($content));
        } else {
            return DBField::create_field('HTMLText', $this->owner->Content)->Plain();
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
            'content' => $this->owner->getPlainContent(),
            'url' => $this->owner->Link(),
            'type' => $this->owner->ClassName,
            'created' => date(\DateTime::ISO8601, strtotime($this->owner->Created)),
            'last_indexed' => date(\DateTime::ISO8601, strtotime($this->owner->LastIndexed)),
        ];
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
     * BUG: This hook is never called. https://github.com/dnadesign/silverstripe-elemental/issues/779
     * Index this page's content.
     */
    public function onBeforePublish()
    {
        $searchData = $this->owner->searchData();

        if ($searchData) {
            try {
                $service = new ElasticSearchService();
                $service->putDocument($searchData);
                $this->logger()->error("did the thing");
            } catch (\Exception $e) {
                $this->logger()->error("Unable to re-index page onPublish. Index {$service->getIndexName()}, Page ID: {$this->owner->ID}, Title: {$this->owner->Title}");
            }
        }

        $this->owner->LastIndexed = DBDatetime::now()->Rfc2822();
    }

    /**
     * Remove this page from elastic index.
     */
    public function onAfterDelete()
    {
        parent::onAfterDelete();

        try {
            $service = new ElasticSearchService();
            $service->removeDocument($this->owner->GUID);
        } catch (\Exception $e) {
            $this->logger()->error("Unable to remove page from elastic index {$service->getIndexName()} onDelete. Page ID: {$this->owner->ID}, Title: {$this->owner->Title}");
            $this->logger()->error("Please remove from index {$service->getIndexName()} to avoid returning outdated search results. Page GUID: {$this->owner->GUID}");
        }
    }

    /**
     * Get logger singleton.
     */
    private function logger()
    {
        return Injector::inst()->get(LoggerInterface::class);
    }
}
