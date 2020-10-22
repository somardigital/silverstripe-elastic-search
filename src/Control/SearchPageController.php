<?php

namespace Somar\Search\Control;

use PageController;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Core\Environment;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBText;
use SilverStripe\View\Requirements;
use Somar\Search\ElasticSearchService;
use SilverStripe\Forms\Filter\SlugFilter;
use SilverStripe\ORM\DataObject;
use Somar\Search\PageType\SearchPage;

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
        $params = $this->buildSearchParams($request);

        $data = $this->getSearchResponse($params);

        return $this->json($data);
    }

    protected function getSearchResponse($params)
    {
        $service = new ElasticSearchService();
        $results = $service->searchDocuments($params);

        if (!empty($results['error'])) {
            return $results;
        }

        $resultsData = new ArrayList();

        foreach ($results['hits']['hits'] as $result) {
            $data = $result['_source'];
            $highlightData = $result['highlight'] ?? [];

            if (empty($highlightData)) {
                $summary = !empty($data['content'])
                    ? DBText::create()
                    ->setValue(str_replace(["\n", "\t"], '', $data['content']))
                    ->Summary(80)
                    : '';
            } else {
                $summary = str_replace(["\n", "\t"], '', reset($highlightData)[0]);
            }

            $resultData = [
                'title' => !empty($data['title']) ? $data['title'] : '',
                'url' => !empty($data['url']) ? $data['url'] : '',
                'type' => 'page',
                'date' => !empty($data['sort_date']) ? $data['sort_date'] : '',
                'thumbnailURL' => !empty($data['thumbnail_url']) ? $data['thumbnail_url'] : '',
                'summary' => $summary
            ];

            $this->extend('updateResultData', $data, $resultData, $highlightData);

            $resultsData->push($resultData);
        }

        return [
            'results' => $resultsData->toNestedArray(),
            'meta' => [
                'count' => $results['hits']['total']['value']
            ]
        ];
    }


    /**
     * Builds parameters for ElasticSearchService based on search request parameters
     *
     * @param HTTPRequest $request
     * @return array
     */
    protected function buildSearchParams(HTTPRequest $request)
    {
        $params = [];
        $queryParams = $request->getVars();

        // allow injecting search parameters
        $this->extend('updateQueryParams', $queryParams);

        // keyword
        if (!empty($queryParams['q'])) {
            $params['term'] = $queryParams['q'];
        }

        $searchConfig = SearchPage::config()->searchConfig;

        // overwrite with predefined search type
        if ($this->SearchType) {
            $searchTypeConfig = SearchPage::config()->searchTypes[$this->SearchType];

            if (!empty($searchTypeConfig['presets'])) {
                foreach ($searchTypeConfig['presets'] as $name => $value) {
                    $queryParams[$name] = [$value];
                }
            }

            if (!empty($searchTypeConfig['filters'])) {
                $searchConfig['filters'] = array_replace_recursive($searchConfig['filters'], $searchTypeConfig['filters']);
            }

            if (!empty($searchTypeConfig['date'])) {
                $searchConfig['date'] = array_replace_recursive($searchConfig['date'], $searchTypeConfig['date']);
            }
        }

        // filters
        if (!empty($searchConfig['filters'])) {
            foreach ($searchConfig['filters'] as $name => $filter) {
                if (!empty($queryParams[$name])) {
                    $field = $filter['field'];

                    $params['filter']["$field"] = [];
                    $params['filter']["$field:not"] = [];

                    foreach ($queryParams[$name] as $filteredValue) {
                        // if there is no predefined option, it an option generated from tags
                        $option = !empty($filter['options'][$filteredValue]) ? $filter['options'][$filteredValue] : ['filter' => $filteredValue];

                        foreach (["", ":not"] as $type) {
                            if (!empty($option['filter' . $type])) {
                                // allow single or multiple values for each filter option
                                $filterValue = is_array($option['filter' . $type]) ?
                                    $option['filter' . $type] : [$option['filter' . $type]];
                                $filterValue2 = is_array($params['filter'][$field . $type]) ?
                                    $params['filter'][$field . $type] : [$params['filter'][$field . $type]];
                                $params['filter'][$field . $type] = array_merge($filterValue, $filterValue2);
                                // $params['filter'][$field . $type] = [...$params['filter'][$field . $type], ...$filterValue];
                            }
                        }
                    }

                    // to not have the same filter including and excluding at the same time
                    if (!empty($params['filter']["$field"]) && !empty($params['filter']["$field:not"])) {
                        $params['filter']["$field:not"] = array_values(array_diff($params['filter']["$field:not"], $params['filter']["$field"]));
                        $params['filter']["$field"] = [];
                    }
                }
            }
        }

        // date filter/sort
        if (!empty($searchConfig['date'])) {
            $dateConfig = $searchConfig['date'];

            // sort by date when empty keword
            if (empty($queryParams['q']) && empty($queryParams['sort'])) {
                $queryParams['sort'] = 'desc';
            }

            if (!empty($queryParams['sort'])) {
                $params['sort'][$dateConfig['field']] = $queryParams['sort'];
            }

            if (!empty($queryParams['dateFrom'])) {
                $params['range'][$dateConfig['field']]['from'] = date(\DateTime::ISO8601, strtotime($queryParams['dateFrom']));
            }

            if (!empty($queryParams['dateTo'])) {
                $params['range'][$dateConfig['field']]['to'] = date(\DateTime::ISO8601, strtotime($queryParams['dateTo']));
            }
        }

        // limit, offset
        if (!empty($queryParams['size'])) {
            $params['size'] = (int) $queryParams['size'];
        } else {
            $params['size'] = 50;
        }

        if (!empty($queryParams['offset'])) {
            $params['offset'] = (int) $queryParams['offset'];
        }

        $this->extend('updateSearchParams', $params, $request);

        return $params;
    }

    /**
     * Search configuration
     *
     * @return string JSON encoded configuration object
     */
    protected function getSearchConfig()
    {
        $searchConfig = SearchPage::config()->get('searchConfig');

        // to allow dynamically defined options
        $this->extend('updateSearchConfig', $searchConfig);

        if ($this->SearchType) {
            $searchTypeConfig = SearchPage::config()->searchTypes[$this->SearchType];
            $searchConfig = array_replace_recursive($searchConfig, $searchTypeConfig);

            foreach ($searchTypeConfig['presets'] as $name => $value) {
                if (isset($searchConfig['filters'][$name])) {
                    unset($searchConfig['filters'][$name]);
                }
            }
        }

        // parse yml config to structure required for frontend
        $parseConfig = function ($filterName, $config) {
            $options = [];

            if (!empty($config['tag']) && $tags = DataObject::get($config['tag'])->toArray()) {
                $slugFilter = SlugFilter::create();

                $options = array_map(function ($tag) use ($slugFilter) {
                    return [
                        'name' => $tag->Title,
                        'value' => $slugFilter->filter($tag->Title)
                    ];
                }, $tags);
            }

            if (!empty($config['options'])) {
                foreach ($config['options'] as $value => $option) {
                    $options[] = [
                        'name' => $option['name'],
                        'value' => $value
                    ];
                }
            }

            return !empty($options) ? [
                'name' => $filterName,
                'label' => $config['label'] ?? '',
                'iconClass' => $config['iconClass'] ?? '',
                'placeholder' => $config['placeholder'] ?? '',
                'columns' => $config['columns'] ?? 6,
                'showInline' => $config['showInline'] ?? false,
                'default' => $config['default'] ?? null,
                'options' => $options
            ] : null;
        };

        $filters = [];

        if (!empty($searchConfig['filters'])) {
            foreach ($searchConfig['filters'] as $name => $filter) {
                if ($filterConfig = $parseConfig($name, $filter)) {
                    $filters[] = $filterConfig;
                }
            }
        }

        $config = [
            'headingLevel' => $searchConfig['headingLevel'] ?? 2,
            'labels' => $searchConfig['labels'],
            'placeholder' => $searchConfig['placeholder'] ?? '',
            'filters' => $filters,
            'allowEmptyKeyword' => $searchConfig['allowEmptyKeyword'],
            'icons' => $searchConfig['icons'],
            'caretIconClass' => $searchConfig['caretIconClass'] ?? '',
        ];

        if (!empty($searchConfig['date'])) {
            $config['date'] = $parseConfig('date', $searchConfig['date']);
        }

        if (!empty($searchConfig['secondarySearch']) && $this->SearchType != $searchConfig['secondarySearch']) {
            $secondarySearch = SearchPage::get()->filter('SearchType', $searchConfig['secondarySearch'])->first();
            $searchTypeConfig = SearchPage::config()->searchTypes[$secondarySearch->SearchType];

            $config['secondarySearch'] = [
                'title' => 'Search in ' . strtolower($searchTypeConfig['name']),
                'url' => $secondarySearch->Link()
            ];
        }

        return json_encode($config);
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
