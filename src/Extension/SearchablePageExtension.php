<?php

namespace Somar\Search\Extension;

use Page;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\Core\Convert;

use Ramsey\Uuid\Uuid;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLUpdate;
use Somar\Search\ElasticSearchService;
use Somar\Search\Log\SearchLogger;

/**
 * Allow a Page to be indexed in Elastic.
 */
class SearchablePageExtension extends DataExtension
{
    private static $db = [
        "LastIndexed" => "Datetime",
        'GUID' => 'Varchar(40)',
        'Keywords' => 'Varchar(255)'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName([
            'GUID',
        ]);

        $fields->addFieldToTab(
            'Root.Main',
            TextField::create('Keywords')
                ->setRightTitle('Use this field to affect the site search results'),
            'MetaDescription'
        );
    }

    public function updateSettingsFields(FieldList $fields)
    {
        $fields->insertAfter(
            'Visibility',
            DatetimeField::create('LastIndexed')->setReadonly(true)
        );
    }

    /**
     * Re-index this page in Elastic
     *
     * @return void
     */
    public function updateSearchIndex()
    {
        if ($this->owner->isIndexed() && $searchData = $this->owner->searchData()) {
            try {
                $service = new ElasticSearchService();
                $service->putDocument($this->owner->GUID, $searchData);

                // Update LastIndexed timestamp
                $table = DataObject::getSchema()->tableName(Page::class);

                SQLUpdate::create($table, ['LastIndexed' => DBDatetime::now()->Rfc2822()], ['ID' => $this->owner->ID])->execute();
                SQLUpdate::create("${table}_Live", ['LastIndexed' => DBDatetime::now()->Rfc2822()], ['ID' => $this->owner->ID])->execute();
            } catch (\Exception $e) {
                $this->logger()->error("Unable to re-index page onPublish. Index {$service->getIndexName()}, Page ID: {$this->owner->ID}, Title: {$this->owner->Title}, {$e->getMessage()}");
            }
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
            'page_id' => $this->owner->ID,
            'title' => $this->owner->Title,
            'content' => $this->owner->getPlainContent(),
            'keywords' => $this->owner->Keywords,
            'url' => str_replace(['?stage=Stage', '?stage=Live'], '', $this->owner->Link()),
            'type' => $this->owner->ClassName,
            'last_edited' => date(\DateTime::ISO8601, strtotime($this->owner->LastEdited)),
            'last_indexed' => date(\DateTime::ISO8601, strtotime(DBDatetime::now()->Rfc2822())),
        ];
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
     * Re-index this page's content if any top-level fields on the Page have changed
     */
    public function onAfterPublish()
    {
        $this->updateSearchIndex();
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
        return Injector::inst()->get(SearchLogger::class);
    }

    /**
     * Updates LastEdited to current timestamp using SQLUpdate
     *
     * @return void
     */
    public function updateLastEdited()
    {
        $this->owner->LastEdit = DBDatetime::now()->Rfc2822();
        $table = DataObject::getSchema()->tableName(SiteTree::class);

        $data = ['LastEdited' => $this->owner->LastEdit];
        $where = ['ID' => $this->owner->ID];

        SQLUpdate::create($table, $data, $where)->execute();
        SQLUpdate::create("${table}_Live", $data, $where)->execute();
    }

    public function isIndexed()
    {
        return !($this->owner->config()->get('disable_indexing') || $this->owner->DisableIndexing);
    }
}
