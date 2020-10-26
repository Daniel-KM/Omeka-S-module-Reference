<?php declare(strict_types=1);
namespace Reference\View\Helper;

use Reference\Mvc\Controller\Plugin\References as ReferencesPlugin;
use Zend\View\Helper\AbstractHelper;

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
     * @param array $metadata Classes, properties terms, template names, or
     * other Omeka metadata names.
     * @param array $query An Omeka search query.
     * @param array $options Options for output.
     * - resource_name: items (default), "item_sets", "media", "resources".
     * - sort_by: "alphabetic" (default), "count", or any available column.
     * - sort_order: "asc" (default) or "desc".
     * - filters: array Limit values to the specified data. Currently managed:
     *   - "languages": list of languages. Values without language are returned
     *     with the empty value "". This option is used only for properties.
     *   - "datatypes": array Filter property values according to the data types.
     *     Default datatypes are "literal", "resource", "resource:item", "resource:itemset",
     *     "resource:media" and "uri".
     *     Warning: "resource" is not the same than specific resources.
     *     Use module Bulk Edit or Bulk Check to specify all resources automatically.
     * - values: array Allow to limit the answer to the specified values.
     * - first: false (default), or true (get first resource).
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
    public function list($metadata = null, array $query = null, array $options = null)
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
     * @see \Reference\View\Helper\Reference::list()
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
     * @see \Reference\View\Helper\Reference::list()
     *
     * @param string $term
     * @param array $query
     * @param array $options Same options than list(), and specific ones for
     * the display:
     * - template (string): the template to use (default: "common/reference")
     * - raw (bool): Show references as raw text, not links (default to false)
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
    public function displayListForTerm($term, array $query = null, array $options = null)
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

        $ref = $this->references;
        $list = $ref([$term], $query, $options)->list();

        $first = reset($list);
        $options = $ref->getOptions() + $options;

        // Keep original option for key first.
        $options['first'] = $firstId;

        $list = $first['o-module-reference:values'];
        unset($first['o-module-reference:values']);

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
}
