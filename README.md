Reference (module for Omeka S)
==============================

[Reference] is a module for [Omeka S] that allows to serve a glossary (an
alphabetized index) of links to records or to searches for all resources classes
(item types) and properties (metadata fields) of all resources of an Omeka S
instance, or an expandable hierarchical tree of statically specified references.
These lists can be displayed in any page via a helper or a block. References can
be limited by site or any other pool, and ordered alphabetically or by count.

This [Omeka S] module is a rewrite of the [Reference plugin] for [Omeka] and
intends to provide the same features as the original plugin.


Installation
------------

Uncompress files and rename module folder `Reference`. Then install it like any
other Omeka module and follow the config instructions.

See general end user documentation for [Installing a module].

### Note for an upgrade from Omeka Classic

The default slugs use the full term, with the vocabulary prefix, so the default
route for subjects is now `reference/dcterms:subject` instead of `references/subject`.
It can be changed in the main config form or pages can be created with any slug.

Furthermore, the base route has been changed to singular `reference` instead of
`references`. To keep or to create an alias for old plural routes, simply
add/update it directly in your `local.config.php`, via a copy of the route part
of the file `config/module.config.php`. Any word can be used, like `lexicon`,
`glossary`, etc.

Anyway, it is recommended to use site page blocks, with any slug, so it’s not
necessary to modify the config.


Usage
-----

The plugin adds pages in all sites, that can be added to the navigation:
* "Browse by reference" (http://www.example.com/s/my-site/reference).
* "Hierarchy of references" (http://www.example.com/s/my-site/reference-tree).

The results are available via json too: simply add `.json` to the url (available
only for lists). The old `?output=json` is deprecated and will be removed in
version 3.4.12.

The config is the same in the main config form or in the block form for pages.

For the tree view, the references are formatted like:

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

So, the format is the config page for the tree view is:

- One reference by line.
- Each reference is preceded by zero, one or more "-" to indicate the hierarchy
level.
- Separate the "-" and the reference with a space.
- A reference cannot begin with a "-" or a space.
- Empty lines are not considered.

These contents can be displayed on any page via the view helper `reference()`:

```php
// With default values.
echo $this->reference()->displayListForTerm($term,
    [
        'type' => 'properties',
        'resource_name' => 'items',
        'order' => ['alphabetic' => 'ASC'],
        'query' => ['site_id' => 1],
        'per_page' => null,
        'page' => null,
    ],
    [
        'query_type' => 'eq',
        'link_to_single' => true,
        'total' => true,
        'skiplinks' => true,
        'headings' => true,
        'raw' => false,
    ]
);
```

For tree view:
```php
$references = $this->reference()->getTree();
echo $this->reference()->displayTree($references,
    [
        'term' => $term,
        'type' => 'properties',
        'resource_name' => 'items',
        'query' => ['site_id' => 1],
    ],
    [
        'query_type' => 'eq',
        'link_to_single' => true,
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

For `order`, the sort can be `['count' => 'DESC']` too.

For `query`, it is the standard query used in the api of Omeka, or the arguments
taken from the url of an advanced search, converted into an array with `parse_str`.
The conversion is automatically done inside the user interface (page blocks).


TODO
----

- Manage references inside admin board.
- Manage pagination by letter and by number of references.
- Fix initials for some letters ([#2](https://github.com/Daniel-KM/Omeka-S-module-Reference/issues/2)) via a custom DQL function for `convert`.
- Create automatically the pages related to the block index in order to remove
  the global pages.
- Manage advanced search reference by site and in admin board.


Warning
-------

Use it at your own risk.

It’s always recommended to backup your files and your databases and to check
your archives regularly so you can roll back if needed.


Troubleshooting
---------------

See online issues on the [module issues] page on GitHub.


License
-------

This module is published under the [CeCILL v2.1] licence, compatible with
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


Contact
-------

Current maintainers:

* Daniel Berthereau (see [Daniel-KM] on GitHub)

This module is inspired from earlier work done by William Mayo (see [pobocks] on
GitHub) in [Subject Browse], with some ideas from [Metadata Browser] and
[Category Browse], that have been upgraded for Omeka 2.x too ([Subject Browse (2.x)],
[Metadata Browser (2.x)], and [Category Browse (2.x)]). They are no longer
maintained. Upgrade and improvements were made for [Jane Addams Digital Edition].


Copyright
---------

* Copyright William Mayo, 2011
* Copyright Daniel Berthereau, 2014-2018
* Copyright Philip Collins, 2013 ([jQuery tree view])


[Omeka S]: https://omeka.org/s
[Reference]: https://github.com/Daniel-KM/Omeka-S-module-Reference
[Omeka]: https://omeka.org/classic
[Reference plugin]: https://github.com/Daniel-KM/Omeka-plugin-Reference
[Installing a module]: http://dev.omeka.org/docs/s/user-manual/modules/#installing-modules
[module issues]: https://github.com/Daniel-KM/Omeka-S-module-Reference/issues
[CeCILL v2.1]: https://www.cecill.info/licences/Licence_CeCILL_V2.1-en.html
[GNU/GPL]: https://www.gnu.org/licenses/gpl-3.0.html
[FSF]: https://www.fsf.org
[OSI]: http://opensource.org
[MIT]: http://http://opensource.org/licenses/MIT
[jQuery tree view]: https://github.com/collinsp/jquery-simplefolders
[pobocks]: https://github.com/pobocks
[Subject Browse]: https://github.com/pobocks/SubjectBrowse
[Metadata Browser]: https://github.com/kevinreiss/Omeka-MetadataBrowser
[Category Browse]: https://github.com/kevinreiss/Omeka-CategoryBrowse
[Subject Browse (2.x)]: https://github.com/Daniel-KM/Omeka-plugin-Reference/tree/subject_browse
[Metadata Browser (2.x)]: https://github.com/Daniel-KM/Omeka-plugin-MetadataBrowser
[Category Browse (2.x)]: https://github.com/Daniel-KM/Omeka-plugin-CategoryBrowse
[Jane Addams Digital Edition]: http://digital.janeaddams.ramapo.edu
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
