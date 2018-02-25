<?php
namespace Reference\View\Helper;

use Omeka\Api\Representation\PropertyRepresentation;
use Omeka\Api\Representation\ResourceClassRepresentation;
use Reference\Mvc\Controller\Plugin\Reference as ReferencePlugin;
use Zend\View\Helper\AbstractHelper;

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
     * @param int|string|PropertyRepresentation|ResourceClassRepresentation $term
     * @param string $type "properties" (default) or "resource_classes".
     * @param string $resourceName All resources types if empty.
     * @param int $perPage
     * @param int $page One-based page number.
     * @return Reference|array|null The result or null if called directly, else
     * this view helper.
     */
    public function __invoke($term = null, $type = null, $resourceName = null, $perPage = null, $page = null)
    {
        if (empty($term)) {
            return $this;
        }
        return $this->reference->getList($term, $type, $resourceName, $perPage, $page);
    }

    /**
     * Get the list of references of a property or a resource class.
     *
     * @param int|string|PropertyRepresentation|ResourceClassRepresentation $term
     * @param string $type "properties" (default) or "resource_classes".
     * @param string $resourceName
     * @param int $perPage
     * @param int $page One-based page number.
     * @return array Associative array with total and first record ids.
     */
    public function getList($term, $type = null, $resourceName = null, $perPage = null, $page = null)
    {
        return $this->reference->getList($term, $type, $resourceName, $perPage, $page);
    }

    /**
     * Get a list of references as tree.
     *
     * @deprecated 3.4.5 Useless since tree is stored as array.
     *
     * @param string $references The default one if null.
     * @return array.
     */
    public function getTree($references = null)
    {
        return $this->reference->getTree($references);
    }

    /**
     * Count the total of distinct element texts for a term.
     *
     * @todo Manage multiple resource names (items, item sets, medias) at once.
     *
     * @param int|string|PropertyRepresentation|ResourceClassRepresentation $term
     * @param string $type "properties" (default) or "resource_classes".
     * @param string $resourceName
     * @return int The number of references if only one resource name is set.
     */
    public function count($term, $type = null, $resourceName = null)
    {
        return $this->reference->count($term, $type, $resourceName);
    }

    /**
     * Display the list of the references of a term via a partial view.
     *
     * @param int|string|PropertyRepresentation|ResourceClassRepresentation $term
     * @param array $args Specify the references with "type", "resource_name",
     * "per_page" and "page".
     * @param array $options Options to display references. Values are booleans:
     * - raw: Show references as raw text, not links (default to false)
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
     *
     * @param array $referenceLevels Flat associative array of references to
     * show with reference as key and level as value.
     * @param array $args Specify the references with "term" (dcterms:subject by
     * default), "type" and "resource_name"
     * @param array $options Options to display the references. Values are booleans:
     * - raw: Show subjects as raw text, not links (default to false)
     * - expanded: Show tree as expanded (defaul to config)
     * @return string Html list.
     */
    public function displayTree($references, array $args, array $options = [])
    {
        return $this->reference->displayTree($references, $args, $options);
    }
}
