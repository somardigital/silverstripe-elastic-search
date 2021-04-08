<?php

namespace Somar\Search\Extension;

use SilverStripe\ORM\DataObject;

trait FluentTrait
{
    /**
     * Get locale this record was originally queried from, or belongs to
     *
     * Copied from Fluent extension as it is not accessible otherwise.
     *
     * @return Locale|null
     */
    public function getRecordLocale()
    {
        $localeCode = $this->owner->getSourceQueryParam('Fluent.Locale');
        if ($localeCode) {
            $locale = \TractorCow\Fluent\Model\Locale::getByLocale($localeCode);
            if ($locale) {
                return $locale;
            }
        }
        return \TractorCow\Fluent\Model\Locale::getCurrentLocale();
    }

    /**
     * Update search data with fluent specific data
     *
     * @param [type] $searchData
     * @return void
     */
    public function updateSearchDataFluent(&$searchData)
    {
        if (
            $this->owner->hasExtension('TractorCow\Fluent\Extension\FluentExtension')
            && $locale = $this->getRecordLocale()
        ) {
            $searchData['locale'] = $locale->Locale;
        }
    }

    public function updateSearchIndexAllLocales()
    {
        $this->applyFunctionOnAllLocales('updateSearchIndex');
    }

    public function removeFromIndexAllLocales()
    {
        $this->applyFunctionOnAllLocales('removeFromIndex');
    }

    protected function applyFunctionOnAllLocales(string $functionName)
    {
        $locales = $this->owner->Locales()->toArray();

        array_walk($locales, function ($locale) use ($functionName) {
            \TractorCow\Fluent\State\FluentState::singleton()->withState(
                function (\TractorCow\Fluent\State\FluentState $state) use ($locale, $functionName) {
                    $state->setLocale($locale->Locale);
                    $obj = DataObject::get_by_id($this->owner->ClassName, $this->owner->ID);
                    $obj->$functionName();
                }
            );
        });
    }
}
