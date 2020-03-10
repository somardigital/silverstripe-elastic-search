<?php

namespace Somar\Search\Control;

use PageController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\ORM\PaginatedList;
use SilverStripe\View\ArrayData;
use Somar\Search\ElasticSearchService;

class SearchPageController extends PageController
{
    private static $allowed_actions = [
        'index',
    ];

    /**
     * @param null $action
     *
     * @return string
     */
    public function Link($action = null)
    {
        return Controller::join_links(
            Director::baseURL(),
            'search',
            $action
        );
    }

    /**
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    public function index(HTTPRequest $request)
    {
        $query = $request->getVar('search') ?? null;
        $results = [];

        if (!empty($query)) {
            $data = $this->getData($query);
            $results = PaginatedList::create($data, $request)->setPageLength(5);
        }

        return $this->customise([
            'Title' => 'Search Results',
            'Results' => $results,
            'Query' => $query,
        ])->renderWith([
            'Somar\\Search\\Layout\\SearchPage',
            'Page',
        ]);
    }

    protected function getData($term)
    {
        $service = new ElasticSearchService();
        $results = $service->searchDocuments([
            'term' => $term,
        ]);

        $data = new ArrayList();

        foreach ($results['hits']['hits'] as $result) {
            $resultData = $result['_source'];

            switch ($resultData['type']) {
                default:
                    $type = 'Page';
                    $summary = DBText::create()
                        ->setValue(str_replace(["\n", "\t"], '', $resultData['content']))
                        ->Summary(80);
            }

            $data->push(ArrayData::create([
                'Title' => $resultData['title'],
                'Summary' => $summary,
                'Link' => $resultData['url'],
                'Type' => $type,
            ]));
        }

        return $data;
    }
}
