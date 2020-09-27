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
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Parsers\ShortcodeParser;
use Somar\Search\ElasticSearchService;
use Somar\Search\Log\SearchLogger;

/**
 * Allow a DataObject to be indexed in Elastic.
 */
class SearchableDataObjectExtension extends DataExtension
{
    private static $db = [
        'LastIndexed' => 'Datetime',
        'GUID' => 'Varchar(40)',
        'Keywords' => 'Varchar(255)'
    ];

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName([
            'GUID', 'LastIndexed'
        ]);

        if ($this->isIndexed()) {
            $fields->addFieldsToTab(
                'Root.Main',
                [
                    TextField::create('Keywords')
                        ->setRightTitle('Use this field to affect the site search results')
                ],
                'MetaDescription'
            );
        }
    }

    public function updateSettingsFields(FieldList $fields)
    {
        if ($this->isIndexed()) {
            $fields->insertAfter(
                'Visibility',
                DatetimeField::create('LastIndexed')->setReadonly(true)
            );
        }
    }

    /**
     * Re-index this DataObject in Elastic
     *
     * @return void
     */
    public function updateSearchIndex()
    {
        if (!$this->owner->GUID) {
            $this->owner->assignGUID();
        }

        try {
            $service = new ElasticSearchService();
            $service->putDocument($this->owner->GUID, $this->owner->searchData());

            // Update LastIndexed timestamp
            $table = DataObject::getSchema()->tableName(is_a($this->owner, Page::class) ? Page::class : $this->owner->ClassName);

            SQLUpdate::create($table, ['LastIndexed' => DBDatetime::now()->Rfc2822()], ['ID' => $this->owner->ID])->execute();

            if ($this->owner->has_extension(Versioned::class)) {
                SQLUpdate::create("${table}_Live", ['LastIndexed' => DBDatetime::now()->Rfc2822()], ['ID' => $this->owner->ID])->execute();
            }
        } catch (\Exception $e) {
            $this->logger()->error("Unable to re-index object. Index {$service->getIndexName()}, ID: {$this->owner->ID}, Title: {$this->owner->Title}, {$e->getMessage()}");
        }
    }

    public function removeFromIndex()
    {

        try {
            $service = new ElasticSearchService();
            $service->removeDocument($this->owner->GUID);
        } catch (\Exception $e) {
            $this->logger()->error("Unable to remove record from elastic index {$service->getIndexName()} onDelete. ID: {$this->owner->ID}, Title: {$this->owner->Title}");
            $this->logger()->error("Please remove from index {$service->getIndexName()} to avoid returning outdated search results. GUID: {$this->owner->GUID}");
        }
    }

    /**
     * Flattened representation of DataObject content to push to Elastic.
     */
    public function searchData()
    {
        $searchData = [
            'object_id' => $this->owner->ID,
            'title' => $this->owner->Title,
            'content' => $this->owner->getPlainContent(),
            'keywords' => $this->owner->Keywords,
            'type' => $this->owner->ClassName,
            'thumbnail_url' => $this->owner->Thumbnail ? $this->owner->Thumbnail()->Link() : null,
            'sort_date' => date(\DateTime::ISO8601, strtotime($this->owner->LastEdited)),
            'last_edited' => date(\DateTime::ISO8601, strtotime($this->owner->LastEdited)),
            'last_indexed' => date(\DateTime::ISO8601, strtotime(DBDatetime::now()->Rfc2822())),
        ];

        if (method_exists($this->owner, 'Link')) {
            $searchData['url'] = str_replace(['?stage=Stage', '?stage=Live'], '', $this->owner->Link());
        }

        if (method_exists($this->owner, 'updateSearchData')) {
            $searchData = $this->owner->updateSearchData($searchData);
        }

        $this->owner->extend('updateSearchData', $searchData);


        return $searchData;
    }

    public function getPlainContent(): string
    {
        ShortcodeParser::config()->set('RenderSearchableContentOnly', true);

        if ($this->owner->hasExtension('DNADesign\Elemental\Extensions\ElementalPageExtension')) {
            $content = '';
            foreach ($this->owner->ElementalArea->Elements() as $element) {
                if ($element->isSerachable()) {
                    $content .= strip_tags($element->forTemplate());
                }
            }

            // Strip line breaks from elemental markup
            $content = str_replace("\n", " ", $content);
            // Decode HTML entities back to plain text
            return trim(Convert::xml2raw($content));
        } else {
            return DBField::create_field('HTMLText', $this->owner->Content)->Plain();
        }
    }

    /**
     * Sets GUID without triggering write hooks
     *
     * @return string assigned GUID
     */
    public function assignGUID()
    {
        if (empty($this->owner->GUID)) {
            $this->owner->GUID = Uuid::uuid4()->toString();

            $data = ['GUID' => $this->owner->GUID];
            $where = ['ID' => $this->owner->ID];

            $table = DataObject::getSchema()->tableName(is_a($this->owner, Page::class) ? Page::class : $this->owner->ClassName);
            SQLUpdate::create($table, $data, $where)->execute();

            if ($this->owner->has_extension(Versioned::class)) {
                SQLUpdate::create("${table}_Live", $data, $where)->execute();
            }
        }
        return $this->owner->GUID;
    }

    /**
     * Generate an ID for elastic.
     */
    public function onBeforeWrite()
    {
        if (empty($this->owner->GUID)) {
            $guid = Uuid::uuid4()->toString();
            $this->owner->GUID = $guid;
        }
    }

    public function onAfterWrite()
    {
        if ($this->isIndexed() && false !== $this->owner->config()->update_index_on_save) {
            if (!$this->owner->has_extension(Versioned::class)) {
                $this->updateSearchIndex();
            }
        }
    }


    public function onAfterPublish()
    {
        if ($this->isIndexed() && false !== $this->owner->config()->update_index_on_save) {
            $this->updateSearchIndex();
        }
    }

    public function onAfterUnpublish()
    {
        // Skip if already unpublished from a delete() call
        if (!$this->owner->isPublished()) {
            return false;
        }

        if ($this->isIndexed()) {
            $this->removeFromIndex();
        }
    }

    /**
     * Remove this object from elastic index.
     */
    public function onAfterDelete()
    {
        parent::onAfterDelete();

        if ($this->isIndexed()) {
            $this->removeFromIndex();
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
        $this->owner->LastEdited = DBDatetime::now()->Rfc2822();

        $table = DataObject::getSchema()->tableName(is_a($this->owner, SiteTree::class) ? SiteTree::class : $this->owner->ClassName);
        $data = ['LastEdited' => $this->owner->LastEdited];
        $where = ['ID' => $this->owner->ID];

        SQLUpdate::create($table, $data, $where)->execute();
        if ($this->owner->has_extension(Versioned::class)) {
            SQLUpdate::create("${table}_Live", $data, $where)->execute();
        }
    }

    public function isIndexed()
    {
        return !($this->owner->config()->disable_indexing || $this->owner->DisableIndexing);
    }
}
