<?php

namespace Somar\Search\Control;

use GWRC\Website\PageType\Event;
use GWRC\Website\PageType\NewsArticle;
use GWRC\Website\PageType\ParkPage;
use Page;
use PageController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Environment;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use Somar\Search\ElasticSearchService;

class SearchPageController extends PageController
{
    private static $allowed_actions = [
        'index',
        'search'
    ];

    /**
     * @return \SilverStripe\ORM\FieldType\DBHTMLText
     */
    public function index(HTTPRequest $request)
    {
        if (!Environment::getEnv('SS_SEARCH_HOT_RELOAD_URL')) {
            Requirements::css('somardesignstudios/silverstripe-elastic-search: client/dist/css/app.css');
            Requirements::javascript('somardesignstudios/silverstripe-elastic-search: client/dist/js/app.js');
            Requirements::javascript('somardesignstudios/silverstripe-elastic-search: client/dist/js/chunk-vendors.js');
        } else {
            Requirements::javascript(Environment::getEnv('SS_SEARCH_HOT_RELOAD_URL') . 'js/app.js');
            Requirements::javascript(Environment::getEnv('SS_SEARCH_HOT_RELOAD_URL') . 'js/chunk-vendors.js');
        }

        Requirements::set_force_js_to_bottom(true);

        return $this->renderWith([
            'Somar\\Search\\SearchPage',
            'Page',
        ]);
    }

    public function search(HTTPRequest $request)
    {
        $requestParams = [
            'term' => $request->getVar('q'),
            'type' => $request->getVar('type'),
            'sort' => $request->getVar('sort'),
            'dateFrom' => $request->getVar('dateFrom'),
            'dateTo' => $request->getVar('dateTo')
        ];

        $params = $this->buildSearchParams($requestParams);

        $data = [
            'results' => $this->getResults($params)
        ];


        return $this->json($data);
    }

    protected function getResults($params)
    {
        $service = new ElasticSearchService();
        $results = $service->searchDocuments($params);

        $data = new ArrayList();

        $types = [
            Event::class => 'event',
            NewsArticle::class => 'news',
            ParkPage::class => 'park'
        ];

        foreach ($results['hits']['hits'] as $result) {
            $resultData = $result['_source'];
            $type = !empty($types[$resultData['type']]) ? $types[$resultData['type']] : 'page';

            switch ($resultData['type']) {


                default:
                    $summary = DBText::create()
                        ->setValue(str_replace(["\n", "\t"], '', $resultData['content']))
                        ->Summary(80);
            }

            $data->push(ArrayData::create([
                'title' => $resultData['title'],
                'summary' => $summary,
                'url' => $resultData['url'],
                'type' => $type,
                'lastEdited' => $resultData['last_edited']
            ]));
        }

        return $data->toNestedArray();
    }


    /**
     * Builds parameters for ElasticSearchService based on search request parameters
     *
     * @param array $vars
     * @return array
     */
    protected function buildSearchParams($requestParams)
    {
        $params = [];

        if (!empty($requestParams['term'])) {
            $params['term'] = $requestParams['term'];
        }

        if ($requestParams['type']) {
            $typeConfig = $this->TypeFilterConfig;

            $params['filter']['type'] = [];
            $params['filter']['type:not'] = [];

            foreach ($requestParams['type'] as $type) {

                if (!empty($typeConfig[$type]['type'])) {
                    $params['filter']['type'] = [...$params['filter']['type'], ...$typeConfig[$type]['type']];
                }

                if (!empty($typeConfig[$type]['type:not'])) {
                    $params['filter']['type:not'] = [...$params['filter']['type:not'], ...$typeConfig[$type]['type:not']];
                }
            }
        }

        if ($requestParams['sort']) {
            $params['sort']['last_edited'] = $requestParams['sort'];
        }

        if ($requestParams['dateFrom']) {
            $params['range']['last_edited']['from'] = $requestParams['dateFrom'];
        }

        if ($requestParams['dateTo']) {
            $params['range']['last_edited']['to'] = $requestParams['dateTo'];
        }

        return $params;
    }

    /**
     * Type filters configuration
     *
     * @return array
     */
    protected function getTypeFilterConfig()
    {
        return [
            'news' => [
                'type' => [NewsArticle::class],
            ],
            'events' => [
                'type' => [Event::class],
            ],
            'content' => [
                'type:not' => [NewsArticle::class, Event::class],
            ],
            'documents' => [ /* TODO: add once document library is implemented*/],
        ];
    }

    /**
     * Search configuration
     *
     * @return string JSON encoded configuration object
     */
    protected function getSearchConfig()
    {
        return json_encode([
            'labels' => [
                'filtersHint' => 'Refine your search results below by selecting popular filters and/or ordering them by date.',
                'noResultsMessage'
            ],
            'filters' => [

                'type' => [
                    'placeholder' => 'Type of content',
                    'options' => [
                        [
                            'name' => 'News',
                            'value' => 'news',
                        ],
                        [
                            'name' => 'Events',
                            'value' => 'events',
                        ],
                        [
                            'name' => 'Content',
                            'value' => 'content',
                        ],

                        [
                            'name' => 'Documents',
                            'value' => 'documents',
                        ],
                        [
                            'name' => 'I want to...',
                            'value' => 'activities',
                        ]
                    ]
                ],

                'date' => [
                    'placeholder' => 'Type of content',
                    'options' => [
                        [
                            'name' => 'Most recent first',
                            'value' => 'desc',
                        ],
                        [
                            'name' => 'Oldest first',
                            'value' => 'asc',
                        ],
                        [
                            'name' => 'Select dates',
                            'value' => 'range',
                        ]
                    ]
                ]
            ]

        ]);
    }

    /**
     * Creates a response with all required headers and encodes its body
     *
     * @param Array $body
     * @param integer $code
     * @return HTTPResponse
     */
    protected function json($body, $code = 200)
    {
        $body = json_encode($body);

        $response = (new HTTPResponse())
            ->addHeader('Content-Type', 'application/json')
            ->addHeader('Cache-Control', 'no-cache, no-store, must-revalidate')
            ->setStatusCode($code)
            ->setBody($body);

        return $response;
    }
}
