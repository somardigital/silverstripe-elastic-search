# SilverStripe elastic search

Silverstripe module to provide site-wide content search with elasticsearch

## Requirements

* SilverStripe ^4.0
* Elastic Search
* Other server requirement
* Etc

## Installation
Add some installation instructions here, having a 1 line composer copy and paste is useful. 
Here is a composer command to create a new module project. Ensure you read the
['publishing a module'](https://docs.silverstripe.org/en/developer_guides/extending/how_tos/publish_a_module/) guide
and update your module's composer.json to designate your code as a SilverStripe module. 

```
composer require silverstripe-module/skeleton 4.x-dev
```

## Example configuration
Add your elasticsearch API keys

```
# Elastic search
ELASTIC_CLOUD_ID=""
ELASTIC_INDEX=""

# For back-end indexing
ELASTIC_WRITABLE_API_ID=""
ELASTIC_WRITABLE_API_KEY=""

# For front-end searches
ELASTIC_READONLY_API_ID=""
ELASTIC_READONLY_API_KEY=""
  
```
