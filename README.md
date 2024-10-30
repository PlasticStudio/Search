
The built-in SilverStripe search form is a very simple search engine. This plugin takes SQL-based searching to the next level, without requiring the implementation of a full-blown search engine like Solr or Elastic Search. It is designed to bring data-oriented filters on top of the simple text search functionality.


# Requirements

* SilverStripe 4


# Usage

* Create a `SearchPage` instance (typically at the root of your website). This page only is used to display results, so please refrain from creating multiple instances.
* Configure your website's `_config/config.yml` (or add `_config/search.yml`) to define search parameters.
* Run `dev/build` to instansiate your new configuration (this will also automatically create an instance of `SearchPage` if one does not exist).
* To overwrite the default `SearchPage` tmeplate, add a template file to your application: `templates/PlasticStudio/Search/Layout/SearchPage.ss`


# Elemental

* Elemental search is included
* On page or Element save, all content from all Elements is saved to a field called `ElementalSearchContent` on sitetree.
* Simply include `'SiteTree_Live.ElementalSearchContent'` to the list of page columns
* Currently there is no way to exclude individual elements from being included.
* Run IndexPageContentForSearchTask to index element content


# Configuration
* `types`: associative list of types to search
  * `Label`: front-end field label
  * `Table`: the object's primary table (note `_Live` suffix for versioned objects)
  * `ClassName`: full ClassName
  * `ClassNameShort`: namespaced ClassName
  * `Filters`: a list of filters to apply pre-search (maps to `DataList->Filter(key => value)`)
  * `Columns`: columns to search for query string matches (format `Table.Column`)
* `filters`: associative list of filter options
  * `Structure`: defines the filter's relational structure (must be one of `db`, `has_one` or `many_many`)
  * `Label`: front-end field label
  * `Table`: relational subject's table
  * `Column`: column to filter on
  * `Operator`: SQL filter operator (ie `>`, `<`, `=`)
  * `JoinTables`: associative list of relationship mappings (use the `key` from the `types` array)
    * `Table`: relational join table
    * `Column`: column to join by
 * `sorts`: associative list of sort options. These are used to popoulate a "Sort by" dropdown field in the Advanced Search Form. Sort order of search results will default to the top item in this list.
   * `Label`: front-end field label
   * `Sort`: SQL sort string
* `submit_button_text`: Text to use on search form submit button (defaults to "Search")

TODO: `defaults`: Default attributes or settings, as opposed to those submitted through the search form.


# Example configuration

```
---
Name: search
Before:
    - '#site'
---
PlasticStudio\Search\SearchPageController:
  types:
    docs:
      Label: 'Documents'
      Table: 'File_Live'
      ClassName: 'SilverStripe\Assets\File'
      ClassNameShort: 'File'
      Filters:
        File_Live.ShowInSearch: '1'
        File_Live.ClassName:  '''Silverstripe\\Assets\\File''' # You need to TRIPLE-ESCAPE in order to pass this as a string to the query
      Columns: ['File_Live.Title','File_Live.Description','File_Live.Name']
    pages:
      Label: 'Pages'
      ClassName: 'Page'
      ClassNameShort: 'Page'
      Table: 'Page_Live'
      Filters: 
        SiteTree_Live.ShowInSearch: '1'
      JoinTables: ['SiteTree_Live']
      Columns: ['SiteTree_Live.Title','SiteTree_Live.MenuTitle','SiteTree_Live.Content', 'SiteTree_Live.ElementalSearchContent']
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
      authors:
        Structure: 'many_many'
        Label: 'Authors'
        ClassName: 'Member'
        Table: 'Member'
        JoinTables:
          pages: 
            Table: 'Page_Authors'
            Column: 'PageID'
    subsite:
      Structure: 'db'
      Label: 'Subsite'
      Column: 'SubsiteID'
      Operator: '='
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
  submit_button_text: 'Go'
  ## TODO:
  ## defaults:
    ## sort: 'Title ASC'
```
