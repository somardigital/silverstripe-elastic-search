<?php

namespace Somar\Search\Extension;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Versioned\Versioned;

/**
 * Allow a Page to detect when Elemental content has changed
 */
class SearchableElementExtension extends DataExtension
{
    /**
     * If Element fields have changed, then re-index Page content
     *
     * @return void
     */
    public function onAfterWrite()
    {
        // Nothing, only push to index if published
    }

    public function onAfterDelete()
    {
        $element = $this->owner;

        if ($element->isSearchable() && $parentPage = $this->getParentPage()) {
            $parentPage->updateLastEdited();
            $parentPage->updateSearchIndex();
        }
    }

    /**
     *  This is working now: https://github.com/silverstripe/silverstripe-elemental/issues/779
     */
    public function onAfterPublish()
    {
        $element = $this->owner;

        if ($element->isSearchable() && $parentPage = $this->getParentPage()) {
            $parentPage->updateLastEdited();
            $parentPage->updateSearchIndex();
        }
    }

    public function onAfterUnpublish()
    {
        $element = $this->owner;

        if ($element->isSearchable() && $parentPage = $this->getParentPage()) {
            $parentPage->updateLastEdited();
            $parentPage->updateSearchIndex();
        }
    }

    /**
     * To fix incorrect behaviour when nested elements
     *
     */
    public function getParentPage()
    {
        // Allow to overwrite with custom function in element
        if (method_exists($this->owner, 'getParentPage')) {
            return $this->owner->getParentPage();
        }

        // Change stage to draft in case of unpublished parent element
        $originalStage = Versioned::get_stage();
        Versioned::set_stage(Versioned::DRAFT);

        $parent = $this->owner->getPage();

        while ($parent && !$parent instanceof SiteTree) {
            $parent = $parent->getPage();
        }

        Versioned::set_stage($originalStage);

        return $parent;
    }

    public function isSearchable()
    {
        if (($this->owner->config()->not_searchable || $this->owner->NotSearchable)) {
            return false;
        }
        return true;
    }
}
