<?php declare(strict_types=1);

namespace Reference\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Reference\Mvc\Controller\Plugin\References as ReferencesPlugin;
use Reference\Mvc\Controller\Plugin\ReferenceTree;

class References extends AbstractHelper
{
    /**
     * @var \Reference\Mvc\Controller\Plugin\References
     */
    protected $references;

    /**
     * @var \Reference\Mvc\Controller\Plugin\ReferenceTree
     */
    protected $referenceTree;

    public function __construct(ReferencesPlugin $references, ReferenceTree $referenceTree)
    {
        $this->references = $references;
        $this->referenceTree = $referenceTree;
    }

    /**
     * Get the references.
     *
     * @uses \Reference\Mvc\Controller\Plugin\References
     */
    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Stringify to an empty string to force to use a specific method.
     */
    public function __toString(): string
    {
        return '';
    }

    /**
     * Get the references.
     *
     * @uses \Reference\Mvc\Controller\Plugin\References::__invoke()
     * @uses \Reference\Mvc\Controller\Plugin\References::list()
     *
     * @param array|string $metadata List of metadata to get references for.
     * Classes, properties terms, template names, or other Omeka metadata names.
     * Similar types of metadata may be grouped to get aggregated references,
     * for example ['Dates' => ['dcterms:date', 'dcterms:issued']], with the key
     * used as key and label in the result. Each value may be a comma-separated
     * list of metadata, that will be exploded to an array of metadata to group.
     * @param array $query An Omeka search query to limit the base pool.
     * @param array $options Options for output.
     * - Options to specify, filter, limit and sort references:
     *   - resource_name (string): items (default), "item_sets", "media",
     *     "resources".
     *   - page (int): the page to output, the first one in most of the cases.
     *   - per_page (int): the number of references to output.
     *   - sort_by (string): "alphabetic" (default), "total", "values", or any
     *     available column in the table of the database. For values, they
     *     should be set as filters values.
     *   - sort_order (string): "asc" (default) or "desc".
     *   - filters (array): Limit values to the specified data. The passed
     *     settings may be a string separated by "|" (recommended) or ",", that
     *     will be exploded with the separator "|" if present, else ",".
     *     - languages (array): list of languages. Values without language are
     *       defined with a null or the string "null" (the empty string "" is
     *       deprecated). It is recommended to append the empty language when a
     *       language is set. This option is used only for properties.
     *     - main_types (array): array with "literal", "resource", or "uri".
     *       Filter property values according to the main data type. Default is
     *       to search all data types.
     *     - data_types (array): Filter property values according to the data
     *       types. Default datatypes are "literal", "resource", "resource:item",
     *       "resource:itemset", "resource:media" and "uri". Data types from
     *       other modules are managed too.
     *       Warning: "resource" is not the same than specific resources.
     *       Use module Bulk Edit or Easy Admin to specify all resources
     *       automatically.
     *     - values (array): Allow to limit the answer to the specified values,
     *       for example a short list of keywords.
     *     - begin (array): Filter property values that begin with these
     *       strings, generally one or more initials.
     *     - end (array): Filter property values that end with these strings.
     * - Options for output:
     *   - first (bool): Append the id of the first resource (default false).
     *   - list_by_max (int): 0 (default), or the max number of resources for
     *     each reference. The max number should be below 1024, that is the hard
     *     coded database limit of mysql and mariadb for function group_concat.
     *   - fields (array): the fields to use for the list of resources, if any.
     *     If not set, the output is an associative array with id as key and
     *     title as value. If set, value is an array of the specified fields.
     *   - initial (int): If set and not 0 or false, append the specified first
     *     letters of each result. It is useful for example to extract years
     *     from iso 8601 dates.
     *   - distinct (bool): Distinct values by type (default false).
     *   - data_type (bool): Include the data type of values (default false).
     *   - lang (bool): Include the language of value (default false).
     *   - locale (string|array): Allow to get the returned values in the
     *     specified languages when a property has translated values. Use "null"
     *     to get a value without language.
     *     Unlike Omeka core, it get the translated title of linked resources.
     *   - include_without_meta (bool): Include the total of resources with no
     *     metadata (default false).
     *   - single_reference_format (bool): Use the old output format without the
     *     deprecated warning for single references without named key.
     *   - output (string): "list" (default), "associative" or "values". When
     *     options "first", "list_by_max", "initial", "distinct", "datatype", or
     *     "lang" are used, the output is forced to "list".
     * Some options and some combinations are not managed for some metadata.
     * @return array Associative array with total and first record ids. Unlike
     * controller plugin, when a string is set as metadata, only its references
     * are returned.
     */
    public function list($metadata = null, ?array $query = [], ?array $options = []): array
    {
        $isSingle = !is_array($metadata);
        if ($isSingle) {
            $metadata = ['fields' => $metadata];
        }
        $result = $this->references->__invoke($metadata, $query, $options)->list();
        return $isSingle && $result
            ? reset($result)
            : $result;
    }

    /**
     * Count the total of distinct element texts for terms.
     *
     * If total is not correct, reindex the references in main settings.
     *
     * @uses \Reference\Mvc\Controller\Plugin\References::count()
     * Unlike References::count(), it has arguments and may return an integer.
     *
     * @param string|array $metadata
     * @param array $query
     * @param array $options
     * @return int|array The total or an associative array with the metadata and the total.
     */
    public function count($metadata = null, ?array $query = [], ?array $options = [])
    {
        $isSingle = !is_array($metadata);
        if ($isSingle) {
            $metadata = ['fields' => $metadata];
        }
        $result = $this->references->__invoke($metadata, $query, $options)->count();
        return $isSingle
            ? ($result ? reset($result) : 0)
            : $result;
    }

    /**
     * Get the initials (first or more characters) of values for a field.
     *
     * The filter "begin" is skipped from the query.
     *
     * @uses \Reference\Mvc\Controller\Plugin\References::initials()
     *
     * @param string|array $metadata
     * @param array $query
     * @param array $options The option "initial" allows to set the number of
     *   characters by "initial" (default 1).
     * @return array The list of initials for each field.
     */
    public function initials($metadata = null, ?array $query = [], ?array $options = []): array
    {
        $isSingle = !is_array($metadata);
        if ($isSingle) {
            $metadata = ['fields' => $metadata];
        }
        $result = $this->references->__invoke($metadata, $query, $options)->initials();
        return $isSingle && $result
            ? reset($result)
            : $result;
    }

    /**
     * Display list of references of one or more fields via a template.
     *
     * @uses \Reference\Mvc\Controller\Plugin\References::list()
     *
     * @param array $fields
     * @param array $query An Omeka search query to limit results.
     * @param array $options Same options than list(), and specific ones for
     * the display:
     * - template (string): the template to use (default: "common/reference")
     * - raw (bool): Show references as raw text, not links (default to false)
     * - raw_sub (bool): Show sub references as raw text, not links (default to false)
     * - search_config (string): Link to browse or search engine slug (module
     *   Advanced Search)
     * - link_to_single (bool): When there is one result for a term, link it
     *   directly to the resource, and not to the list page (default to config)
     * - custom_url (bool): with modules such Clean Url or Ark, use the url
     *   generator instad the standard item/id. May slow the display when there
     *   are many single references
     * - thumbnail (string): display the thumbnail of the first reference
     * - skiplinks (bool): Add the list of letters at top and bottom of the page
     * - headings (bool): Add each letter as headers
     * - subject_property (string|int): property to use for second level list
     * @return string Html list.
     */
    public function displayListForFields($fields, ?array $query = [], ?array $options = []): string
    {
        $query = $query ?: [];
        $options = $options ?: [];

        $options['initial'] = @$options['initial'] || @$options['skiplinks'] || @$options['headings'];
        $options['first'] = @$options['first'] || @$options['link_to_single'];

        // Add first id if there is a property for subject values.
        $firstId = $options['first'];
        unset($options['subject_property_id'], $options['subject_property_term']);
        if (!empty($options['subject_property'])) {
            $api = $this->getView()->api();
            $property = is_numeric($options['subject_property'])
                ? $api->read('properties', ['id' => $options['subject_property']])->getContent()
                : $api->searchOne('properties', ['term' => $options['subject_property']])->getContent();
            if ($property) {
                $options['first'] = true;
                $options['subject_property'] = [
                    'id' => $property->id(),
                    'term' => $property->term(),
                ];
            } else {
                unset($options['subject_property']);
            }
        }

        // To display a thumbnail, the first resource is needed.
        $options['first'] = @$options['first'] || @$options['thumbnail'];

        $ref = $this->references->__invoke($fields, $query, $options);
        $list = $ref->list();
        $initials = $options['initial'] ? $ref->initials() : [];

        $options = $ref->getOptions() + $options;

        // Keep original option for key first.
        $options['first'] = $firstId;

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $plugins = $this->getView()->getHelperPluginManager();
        if (!empty($options['search_config']) && $plugins->has('getSearchConfig')) {
            $options['search_config'] = $plugins->get('getSearchConfig')($options['search_config'] === 'default' ? null : $options['search_config']);
            $options['search_config'] = $options['search_config'] ? $options['search_config']->slug() : null;
        } else {
            $options['search_config'] = null;
        }

        $template = empty($options['template']) ? 'common/reference' : $options['template'];
        unset($options['template']);

        $html = '';
        foreach ($list as $keyField => $result) {
            if (empty($fields[$keyField])) {
                continue;
            }
            $html .= $this->getView()->partial($template, [
                'currentField' => [$keyField => $fields[$keyField]],
                'query' => $query,
                'options' => $options,
                'request' => $result['o:request'] ?? [],
                'references' => $result['o:references'] ?? [],
                'initials' => $options['initial'] ? $initials[$keyField]['o:references'] ?? [] : [],
                // Kept for compatibility of old themes.
                'first' => $result['o:request']['o:field'][0] ?? ['o:id' => null, 'o:term' => null, '@type' => null],
                'term' => $result['o:request']['o:field'][0]['o:term'] ?? null,
            ]);
        }
        return $html;
    }

    /**
     * Display the list of the references of a term or a template via a partial view.
     *
     * @deprecated Use displayListForFields() instead. Will be removed in a next release.
     * @see \Reference\View\Helper\References::displayListForFields().
     */
    public function displayListForTerm($term, ?array $query = [], ?array $options = []): string
    {
        return $this->displayListForFields(['fields' => $term], $query, $options);
    }

    /**
     * Get the prepared tree of values from an array or a dash tree.
     *
     * @uses \Reference\Mvc\Controller\Plugin\ReferenceTree::getTree()
     *
     * @param array|string $referenceLevels
     * @param array $query
     * @param array $options
     * @return array
     */
    public function tree($referenceLevels, ?array $query = [], ?array $options = []): array
    {
        return $this->referenceTree->getTree($referenceLevels, $query, $options);
    }

    /**
     * Display the tree of subjects via a partial view.
     *
     * @link http://www.jqueryscript.net/other/jQuery-Flat-Folder-Tree-Plugin-simplefolders.html
     * @uses \Reference\Mvc\Controller\Plugin\ReferenceTree::getTree()
     *
     * Note: Sql searches are case insensitive, so the all the values must be
     * case-insisitively unique.
     *
     * Output via the default partial:
     *
     * ```html
     * <ul class="tree">
     *     <li>Europe
     *         <div class="expander"></div>
     *         <ul>
     *             <li>France
     *                 <div class="expander"></div>
     *                 <ul>
     *                     <li>Paris</li>
     *                 </ul>
     *             </li>
     *             <li>United Kingdom
     *                 <div class="expander"></div>
     *                 <ul>
     *                     <li>England
     *                         <div class="expander"></div>
     *                         <ul>
     *                             <li>London</li>
     *                         </ul>
     *                     </li>
     *                     <li>Scotland</li>
     *                 </ul>
     *             </li>
     *         </ul>
     *     </li>
     *     <li>Asia
     *         <div class="expander"></div>
     *         <ul>
     *             <li>Japan</li>
     *         </ul>
     *     </li>
     * </ul>
     * ```
     *
     * @param array|string $referenceLevels References and levels to show as
     * array or dash tree.
     * @param array $query An Omeka search query to limit results. It is used in
     *   for urls in the tree too.
     * @param array $options Options to display the references.
     * - template (string): the template to use (default: "common/reference-tree")
     * - term (string): Term or id to search (dcterms:subject by default).
     * - type (string): "properties" (default), "resource_classes", "item_sets"
     *   "resource_templates".
     * - resource_name: items (default), "item_sets", "media", "resources".
     * - branch: Managed terms are branches (path separated with " :: ")
     * - raw (bool): Show references as raw text, not links (default to false)
     * - search_config (string): Link to browse or search engine slug (module
     *   Advanced Search)
     * - thumbnail (string): display the thumbnail of the first reference
     * - link_to_single (bool): When there is one result for a term, link it
     *   directly to the resource, and not to the list page (default to config)
     * - custom_url (bool): with modules such Clean Url or Ark, use the url
     *   generator instad the standard item/id. May slow the display when there
     *   are many single references
     * - expanded (bool) : Show tree as expanded (default to config)
     * @return string Html list.
     */
    public function displayTree($referenceLevels, ?array $query = [], ?array $options = []): string
    {
        $default = [
            'fields' => [
                'dcterms:subject',
            ],
            'type' => 'properties',
            'resource_name' => 'items',
            'branch' => null,
            'raw' => false,
            'search_config' => '',
            'thumbnail' => '',
            'link_to_single' => null,
            'custom_url' => false,
            'expanded' => null,
        ];
        $options = $options ? $options + $default : $default;
        $options['first'] = $options['link_to_single'] || $options['custom_url'];
        $options['initial'] = false;

        // To display a thumbnail, the first resource is needed.
        $firstId = $options['first'];
        $options['first'] = @$options['first'] || @$options['thumbnail'];

        $result = $this->tree($referenceLevels, $query, $options);

        $options['first'] = $firstId;

        /** @var \AdvancedSearch\Api\Representation\SearchConfigRepresentation $searchConfig */
        $plugins = $this->getView()->getHelperPluginManager();
        if (!empty($options['search_config']) && $plugins->has('getSearchConfig')) {
            $options['search_config'] = $plugins->get('getSearchConfig')($options['search_config'] === 'default' ? null : $options['search_config']);
            $options['search_config'] = $options['search_config'] ? $options['search_config']->slug() : null;
        } else {
            $options['search_config'] = null;
        }

        $template = empty($options['template']) ? 'common/reference-tree' : $options['template'];
        unset($options['template']);

        return $this->getView()->partial($template, [
            'references' => $result,
            'options' => $options,
        ]);
    }
}
