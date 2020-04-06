<?php

namespace Somar\Search\PageType;

use Page;
use Somar\Search\Control\SearchPageController;

class SearchPage extends Page
{
    private static $table_name = 'SearchPage';

    private static $icon_class = 'font-icon-p-search';
    private static $disable_indexing = true;

    public function getControllerName()
    {
        return SearchPageController::class;
    }
}
