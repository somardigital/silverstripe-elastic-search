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
    public function onBeforeWrite()
    {
        $element = $this->owner;
        // Workaround to detect if this Element has changes that need published
        if (!$this->owner->isLiveVersion() && $this->owner->isModifiedOnDraft()) {
            $this->getParentPage($this->owner)->putDocument();
        }
    }

    /**
     * TODO: use onBeforePublish to trigger re-index when the below bug is fixed.
     * BUG: This hook is never called. https://github.com/dnadesign/silverstripe-elemental/issues/779
     *
     * @return void
     */
    public function onBeforePublish()
    {
    }

    /**
     * To fix incorrect behavior when nested elements
     *
     */
    private function getParentPage($element)
    {
        // Change stage to draft in case of unpublished parent element
        $originalStage = Versioned::get_stage();
        Versioned::set_stage(Versioned::DRAFT);

        $parent = $element->getPage();
        while (!$parent instanceof SiteTree) {
            $parent = $parent->getPage();
        }

        Versioned::set_stage($originalStage);

        return $parent;
    }
}
