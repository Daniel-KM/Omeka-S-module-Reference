<?php declare(strict_types=1);

namespace Reference\View\Helper;

use Laminas\View\Helper\AbstractHelper;
use Reference\Mvc\Controller\Plugin\References as ReferencesPlugin;

class References extends AbstractHelper
{
    /**
     * @var ReferencesPlugin
     */
    protected $references;

    /**
     * @param ReferencesPlugin $references
     */
    public function __construct(ReferencesPlugin $references)
    {
        $this->references = $references;
    }

    /**
     * Get the references.
     *
     * @uses \Reference\Mvc\Controller\Plugin\References
     *
     * @return self
     */
    public function __invoke(): self
    {
        return $this;
    }

    /**
     * Stringify to an empty string.
     *
     *  The default result force to specify another methods.
     *
     * @return string
     */
    public function __toString(): string
    {
        return '';
    }

    /**
     * Get the references.
     *
     * @uses \Reference\Mvc\Controller\Plugin\References::list()
     *
     * @param array $metadata Classes, properties terms, template names, or
     * other Omeka metadata names.
     * @param array $query An Omeka search query.
     * @param array $options Options for output.
     * - resource_name: items (default), "item_sets", "media", "resources".
     * - sort_by: "alphabetic" (default), "total", or any available column.
     * - sort_order: "asc" (default) or "desc".
     * - filters: array Limit values to the specified data. Currently managed:
     *   - "languages": list of languages. Values without language are returned
     *     with the empty value "". This option is used only for properties.
     *   - "datatypes": array Filter property values according to the data types.
     *     Default datatypes are "literal", "resource", "resource:item", "resource:itemset",
     *     "resource:media" and "uri".
     *     Warning: "resource" is not the same than specific resources.
     *     Use module Bulk Edit or Bulk Check to specify all resources automatically.
     *   - "begin": array Filter property values that begin with these strings,
     *     generally one or more initials.
     *   - "end": array Filter property values that end with these strings.
     * - values: array Allow to limit the answer to the specified values.
     * - first: false (default), or true (get first resource).
     * - list_by_max: 0 (default), or the max number of resources for each reference)
     *   The max number should be below 1024 (mysql limit for group_concat).
     * - fields: the fields to use for the list of resources, if any. If not
     *   set, the output is an associative array with id as key and title as
     *   value. If set, value is an array of the specified fields.
     * - initial: false (default), or true (get first letter of each result).
     * - distinct: false (default), or true (distinct values by type).
     * - datatype: false (default), or true (include datatype of values).
     * - lang: false (default), or true (include language of value to result).
     * TODO Check if the option include_without_meta is still needed with data types.
     * - include_without_meta: false (default), or true (include total of
     *   resources with no metadata).
     * - output: "list" (default) or "associative" (possible only without added
     *   options: first, initial, distinct, datatype, or lang).
     * Some options and some combinations are not managed for some metadata.
     * @return array Associative array with total and first record ids.
     */
    public function list($metadata = null, array $query = null, array $options = null): array
    {
        $ref = $this->references;
        $isSingle = is_string($metadata);
        if ($isSingle) {
            $metadata = [$metadata];
        }
        $list = $ref($metadata, $query, $options)->list();
        return $isSingle ? reset($list) : $list;
    }

    /**
     * Count the total of distinct element texts for terms.
     *
     * @uses \Reference\Mvc\Controller\Plugin\References::count()
     *
     * @param string|array $metadata
     * @param array $query
     * @param array $options
     * @return int|array The total or an associative array with the metadata and the total.
     */
    public function count($metadata = null, array $query = null, array $options = null)
    {
        $ref = $this->references;
        $isSingle = !is_array($metadata);
        if ($isSingle) {
            $metadata = [$metadata];
        }
        $count = $ref($metadata, $query, $options)->count();
        return $isSingle ? reset($count) : $count;
    }

    /**
     * Display the list of the references of a term or a template via a partial view.
     *
     * @uses \Reference\Mvc\Controller\Plugin\References::list()
     *
     * @param string $term
     * @param array $query An Omeka search query to limit results.
     * @param array $options Same options than list(), and specific ones for
     * the display:
     * - template (string): the template to use (default: "common/reference")
     * - raw (bool): Show references as raw text, not links (default to false)
     * - raw_sub (bool): Show sub references as raw text, not links (default to false)
     * - link_to_single (bool): When there is one result for a term, link it
     *   directly to the resource, and not to the list page (default to config)
     * - custom_url (bool): with modules such Clean Url or Ark, use the url
     *   generator instad the standard item/id. May slow the display when there
     *   are many single references
     * - skiplinks (bool): Add the list of letters at top and bottom of the page
     * - headings (bool): Add each letter as headers
     * - subject_property (string|int): property to use for second level list
     * @return string Html list.
     */
    public function displayListForTerm($term, array $query = null, array $options = null): string
    {
        // Skip option output.
        if (!$options) {
            $options = [];
        }
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

        $ref = $this->references->__invoke([$term], $query, $options);
        $list = $ref->list();

        $first = reset($list);
        $options = $ref->getOptions() + $options;

        // Keep original option for key first.
        $options['first'] = $firstId;

        $list = $first['o:references'];
        unset($first['o:references']);

        $template = empty($options['template']) ? 'common/reference' : $options['template'];
        unset($options['template']);

        return $this->getView()->partial($template, [
            'term' => $term,
            'query' => $query,
            'options' => $options,
            'field' => $first,
            'references' => $list,
        ]);
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
    public function tree($referenceLevels, array $query = null, array $options = null): array
    {
        return $this->references->getController()->referenceTree()->getTree($referenceLevels, $query, $options);
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
     * - template (string): the template to use (default: "common/reference")
     * - term (string): Term or id to search (dcterms:subject by default).
     * - type (string): "properties" (default), "resource_classes", "item_sets"
     *   "resource_templates".
     * - resource_name: items (default), "item_sets", "media", "resources".
     * - branch: Managed terms are branches (path separated with " :: ")
     * - raw (bool): Show references as raw text, not links (default to false)
     * - link_to_single (bool): When there is one result for a term, link it
     *   directly to the resource, and not to the list page (default to config)
     * - custom_url (bool): with modules such Clean Url or Ark, use the url
     *   generator instad the standard item/id. May slow the display when there
     *   are many single references
     * - expanded (bool) : Show tree as expanded (default to config)
     * @return string Html list.
     */
    public function displayTree($referenceLevels, array $query = null, array $options = null): string
    {
        $default = [
            'term' => 'dcterms:subject',
            'type' => 'properties',
            'resource_name' => 'items',
            'branch' => null,
            'raw' => false,
            'link_to_single' => null,
            'custom_url' => false,
            'expanded' => null,
        ];
        $options = $options ? $options + $default : $default;
        $options['first'] = $options['link_to_single'] || $options['custom_url'];
        $options['initial'] = false;

        $result = $this->tree($referenceLevels, $query, $options);

        $template = empty($options['template']) ? 'common/reference-tree' : $options['template'];
        unset($options['template']);

        return $this->getView()->partial($template, [
            'references' => $result,
            'options' => $options,
        ]);
    }
}
