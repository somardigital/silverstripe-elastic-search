<?php

namespace Somar\Search\Control;

use PageController;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
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
        if (!Environment::getEnv('SS_HOT_RELOAD_URL')) {
            Requirements::javascript('somardesignstudios/silverstripe-elastic-search: client/dist/js/app.js');
            Requirements::javascript('somardesignstudios/silverstripe-elastic-search: client/dist/js/chunk-vendors.js');
        } else {
            Requirements::javascript(Environment::getEnv('SS_HOT_RELOAD_URL') . 'js/app.js');
            Requirements::javascript(Environment::getEnv('SS_HOT_RELOAD_URL') . 'js/chunk-vendors.js');
        }

        Requirements::set_force_js_to_bottom(true);

        return $this->renderWith([
            'Somar\\Search\\SearchPage',
            'Page',
        ]);
    }

    public function search(HTTPRequest $request)
    {
        $term = $request->getVar('q');

        $data = [
            'results' => $this->getResults($term)
        ];


        return $this->json($data);
    }

    protected function getResults($term)
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
                    $type = 'page';
                    $summary = DBText::create()
                        ->setValue(str_replace(["\n", "\t"], '', $resultData['content']))
                        ->Summary(80);
            }

            $data->push(ArrayData::create([
                'title' => $resultData['title'],
                'summary' => $summary,
                'url' => $resultData['url'],
                'type' => $type,
                'lastEdited' => $resultData['last_indexed']
            ]));
        }

        return $data->toNestedArray();
    }

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
