# SilverStripe elastic search

Silverstripe module to provide site-wide content search with elasticsearch

## Requirements

- SilverStripe ^4.0
- [elasticsearch-php](https://github.com/elastic/elasticsearch-php)
- [ramsey/uuid](https://github.com/ramsey/uuid)
- [SilverStripe Queued Jobs Module](https://github.com/symbiote/silverstripe-queuedjobs)

## Installation

1. Add private repo to the `composer.json`:

```
...
{
    "repositories": [
        {
            "type": "vcs",
            "url": "git@bitbucket.org:somardesignstudios/silverstripe-elastic-search.git"
        }
    ]
}
...
```

2. Install the module

```
composer require somardesignstudios/silverstripe-elastic-search
```

3. Create `log` folder in the root of the project with permissions allowing write to the user running the site

## Elastic connection configuration

Add your elasticsearch API keys to the .env file

```
# Elastic search
ELASTIC_CLOUD_ID=""
ELASTIC_INDEX=""

ELASTIC_API_ID=""
ELASTIC_API_KEY=""

```

# Search configuration

## Field mappings configuration

Page content will be flattened and stored in a standard set of fields defined in this module's `search.yml`

You can add additional fields by adding a `search.yml` config to your own project. e.g.

```yaml
---
Name: gwrc_search
After:
  - "#somar_search"
---
Somar\Search\ElasticSearchService:
  mappingProperties:
    custom_field_one:
      type: text
      store: true
    custom_field_two:
      type: date
      store: true
```

## Search fields

You can change the default search fields and its weightings in config file:

```yaml
Somar\Search\ElasticSearchService:
  searchFields:
    - title^2
    - keywords^2.5
    - content
```

## Search page

On the Search page you will find a Vue component that is configurable via .yml files:

```yaml
Somar\Search\PageType\SearchPage:
  searchConfig:
    headingLevel: 1
    allowEmptyKeyword: false
    secondarySearch: documents
    icons: material # adds material icon into the keyword field & dropdown tag close
    caretIconClass: "icon-dropdown" # adds <i class="icon-dropdown"> to dropdowns
    labels:
      title: Start typing to search the content # all title fields & subtitle can have placeholders and html
      titleFound: "Search results for <b>“[searchedKeyword]”</b>"
      titleSearching: "Searching for <b>“[keyword]”</b> ..."
      subtitle: "[resultsCount] results found"
      resultLinkText: Read more # uses URL of the result when not defined
      filtersHint: Refine your search results below by selecting popular filters and/or ordering them by date.
    filters:
      type:
      placeholder: Type of content
      field: type
      columns: 4 # width out of 12 columns on desktop, defaults to 6, set to 6 on tablet & 12 on mobile
      multiple: true # allows multiple values selected
      showInline: true # display all options inline rather than in dropdown
      default: news # preselected filter
      searchable: false
      iconClass: icon-type # adds <i class="icon-type"> to dropdowns
      options:
        news:
          name: News
          filter: GWRC\Website\PageType\NewsArticle
        events:
          name: Events
          filter: GWRC\Website\PageType\Event
        content:
          name: Content
          filter:not:
            - GWRC\Website\PageType\NewsArticle
            - GWRC\Website\PageType\Event
            - GWRC\Website\Model\DocumentLibrary\Document
        documents:
          name: Documents
          filter: GWRC\Website\Model\DocumentLibrary\Document
    date:
      placeholder: By date
      field: sort_date
      options:
        desc:
          name: Most recent first
        asc:
          name: Oldest first
        range:
          name: Select dates
```

## Search type

You can add additional configurations sets when multiple search pages with different configurations are needed. When an additional search type is defined, a dropdown will appear on the Search page. Configuration of additional search type is then merged with the default one.

```yaml
Somar\Search\PageType\SearchPage:
  searchTypes:
    documents:
      name: Document library
      presets:
        type: documents
      allowEmptyKeyword: true
      labels:
        title: Search publications
      filters:
        topics:
          placeholder: Topics
          field: topics
          multiple: true
          tag: GWRC\Website\Model\DocumentLibrary\DocumentTopic
        categories:
          placeholder: Categories
          field: categories
          multiple: true
          tag: GWRC\Website\Model\DocumentLibrary\DocumentCategory
        regions:
          placeholder: Regions
          field: regions
          multiple: true
          tag: GWRC\Website\Model\DocumentLibrary\DocumentRegion
      date:
        field: published
```

# Index creation

To create the index run `/dev/tasks/Somar-Search-Task-CreateIndexTask`. This task will create the index, set the mapping and create a pipeline for processing attachments.

When the index is created, use `SearchIndexJob` to index the site content (objects of class Page). You can add additional classes to index via .yml config:

```yml
Somar\Search\Job\SearchIndexJob:
  IndexedClasses:
    - GWRC\Website\Model\DocumentLibrary\Document
```
