
The built-in SilverStripe search form is a very simple search engine. This plugin takes SQL-based searching to the next level, without requiring the implementation of a full-blown search engine like Solr or Elastic Search. It is designed to bring data-oriented filters on top of the simple text search functionality.


# Requirements

* SilverStripe 3


# TO DO - UPDATE BELOW


# Usage

* Create a `SearchPage` instance (typically at the root of your website). This page only is used to display results, so please refrain from creating multiple instances.
* Configure your website's `_config/config.yml` to define search parameters.
* Run `dev/build` to instansiate your new configuration


# Configuration
* `types`: associative list of types to search
  * `Label`: front-end field label
  * `Table`: the object's primary table (note `_Live` suffix for versioned objects)
  * `ClassName`: full ClassName
  * `ClassNameShort`: namespaced ClassName
  * `Filters`: a list of filters to apply pre-search (maps to `DataList->Filter(key => value)`)
  * `Columns`: columns to search for query string matches (format `Table.Column`)
* `filters`: associative list of filter options
  * `Structure`: defines the filter's relational structure (must be one of `db`, `has_one` or `has_many`)
  * `Label`: front-end field label
  * `Table`: relational subject's table
  * `Column`: column to filter on
  * `Operator`: SQL filter operator (ie `>`, `=`)
  * `JoinTables`: associative list of relationship mappings (use the `key` from the `types` array)
    * `Table`: relational join table
    * `Column`: column to join by
 * `sorts`: associative list of sort options
   * `Label`: front-end field label
   * `Sort`: SQL sort string


# Example configuration

```
---
Name: search
Before:
    - '#site'
---
Jaedb\Search\SearchPageController:
  types:
    docs:
      Label: 'Documents'
      Table: 'File_Live'
      ClassName: 'SilverStripe\Assets\File'
      ClassNameShort: 'File'
      Filters:
        File_Live.ShowInSearch: '1'
      Columns: ['File_Live.Title','File_Live.Description','File_Live.Name']
    pages:
      Label: 'Pages'
      ClassName: 'Page'
      ClassNameShort: 'Page'
      Table: 'Page_Live'
      Filters: 
        SiteTree_Live.ShowInSearch: '1'
      JoinTables: ['SiteTree_Live']
      Columns: ['SiteTree_Live.Title','SiteTree_Live.MenuTitle','SiteTree_Live.Content']
  filters:
    updated_before:
      Structure: 'db'
      Label: 'Updated before'
      Column: 'LastEdited'
      Operator: '<'
    updated_after:
      Structure: 'db'
      Label: 'Updated after'
      Column: 'LastEdited'
      Operator: '>'
    tags:
      Structure: 'many_many'
      Label: 'Tags'
      ClassName: 'Tag'
      Table: 'Tag'
      JoinTables:
        docs: 
          Table: 'File_Tags'
          Column: 'FileID'
        pages: 
          Table: 'Page_Tags'
          Column: 'PageID'
  sorts:
    title_asc:
      Label: 'Title (A-Z)'
      Sort: 'Title ASC'
    title_desc:
      Label: 'Title (Z-A)'
      Sort: 'Title DESC'
    published_asc:
      Label: 'Publish date (newest first)'
      Sort: 'DatePublished DESC'
    published_desc:
      Label: 'Publish date (oldest first)'
      Sort: 'DatePublished ASC'
```
