<?php

namespace Somar\Search\Extension;

use SilverStripe\ORM\DataExtension;

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
        $page = $this->owner->getPage();
        $element = $this->owner;

        // Workaround to detect if this Element has changes that need published
        if (!$element->isLiveVersion() && $element->isModifiedOnDraft()) {
            $page->putDocument();
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
}
