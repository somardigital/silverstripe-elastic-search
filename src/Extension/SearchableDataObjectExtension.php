<?php

namespace Somar\Search\Extension;

use Page;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\DatetimeField;
use SilverStripe\ORM\FieldType\DBDatetime;
use SilverStripe\ORM\FieldType\DBField;

use Ramsey\Uuid\Uuid;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLUpdate;
use SilverStripe\Versioned\Versioned;
use SilverStripe\View\Parsers\ShortcodeParser;
use Somar\Search\ElasticSearchService;
use Somar\Search\Log\SearchLogger;
use Somar\Search\Utils\Helpers;

/**
 * Allow a DataObject to be indexed in Elastic.
 */
class SearchableDataObjectExtension extends DataExtension
{
    use FluentTrait;

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

            $service->putDocument($this->getDocumentID(), $this->owner->searchData());

            // Update LastIndexed timestamp
            $table = DataObject::getSchema()->tableName($this->getAppliedClass());

            SQLUpdate::create($table, ['LastIndexed' => DBDatetime::now()->Rfc2822()], ['ID' => $this->owner->ID])->execute();

            if ($this->owner->has_extension(Versioned::class)) {
                SQLUpdate::create("${table}_Live", ['LastIndexed' => DBDatetime::now()->Rfc2822()], ['ID' => $this->owner->ID])->execute();
            }
        } catch (\Exception $e) {
            $this->logger()->error(
                sprintf(
                    "Unable to re-index object. %s %s %s %s",
                    isset($service) ? "Index {$service->getIndexName()}," : '',
                    "ID: {$this->owner->ID},",
                    "Title: {$this->owner->Title},",
                    $e->getMessage()
                )
            );
        }
    }

    public function removeFromIndex()
    {

        try {
            $service = new ElasticSearchService();
            $service->removeDocument($this->getDocumentID());
        } catch (\Exception $e) {
            $this->logger()->error("Unable to remove record from elastic index {$service->getIndexName()} onDelete. ID: {$this->owner->ID}, Title: {$this->owner->Title}");
            $this->logger()->error("Please remove from index {$service->getIndexName()} to avoid returning outdated search results. GUID: {$this->getDocumentID()}");
        }
    }

    public function getDocumentID()
    {
        if ($this->owner->hasExtension('TractorCow\Fluent\Extension\FluentExtension')) {
            if ($locale = $this->getRecordLocale()) {
                return "{$this->owner->GUID}-{$locale->Locale}";
            }
        }

        return $this->owner->GUID;
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

        $this->updateSearchDataFluent($searchData);

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
            return Helpers::get_blocks_plain_content($this->owner->getSearchableBlocks()->toArray());
        } else {
            return DBField::create_field('HTMLText', $this->owner->Content)->Plain();
        }
    }

    public function getSearchableBlocks(): ArrayList
    {
        return $this->owner->ElementalArea->Elements()->filterByCallback(fn ($block) => $block->isSearchable());
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

            $table = DataObject::getSchema()->tableName($this->getAppliedClass());
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

        if ($this->owner->hasField('ShowInSearch') && $this->owner->isChanged('ShowInSearch') && !$this->owner->ShowInSearch) {
            if (!$this->owner->has_extension(Versioned::class)) {
                $this->removeFromIndex();
            }
        }
    }


    public function onAfterPublish()
    {
        if ($this->isIndexed() && false !== $this->owner->config()->update_index_on_save) {
            $this->updateSearchIndex();
        }

        if ($this->owner->hasField('ShowInSearch') && $this->owner->isChanged('ShowInSearch') && !$this->owner->ShowInSearch) {
            $this->removeFromIndex();
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

        $baseClass = DataObject::getSchema()->baseDataClass($this->owner->ClassName);

        $table = DataObject::getSchema()->tableName($baseClass);
        $data = ['LastEdited' => $this->owner->LastEdited];
        $where = ['ID' => $this->owner->ID];

        SQLUpdate::create($table, $data, $where)->execute();
        if ($this->owner->has_extension(Versioned::class)) {
            SQLUpdate::create("${table}_Live", $data, $where)->execute();
        }
    }

    public function isIndexed()
    {
        return !($this->owner->config()->disable_indexing || $this->owner->DisableIndexing || ($this->owner->hasField('ShowInSearch') && !$this->owner->ShowInSearch));
    }

    /**
     * Get a class that this extension was applied to
     *
     * @return string
     */
    protected function getAppliedClass(): string
    {
        // Get starting class - with a shortcut for Page class
        $class = is_a($this->owner, Page::class) ? Page::class : $this->owner->ClassName;

        // Find Class that has the extensions applied
        $isDirectExtension = fn ($c) => in_array(static::class, Config::forClass($c)->uninherited('extensions'));

        // $class might eventually be false when there is no more parent classes
        while ($class && !$isDirectExtension($class)) {
            $class = get_parent_class($class);
        }

        return $class ?? '';
    }
}
