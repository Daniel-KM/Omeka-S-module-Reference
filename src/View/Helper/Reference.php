<?php
namespace Reference\View\Helper;

use Omeka\Api\Representation\PropertyRepresentation;
use Omeka\Api\Representation\ResourceClassRepresentation;
use Reference\Mvc\Controller\Plugin\Reference as ReferencePlugin;
use Zend\View\Helper\AbstractHelper;

/**
 * @deprecated Use \Reference\View\Helper\References instead.
 */
class Reference extends AbstractHelper
{
    /**
     * @param ReferencePlugin
     */
    protected $reference;

    /**
     * @param ReferencePlugin $reference
     */
    public function __construct(ReferencePlugin $reference)
    {
        $this->reference = $reference;
    }

    /**
     * Get the reference object.
     *
     * @param int|string|PropertyRepresentation|ResourceClassRepresentation|null $term
     * @param string $type "properties" (default), "resource_classes", "item_sets", or
     * "resource_templates".
     * @param string $resourceName All resources types if empty. For item sets,
     * it is always "items".
     * @param array $order Sort and direction: ['alphabetic' => 'ASC'] (default),
     * ['count' => 'DESC'], or any available column as sort.
     * @param array $query An api search formatted query to limit results.
     * @param int $perPage
     * @param int $page One-based page number.
     * @return Reference|array|null The result or null if called directly, else
     * this helper.
     */
    public function __invoke(
         $term = null,
         $type = null,
         $resourceName = null,
         $order = null,
         array $query = null,
         $perPage = null,
         $page = null
     ) {
        if (is_null($term)) {
            return $this;
        }
        return $this->reference->getList($term, $type, $resourceName, $order, $query, $perPage, $page);
    }

    /**
     * Get the list of references of a property or a resource class.
     *
     * @param int|string|PropertyRepresentation|ResourceClassRepresentation $term
     * @param string $type "properties" (default), "resource_classes", "item_sets", or
     * "resource_templates".
     * @param string $resourceName All resources types if empty. For item sets,
     * it is always "items".
     * @param array $order Sort and direction: ['alphabetic' => 'ASC'] (default),
     * ['count' => 'DESC'], or any available column as sort.
     * @param array $query An api search formatted query to limit results.
     * @param int $perPage
     * @param int $page One-based page number.
     * @return array Associative array with total and first record ids.
     */
    public function getList($term, $type = null, $resourceName = null, $order = null, array $query = null, $perPage = null, $page = null)
    {
        return $this->reference->getList($term, $type, $resourceName, $order, $query, $perPage, $page);
    }

    /**
     * Count the total of distinct element texts for a term.
     *
     * @todo Manage multiple resource names (items, item sets, medias) at once.
     *
     * @param int|string|PropertyRepresentation|ResourceClassRepresentation $term
     * @param string $type "properties" (default), "resource_classes", "item_sets", or
     * "resource_templates".
     * @param string $resourceName All resources types if empty. For item sets,
     * it is always "items".
     * @param array $query An api search formatted query to limit results.
     * @return int The number of references if only one resource name is set.
     */
    public function count($term, $type = null, $resourceName = null, array $query = null)
    {
        return $this->reference->count($term, $type, $resourceName, $query);
    }

    /**
     * Display the list of the references of a term via a partial view.
     *
     * @param int|string|PropertyRepresentation|ResourceClassRepresentation $term
     * @param array $args Specify the references with "type", "resource_name",
     * "order", "query", "per_page" and "page".
     * @param array $options Options to display references. Values are booleans:
     * - raw: Show references as raw text, not links (default to false)
     * - link_to_single: When there is one result for a term, link it directly
     * to the resource, and not to the list page (default to config)
     * - custom_url: with modules such Clean Url or Ark, use the url generator
     * instad the standard item/id. May slow the display when there are many
     * single references
     * - skiplinks: Add the list of letters at top and bottom of the page
     * - headings: Add each letter as headers
     * @return string Html list.
     */
    public function displayListForTerm($term, array $args = [], array $options = [])
    {
        return $this->reference->displayListForTerm($term, $args, $options);
    }

    /**
     * Display the tree of subjects via a partial view.
     *
     * @uses http://www.jqueryscript.net/other/jQuery-Flat-Folder-Tree-Plugin-simplefolders.html
     *
     * @see \Reference\Mvc\Controller\Plugin\Reference::convertTreeToLevels()
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
     * @param array $referenceLevels References and levels to show.
     * @param array $args Specify the references with "term" (dcterms:subject by
     * default), "type", "resource_name", and "query"
     * @param array $options Options to display the references. Values are booleans:
     * - raw: Show subjects as raw text, not links (default to false)
     * - link_to_single: When there is one result for a term, link it directly
     * to the resource, and not to the list page (default to config)
     * - custom_url: with modules such Clean Url or Ark, use the url generator
     * instad the standard item/id. May slow the display when there are many
     * single references
     * - branch: The managed terms are branches (with the path separated with
     * " :: " (default to config)
     * - expanded: Show tree as expanded (default to config)
     * @return string Html list.
     */
    public function displayTree($references, array $args, array $options = [])
    {
        return $this->reference->displayTree($references, $args, $options);
    }
}
