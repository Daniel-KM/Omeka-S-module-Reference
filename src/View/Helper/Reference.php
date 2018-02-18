<?php
namespace Reference\View\Helper;

use Omeka\Api\Representation\PropertyRepresentation;
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
     * Get the reference view object.
     *
     * @param int|string|PropertyRepresentation $property
     * @param string $resourceName All resources types if empty.
     * @param int $perPage
     * @param int $page One-based page number.
     * @return Reference|array|null The result or null if called directly, else
     * this view helper.
     */
    public function __invoke($property = null, $resourceName = null, $perPage = null, $page = null)
    {
        if (empty($property)) {
            return $this;
        }
        return $this->reference->asList($property, $resourceName, $perPage, $page);
    }

    /**
     * Get the list of categories as an associative array with totals.
     *
     * @param int|string|PropertyRepresentation $property
     * @param string $resourceName All resources types if empty.
     * @param int $perPage
     * @param int $page One-based page number.
     * @return array|null
     */
    public function asList($property = null, $resourceName = null, $perPage = null, $page = null)
    {
        return $this->reference->asList($property, $resourceName, $perPage, $page);
    }

    /**
     * Count the total of distinct element texts for a slug.
     *
     * @todo Manage multiple resource names (items, item sets, medias) at once.
     *
     * @param string $slug
     * @param string $resourceName
     * @return int The number of references if only one resource name is set.
     */
    public function count($slug, $resourceName = null)
    {
        return $this->reference->count($slug, $resourceName);
    }

    /**
     * Display the list of references via a partial view.
     *
     * @param array $references Array of references elements to show.
     * @param array $options Options to display references. Values are booleans:
     * - raw: Show references as raw text, not links (default to false)
     * - strip: Remove html tags (default to true)
     * - skiplinks: Add the list of letters at top and bottom of the page
     * - headings: Add each letter as headers
     * @return string Html list.
     */
    public function displayList($references, array $options = [])
    {
        return $this->reference->displayList($references, $options);
    }

    /**
     * Display the tree of subjects via a partial view.
     *
     * @uses http://www.jqueryscript.net/other/jQuery-Flat-Folder-Tree-Plugin-simplefolders.html
     *
     *  Example for the mode "tree":
     * @example
     * $references = "
     * Europe
     * - France
     * - Germany
     * - United Kingdom
     * -- England
     * -- Scotland
     * -- Wales
     * Asia
     * - Japan
     * ";
     *
     * $hierarchy = "
     * <ul class="tree">
     *     <li>Europe
     *         <div class="expander"></div>
     *         <ul>
     *             <li>France</li>
     *             <li>Germany</li>
     *             <li>United Kingdom
     *                 <div class="expander"></div>
     *                 <ul>
     *                     <li>England</li>
     *                     <li>Scotland</li>
     *                     <li>Wales</li>
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
     * ";
     *
     * @param array $references Array of subjects elements to show.
     * @param array $options Options to display the references. Values are booleans:
     * - raw: Show subjects as raw text, not links (default to false)
     * - strip: Remove html tags (default to true)
     * - expanded: Show tree as expanded (defaul to config)
     * @return string Html list.
     */
    public function displayTree($subjects, array $options = [])
    {
        return $this->reference->displayTree($subjects, $options);
    }
}
