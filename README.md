Reference (module for Omeka S)
==============================

[Reference] is a module for [Omeka S] that allows to serve an alphabetized index
of links to records or to searches for all resources classes (item types) and
properties (metadata fields) of all resources of an Omeka S instance, or an
expandable hierarchical list of specified subjects. These lists can be displayed
in any page via a helper or a block.

This [Omeka S] module is a rewrite of the [Reference plugin] for [Omeka] and
intends to provide the same features as the original plugin.


Installation
------------

Uncompress files and rename module folder `Reference`. Then install it like any
other Omeka module and follow the config instructions.

See general end user documentation for [Installing a module].

*** Upgrade from Omeka Classic

The routes have been changed to singular, for example `reference/subject`
instead of `references/subject`. To keep or to create an alias for old plural
routes, simply add/update it directly in the file `config/module.config.php`.

Furthemore, the default slugs use the full term, with the vocabulary prefix, so
`reference/dcterms:subject` instead of `references/subject`. It can be changed
in the config form.


Usage
-----

The plugin adds a page and a block, that can be added to the navigation:
* "Browse by Reference" (http://www.example.com/reference).
* "Hierarchy of Subjects" (http://www.example.com/subjects/tree).

The results are available via json too: simply add `?output=json` to the url.

For the list view, the references are defined in the config page.

For the tree view, the subjects are set in the config form with the hierarchical
list of subjects, formatted like:
```
Europe
- France
- Germany
- United Kingdom
-- England
-- Scotland
-- Wales
Asia
- Japan
```

So, the format is the config page for the tree view is:

- One subjet by line.
- Each subject is preceded by zero, one or more "-" to indicate the hierarchy
level.
- Separate the "-" and the subject with a space.
- A subject cannot begin with a "-" or a space.
- Empty lines are not considered.

These contents can be displayed on any page via the helper `reference()`:

```
$slug = 'subject';
$references = $this->reference()->getList($slug);
echo $this->reference()->displayList($references, array(
    'skiplinks' => true,
    'headings' => true,
    'strip' => true,
    'raw' => false,
));
```

For tree view:
```
$subjects = $this->reference()->getTree();
echo $this->reference()->displayTree($subjects, array(
    'expanded' => true,
    'strip' => true,
    'raw' => false,
));
```

All arguments are optional and the default ones are set in the config page, but
they can be overridden in the theme. So a simple `echo $this->reference();`
is enough. For list, the default is the "Dublin Core : Subject".


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
[Reference plugin]: https://github.com/Daniel-KM/Reference
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
[Subject Browse (2.x)]: https://github.com/Daniel-KM/Reference/tree/subject_browse
[Metadata Browser (2.x)]: https://github.com/Daniel-KM/MetadataBrowser
[Category Browse (2.x)]: https://github.com/Daniel-KM/CategoryBrowse
[Jane Addams Digital Edition]: http://digital.janeaddams.ramapo.edu
[Daniel-KM]: https://github.com/Daniel-KM "Daniel Berthereau"
