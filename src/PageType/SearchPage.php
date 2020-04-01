<?php

namespace GWRC\Website;

use Page;
use Somar\Search\Control\SearchPageController;

class SearchPage extends Page
{
    private static $table_name = 'SearchPage';

    private static $icon_class = 'font-icon-p-search';

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $fields->removeByName([
            'TeReoTitle',
            'BannerTheme',
            'PageUtils',
            'UpdateReminder',
            'ElementalArea'
        ]);

        return $fields;
    }

    public function getControllerName()
    {
        return SearchPageController::class;
    }
}
