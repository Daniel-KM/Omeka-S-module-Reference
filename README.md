Reference (module for Omeka S)
==============================

> __New versions of this module and support for Omeka S version 3.0 and above
> are available on [GitLab], which seems to respect users and privacy better
> than the previous repository.__

[Reference] is a module for [Omeka S] that allows to serve a glossary (an
alphabetized index) of links to records or to searches for all resources classes
(item types) and properties (metadata fields) of all resources of an Omeka S
instance. The references can be aggregated, for example to get all dates from
"dcterms:date" and "dcterms:issued" together.

These lists can be displayed in any page via a helper or a block. References can
be limited by site or any other pool, and ordered alphabetically or by count.

The references are available via the api too, for example `/api/references?metadata=dcterms:subject`
to get the list of all subjects, or `/api/references?metadata=foaf:Person` to
get the list of all resources with class "Person". Another format for the query
is provided by the module [Api Info].

This [Omeka S] module is a rewrite and an improvement of the [Reference plugin]
for [Omeka].


Installation
------------

See general end user documentation for [installing a module].

* From the zip

Download the last release [Reference.zip] from the list of releases, and
uncompress it in the `modules` directory.

* From the source and for development

If the module was installed from the source, rename the name of the folder of
the module to `Reference`.

### Note for an upgrade from Omeka Classic

The default slugs use the full term, with the vocabulary prefix, so the default
route for subjects is now `reference/dcterms:subject` instead of `references/subject`.
It can be changed in the site settings form and pages can be created with any
slug.

Furthermore, the base route has been changed to singular `reference` instead of
`references`. To keep or to create an alias for old plural routes, simply
add/update it directly in your `local.config.php`, via a copy of the route part
of the file `config/module.config.php`. Any word can be used, like `lexicon`,
`glossary`, etc.

Anyway, it is recommended to use site page blocks, with any slug, so it’s not
necessary to modify the config.


Usage
-----

The site settigns allows to select the terms to display. The config is the same
for the main site pages or in the block form for pages. It is recommended to use
site pages when possible.

### Automatic site pages

The module adds pages for selected resource classes and properties at https://www.example.com/s/my-site/reference.
Available pages and options can be set in the site settings. Options are:

- Print headings: Print headers for each section (#0-9 and symbols, A, B, etc.).
- Print skip links: Print skip links at the top and bottom of each page, which
  link to the alphabetical headers. Note that if headers are turned off,
  skiplinks do not work.
- Print individual total: Print the total of resources for each reference.
- Link to single: When a reference has only one item, link to it directly
  instead of to the items/browse page.
- Custom url for single: May be set with modules such Clean Url or Ark. May slow
  the display when there are many single references.

### Lists

A block allows to display the lists in any page. Furthermore,

These contents can be displayed anywere via the view helper `references()`:

```php
// With default values.
echo $this->references()->displayListForTerm('dcterms:subject', $query, $options);
// Get the lists.
print_r($this->references()->list('dcterms:subject', $query, $options));
// Get the count.
echo $this->references()->count('dcterms:subject', $query, $options);
// Get the initials (here to get the list of years from iso 8601 values or numeric timestamp).
print_r($this->references()->initials('dcterms:created', $query, ['initial' => 4]));
// Get the list of resources related to all subjects.
print_r($references->list('dcterms:subject', null, ['list_by_max' => 1024]));
```

The references are available via the api in `/api/references` too. Arguments are
the same than above: the search query + a `metadata` array for the list of
fields to get, and an array of `options` to use  (see [below](#api-to-get-references-and-facets)). The same feature
is available via the module [Api Info] too on `/api/infos/references`.

### Tree view

The tree of references can be build with the block page "Reference tree".
The references should be formatted like:

```
Europe
- France
-- Paris
- United Kingdom
-- England
--- London
Asia
- Japan
```

So:
- One reference by line.
- Each reference is preceded by zero, one or more "-" to indicate the hierarchy
level.
- Separate the "-" and the reference with a space.
- A reference cannot begin with a "-" or a space.
- Empty lines are not considered.

Via the helper:

```php
echo $this->references()->displayTree(
    // A dash list as a text or as an array of value/level.
    $referenceLevels,
    ['site_id' => 1],
    [
        'term' => $term,
        'type' => 'properties',
        'resource_name' => 'items',
        'query_type' => 'eq',
        'link_to_single' => true,
        'custom_url' => false,
        'total' => true,
        'expanded' => true,
        'strip' => true,
        'total' => true,
        'raw' => false,
    ]
);
```

All arguments are optional and the default ones are set in the config page or in
the block, but they can be overridden in the theme.

For `order`, the sort can be `['total' => 'DESC']` too.

For `query`, it is the standard query used in the api of Omeka, or the arguments
taken from the url of an advanced search, converted into an array with `parse_str`.
The conversion is automatically done inside the user interface (page blocks).

### Api to get references and facets

To get the results via api, use a standard query and append the options you need,
for example `/api/references?metadata[subjects]=dcterms:subject` to get the list
of all subjects, or `/api/references?metadata[people]=foaf:Person` to get the
list of all resources with class "Person". You can add multiple metadata together: `/api/references?medatadata[subjects]=dcterms:subject&medatadata[creators]=dcterms:creator`
You can use the special metadata `o:title` too, but some options won't be
available for it since it is managed differently inside Omeka. The metadata can
be a property term, or `o:item_set`, `o:resource_class`, and `o:resource_template`
too. If no `metadata` is set, you will get the totals of references for
properties.

The query from the url can be simplified with `text=my-text` in most of the
cases, so the references are filtered by this text in any property.
If one or multiple fields are specified, the references are returned for these
fields. The fields can be a comma separated list of an array, for example:
`/api/references?text=example&metadata[subjects]=dcterms:subject` allows to get all
references for the specified text in the specified field.

To get the facets for the search result page, you can use this query:
`/api/references?text=xxx&site_id=1&option[resource_name]=items&option[sort_by]=total&option[sort_order]=desc&option[filters][languages][]=fra&option[filters][languages][]=null&option[filters][languages]=&option[lang]=1&metadata[subjects]=dcterms:subject`
Note: if you use the filters for the language, it may be needed to add an
empty language `&option[filters][languages][]=null` or, for string format, `&option[filters][languages]=fra,null`
because many metadata have no language (date, names, etc.).
The empty language can be an empty string too (deprecated).

To get more information about results, in particular the list of resources
associated to each reference (`list_by_max`), use options. Options can be
appended to the query. If you don't want to mix them, you can use the keys
`query` and `option`.

Options are the same than the view helper:

- `resource_name`: items (default), "item_sets", "media", "resources".
- `sort_by`: "alphabetic" (default), "count", or any available column.
- `sort_order`: "asc" (default) or "desc".
- `filters`: array Limit values to the specified data. Currently managed:
  - `languages`: list of languages. Values without language are returned with
    the value "null". This option is used only for properties.
  - `datatypes`: array Filter property values according to the data types.
    Default datatypes are "literal", "resource", "resource:item", "resource:itemset",
    "resource:media" and "uri"; other existing ones are managed.
    Warning: "resource" is not the same than specific resources.
    Use module Bulk Edit or Bulk Check to specify all resources automatically.
  - `begin`: array Filter property values that begin with these strings,
    generally one or more initials.
  - `end`: array Filter property values that end with these strings.
- `values`: array Allow to limit the answer to the specified values.
- `first`: false (default), or true (get first resource).
- `list_by_max`: 0 (default), or the max number of resources for each reference)
  The max number should be below 1024 (mysql limit for group_concat).
- `fields`: the fields to use for the list of resources, if any. If not set, the
  output is an associative array with id as key and title as value. If set,
  value is an array of the specified fields.
- `initial`: false (default), or true (get first letter of each result).
- `distinct`: false (default), or true (distinct values by type).
- `datatype`: false (default), or true (include datatype of values).
- `lang`: false (default), or true (include language of value to result).
- `locale`: empty (default) or a string or an ordered array Allow to get the
  returned values in the first specified language when a property has translated
  values. Use "null" to get a value without language.
  Unlike Omeka core, it gets the translated title of linked resources.
- `include_without_meta`: false (default), or true (include total of resources
  with no metadata) (TODO Check if this option is still needed).
- `single_reference_format`: false (default), or true to keep the old output
  without the deprecated warning for single references without named key.
- `output`: "list" (default) or "associative" (possible only without added
  options: first, initial, distinct, datatype, or lang).

A standard resource query can be appended to the query. The property argument
supports some more types for the properties: `sw`/`nsw` for "starts with" or not,
`ew`/`new` for "ends with" or not, `in`/`nin` for "in list" or not, `res`/`nres`
for "has resource" or not.

Don't confuse the filters and the query: the query limits the resource to search,
generally a site or an item set, and the filters limits the returned list of
references.

For the filters and the metadata, they can be written in various ways to
simplify url request, for example:
- `metadata[ids]=o:id&metadata[titles]=o:title&metadata[collections]=o:item_set&metadata[short_titles]=bibo:shortTitle`
- `metadata=o:id,o:title,o:item_set,bibo:shortTitle`
or
- `filters[begin][]=w&filters[begin][]=x&filters[begin][]=y&filters[begin][]=z`
- `filters[begin]=w,x,y,z`

To get results for aggregated metadata, use an array for the fields:
- `metadata[Dates][]=dcterms:date&metadata[Dates][]=dcterms:issued`
- `metadata[Dates]=dcterms:date,dcterms:issued`.
The key of the metadata ("Dates" here) is used as the key and the label in the
result. Don't forget that aggregated metadata are possible only for properties.

**Important**:
The response is for all sites by default. Add argument `site_id={##}` or `site_slug={slug}`
to get data for a site.


Note about linked resources
---------------------------

When you want to fetch all datatypes, the data types `resource`, `resource:item`,
`resource:itemset`, etc. are returned. It is recommended to replace all types
`resource` by the specific one in order to clarify the results. The modules [Bulk Edit]
or [Bulk Check] allows to do it automatically.


TODO
----

- [x] Normalize output with `o:references` instead of `o-module-reference:values` (Omeka version 3.0).
- [x] Display values other than literal.
- [ ] Manage pagination by letter and by number of references.
- [ ] Create automatically the pages related to the block index in order to remove the global pages.
- [ ] Fix initials for some letters ([#2](https://gitlab.com/Daniel-KM/Omeka-S-module-Reference/-/issues/2)) via a custom DQL function for `convert`.
- [ ] Manage references inside admin board.
- [ ] Manage advanced search reference by site and in admin board.
- [ ] Make the reference recursive (two levels currently).
- [ ] Get the second levels via a single sql, not via api.
- [ ] Check if the option "include_without_meta" is still needed with data types.
- [ ] Include the fields in the main request or get them via a second request, not via api.
- [ ] Use the new table `reference_metadata` when possible.
- [ ] Simplify queries for aggregated fields (see AdvancedSearch).
- [ ] Order by years instead of alphabetic.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitLab.


License
-------

This module is published under the [CeCILL v2.1] license, compatible with
[GNU/GPL] and approved by [FSF] and [OSI].

In consideration of access to the source code and the rights to copy, modify and
redistribute granted by the license, users are provided only with a limited
warranty and the software’s author, the holder of the economic rights, and the
successive licensors only have limited liability.

In this respect, the risks associated with loading, using, modifying and/or
developing or reproducing the software by the user are brought to the user’s
attention, given its Free Software status, which may make it complicated to use,
with the result that its use is reserved for developers and experienced
professionals having in-depth computer knowledge. Users are therefore encouraged
to load and test the suitability of the software as regards their requirements
in conditions enabling the security of their systems and/or data to be ensured
and, more generally, to use and operate it in the same conditions of security.
This Agreement may be freely reproduced and published, provided it is not
altered, and that no provisions are either added or removed herefrom.

The module uses a jQuery library for the tree view, released under the [MIT]
licence.


Copyright
---------

* Copyright William Mayo, 2011
* Copyright Philip Collins, 2013 ([jQuery tree view])
* Copyright Daniel Berthereau, 2014-2023 (see [Daniel-KM] on GitLab)

This module is inspired from earlier work done by William Mayo (see [pobocks] on
GitLab) in [Subject Browse], with some ideas from [Metadata Browser] and
[Category Browse], that have been upgraded for Omeka 2.x too ([Subject Browse (2.x)],
[Metadata Browser (2.x)], and [Category Browse (2.x)]). They are no longer
maintained. Upgrade and improvements were made for [Jane Addams Digital Edition].
Performance fixes were made for Article 19.


[Omeka S]: https://omeka.org/s
[Reference]: https://gitlab.com/Daniel-KM/Omeka-S-module-Reference
[Omeka]: https://omeka.org/classic
[Reference plugin]: https://gitlab.com/Daniel-KM/Omeka-plugin-Reference
[Reference.zip]: https://gitlab.com/Daniel-KM/Omeka-S-module-Reference/-/releases
[Installing a module]: https://omeka.org/s/docs/user-manual/modules/#installing-modules
[Api Info]: https://gitlab.com/Daniel-KM/Omeka-S-module-ApiInfo
[Bulk Edit]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkEdit
[Bulk Check]: https://gitlab.com/Daniel-KM/Omeka-S-module-BulkCheck
[module issues]: https://gitlab.com/Daniel-KM/Omeka-S-module-Reference/-/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: http://opensource.org/licenses/MIT
[jQuery tree view]: https://github.com/collinsp/jquery-simplefolders
[pobocks]: https://github.com/pobocks
[Subject Browse]: https://github.com/pobocks/SubjectBrowse
[Metadata Browser]: https://github.com/kevinreiss/Omeka-MetadataBrowser
[Category Browse]: https://github.com/kevinreiss/Omeka-CategoryBrowse
[Subject Browse (2.x)]: https://gitlab.com/Daniel-KM/Omeka-plugin-Reference/-/tree/subject_browse
[Metadata Browser (2.x)]: https://gitlab.com/Daniel-KM/Omeka-plugin-MetadataBrowser
[Category Browse (2.x)]: https://gitlab.com/Daniel-KM/Omeka-plugin-CategoryBrowse
[Jane Addams Digital Edition]: http://digital.janeaddams.ramapo.edu
[GitLab]: https://gitlab.com/Daniel-KM
[Daniel-KM]: https://gitlab.com/Daniel-KM "Daniel Berthereau"
