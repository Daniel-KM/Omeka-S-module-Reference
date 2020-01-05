<?php
namespace Reference\View\Helper;

use Reference\Mvc\Controller\Plugin\References as ReferencesPlugin;
use Zend\View\Helper\AbstractHelper;

class References extends AbstractHelper
{
    /**
     * @param ReferencesPlugin
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
     * - link_to_single: false (default, always as a list), or true (direct when
     *   there is only one resource).
     * - initial: false (default), or true (get first letter of each result).
     * - values: array Allow to limit the answer to the specified values.
     * - include_without_meta: false (default), or true (include total of
     *   resources with no metadata).
     * - output: "associative" (default), "list", or "withFirst".
     * Some options and some combinations are not managed for some metadata.
     * @return array Associative array with total and first record ids.
     */
    public function list(array $metadata = null, array $query = null, array $options = null)
    {
        $ref = $this->references;
        return $ref($metadata, $query, $options)->list();
    }

    /**
     * Count the total of distinct element texts for terms.
     *
     * @see \Reference\View\Helper\Reference::list()
     *
     * @param array $metadata
     * @param array $query
     * @param array $options
     * @return array Associative array with the metadata and the total.
     */
    public function count(array $metadata = null, array $query = null, array $options = null)
    {
        $ref = $this->references;
        return $ref($metadata, $query, $options)->count();
    }

    /**
     * Display the list of the references of a term or a template via a partial view.
     *
     * @see \Reference\View\Helper\Reference::list()
     *
     * @param string $term
     * @param array $query
     * @param array $options Same options than list(), and specific ones for the
     * display (booleans):
     * - raw: Show references as raw text, not links (default to false)
     * - link_to_single: When there is one result for a term, link it directly
     *   to the resource, and not to the list page (default to config)
     * - custom_url: with modules such Clean Url or Ark, use the url generator
     *   instad the standard item/id. May slow the display when there are many
     *   single references
     * - skiplinks: Add the list of letters at top and bottom of the page
     * - headings: Add each letter as headers
     * @return string Html list.
     */
    public function displayListForTerm($term, array $query = null, array $options = null)
    {
        // Skip option output.
        $options['output'] = @$options['link_to_single'] ? 'withFirst' : 'list';
        $options['initial'] = @$options['initial'] || @$options['skiplinks'] || @$options['headings'];

        $ref = $this->references;
        $ref = $ref([$term], $query, $options);

        $options = $ref->getOptions() + $options;

        $types = [
            'properties' => 'properties',
            'resource_classes' => 'resource_classes',
            'resource_templates' => 'resource_templates',
            'item_sets' => 'item_sets',
            'o:property' => 'properties',
            'o:resource_class' => 'resource_classes',
            'o:resource_template' => 'resource_templates',
            'o:item_set' => 'item_sets',
        ];
        $type = $types[$options['type']];

        $list = $ref->list();
        $list = reset($list);

        return $this->getView()->partial('common/reference', [
            'references' => $list['o-module-reference:values'],
            'termId' => @$list['o:id'],
            'term' => $term,
            'label' => $list['o:label'],
            'type' => $type,
            'resourceName' => $options['resource_name'],
            'options' => $options,
            'order' => [$options['sort_by'] => $options['sort_order']],
            'query' => $query,
            'perPage' => $options['per_page'],
            'page' => $options['page'],
        ]);
    }
}
