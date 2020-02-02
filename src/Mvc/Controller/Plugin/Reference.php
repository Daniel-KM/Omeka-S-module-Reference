<?php
namespace Reference\Mvc\Controller\Plugin;

use Omeka\Api\Representation\PropertyRepresentation;
use Omeka\Api\Representation\ResourceClassRepresentation;
use Omeka\Mvc\Controller\Plugin\Api;
use Reference\Mvc\Controller\Plugin\References as ReferencesPlugin;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

/**
 * @deprecated Use \Reference\Mvc\Controller\Plugin\References and \Reference\View\Helper\References instead.
 */
class Reference extends AbstractPlugin
{
    /**
     * @var int
     */
    protected $DC_Title_id = 1;

    /**
     * @var int
     */
    protected $DC_Subject_id = 3;

    /**
     * @param Api
     */
    protected $api;

    /**
     * @param ReferencesPlugin
     */
    protected $references;

    /**
     * @param Api $api
     * @param ReferencesPlugin $references
     */
    public function __construct(
        Api $api,
        ReferencesPlugin $references
    ) {
        $this->api = $api;
        $this->references = $references;
    }

    /**
     * Get the reference object.
     *
     * @todo Manage admin references.
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
     * @return Reference|array|null The result or null if called directly, else
     * this plugin.
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
        return $this->getList($term, $type, $resourceName, $order, $query, $perPage, $page);
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
        $type = $this->getType($type);

        $entityClass = $this->mapResourceNameToEntity($resourceName);
        if (empty($entityClass)) {
            return [];
        }

        $termId = $this->getTermId($term, $type);
        if ($termId === 0) {
            return [];
        }

        return $this->getReferencesList($termId, $type, $entityClass, $order, $query, [], $perPage, $page, null, false, false);
    }

    /**
     * Convert a tree from string format to an array of texts with level.
     *
     * Example of a dash tree:
     *
     * Europe
     * - France
     * -- Paris
     * - United Kingdom
     * -- England
     * --- London
     * -- Scotland
     * Asia
     * - Japan
     *
     * Converted into:
     *
     * [
     *     0 => [Europe => 0],
     *     1 => [France => 1]
     *     2 => [Paris => 2]
     *     3 => United Kingdom => 1]
     *     4 => [England => 2]
     *     5 => [London => 3]
     *     6 => [Scotland => 2]
     *     7 => [Asia => 0]
     *     8 => [Japan => 1]
     * ]
     *
     * @param string $dashTree A tree with levels represented with dashes.
     * @return array Array with an associative array as value, containing text
     * as key and level as value (0 based).
     */
    public function convertTreeToLevels($dashTree)
    {
        // The str_replace() allows to fix Apple copy/paste.
        $values = array_filter(array_map('trim', explode("\n", str_replace(["\r\n", "\n\r", "\r"], ["\n", "\n", "\n"], $dashTree))));
        $levels = array_reduce($values, function ($result, $item) {
            $first = substr($item, 0, 1);
            $space = strpos($item, ' ');
            $level = ($first !== '-' || $space === false) ? 0 : $space;
            $value = trim($level == 0 ? $item : substr($item, $space));
            $result[] = [$value => $level];
            return $result;
        }, []);
        return $levels;
    }

    /**
     * Convert a tree from flat array format to string format
     *
     * @see \Reference\Mvc\Controller\Plugin\Reference::convertTreeToLevels()
     *
     * @param array $levels A flat array with text as key and level as value.
     * @return string
     */
    public function convertLevelsToTree(array $levels)
    {
        $tree = array_map(function ($v) {
            $level = reset($v);
            $term = trim(key($v));
            return $level ? str_repeat('-', $level) . ' ' . $term : $term;
        }, $levels);
        return implode(PHP_EOL, $tree);
    }

    /**
     * Convert a tree from flat array format to string format
     *
     * @see \Reference\Mvc\Controller\Plugin\Reference::convertTreeToFlatLevels()
     *
     * @param array $levels A flat array with text as key and level as value.
     * @return string
     */
    public function convertFlatLevelsToTree(array $levels)
    {
        $tree = array_map(function ($v, $k) {
            return $v ? str_repeat('-', $v) . ' ' . trim($k) : trim($k);
        }, $levels, array_keys($levels));
        return implode(PHP_EOL, $tree);
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
        $type = $this->getType($type);

        $entityClass = $this->mapResourceNameToEntity($resourceName);
        if (empty($entityClass)) {
            return 0;
        }

        $termId = $this->getTermId($term, $type);
        return $this->countReferences($termId, $type, $entityClass, $query);
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
        $type = isset($args['type']) ? $this->getType($args['type']): 'properties';

        $termId = $this->getTermId($term, $type);

        if (isset($args['resource_name'])) {
            $entityClass = $this->mapResourceNameToEntity($args['resource_name']);
            if (empty($entityClass)) {
                return '';
            }
        } else {
            $entityClass = \Omeka\Entity\Resource::class;
        }

        $resourceName = $this->mapEntityToResourceName($entityClass);

        $options = $this->cleanOptions($options);

        $order = empty($args['order']) ? null : $args['order'];
        $query = empty($args['query']) ? null : $args['query'];
        $perPage = empty($args['per_page']) ? null : (int) $args['per_page'];
        $page = empty($args['page']) ? null : (int) $args['page'];
        $output = $options['link_to_single'] ? 'withFirst' : 'list';
        $initial = $options['skiplinks'] || $options['headings'];
        $includeWithoutMeta = $options['include_without_meta'];

        $references = $this->getReferencesList($termId, $type, $entityClass, $order, $query, [], $perPage, $page, $output, $initial, $includeWithoutMeta);

        $controller = $this->getController();
        $partial = $controller->viewHelpers()->get('partial');
        return $partial('common/reference', [
            'references' => $references,
            'termId' => $termId,
            'term' => $term,
            'type' => $type,
            'resourceName' => $resourceName,
            'options' => $options,
            'order' => $order,
            'query' => $query,
            'perPage' => $perPage,
            'page' => $page,
        ]);
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
        if (empty($references)) {
            return '';
        }

        $type = isset($args['type']) && $args['type'] === 'resource_classes' ? 'resource_classes' : 'properties';

        $term = empty($args['term']) ? $this->DC_Subject_id : $args['term'];
        $termId = $this->getTermId($term, $type);
        if (empty($termId) && $type !== 'item_sets') {
            return '';
        }

        if (isset($args['resource_name'])) {
            $entityClass = $this->mapResourceNameToEntity($args['resource_name']);
            if (empty($entityClass)) {
                return '';
            }
        } else {
            $entityClass = \Omeka\Entity\Resource::class;
        }

        $resourceName = $this->mapEntityToResourceName($entityClass);

        $query = empty($args['query']) ? null : $args['query'];

        $options['mode'] = 'tree';
        $options = $this->cleanOptions($options);

        // Sql searches are case insensitive, so a convert should be done.
        $hasMb = function_exists('mb_strtolower');
        $isBranch = $options['branch'];
        $output = $options['link_to_single'] ? 'withFirst' : 'list';
        $initial = false;
        if ($isBranch) {
            $branches = [];
            $lowerBranches = [];
            $levels = [];
            foreach ($references as $referenceLevel) {
                $level = reset($referenceLevel);
                $reference = key($referenceLevel);
                $levels[$level] = $reference;
                $branch = '';
                for ($i = 0; $i < $level; ++$i) {
                    $branch .= $levels[$i] . ' :: ';
                }
                $branch .= $reference;
                $branches[] = $branch;
                $lowerBranches[] = $hasMb ? mb_strtolower($branch) : strtolower($branch);
            }
            $totals = $this->getReferencesList($termId, $type, $entityClass, null, $query, $lowerBranches, null, null, $output, $initial, false);
        }
        // Simple tree.
        else {
            $lowerReferences = $hasMb
                ? array_map(function ($v) {
                    return mb_strtolower(key($v));
                }, $references)
                : array_map(function ($v) {
                    return strtolower(key($v));
                }, $references);
            $totals = $this->getReferencesList($termId, $type, $entityClass, null, $query, $lowerReferences, null, null, $output, $initial, false);
        }

        $lowerValues = [];
        if ($hasMb) {
            foreach ($totals as $value) {
                $key = mb_strtolower($value['val']);
                unset($value['val']);
                $lowerValues[$key] = $value;
            }
        } else {
            foreach ($totals as $value) {
                $key = strtolower($value['val']);
                unset($value['val']);
                $lowerValues[$key] = $value;
            }
        }

        // Merge of the two references arrays.
        $result = [];
        $lowers = $isBranch ? $lowerBranches : $lowerReferences;
        foreach ($references as $key => $referenceLevel) {
            $level = reset($referenceLevel);
            $reference = key($referenceLevel);
            $lower = $lowers[$key];
            if (isset($lowerValues[$lower])) {
                $referenceData = [
                    'total' => $lowerValues[$lower]['total'],
                    'first_id' => $options['link_to_single']
                        ? $lowerValues[$lower]['first']
                        : null,
                ];
            } else {
                $referenceData = [
                    'total' => 0,
                    'first_id' => null,
                ];
            }
            $referenceData['val'] = $reference;
            $referenceData['level'] = $level;
            if ($isBranch) {
                $referenceData['branch'] = $branches[$key];
            }
            $result[] = $referenceData;
        }

        $controller = $this->getController();
        $partial = $controller->viewHelpers()->get('partial');
        return $partial('common/reference-tree', [
            'references' => $result,
            'termId' => $termId,
            'term' => $term,
            'type' => $type,
            'resourceName' => $resourceName,
            'options' => $options,
            'perPage' => null,
            'page' => null,
        ]);
    }

    /**
     * Get list of options.
     */
    protected function cleanOptions($options)
    {
        $settings = $this->getController()->settings();

        $mode = isset($options['mode']) && $options['mode'] === 'tree' ? 'tree' : 'list';

        $cleanedOptions = [
            'mode' => $mode,
            'raw' => isset($options['raw']) && $options['raw'],
        ];

        $cleanedOptions['link_to_single'] = (bool) (isset($options['link_to_single'])
            ? $options['link_to_single']
            : $settings->get('reference_link_to_single'));
        $cleanedOptions['custom_url'] = (bool) (isset($options['custom_url'])
            ? $options['custom_url']
            : $settings->get('custom_url'));
        $cleanedOptions['total'] = (bool) (isset($options['total'])
            ? $options['total']
            : $settings->get('reference_total', true));

        switch ($mode) {
            default:
            case 'list':
                $cleanedOptions['headings'] = (bool) (isset($options['headings'])
                    ? $options['headings']
                    : $settings->get('reference_list_headings'));
                $cleanedOptions['skiplinks'] = (bool) (isset($options['skiplinks'])
                    ? $options['skiplinks']
                    : $settings->get('reference_list_skiplinks'));
                $cleanedOptions['slug'] = empty($options['slug'])
                    ? $this->DC_Subject_id
                    : $options['slug'];
                $cleanedOptions['include_without_meta'] = (bool) (isset($options['include_without_meta'])
                    ? $options['include_without_meta']
                    : $settings->get('reference_include_without_meta'));
                break;

            case 'tree':
                $cleanedOptions['query_type'] = isset($options['query_type'])
                    ? ($options['query_type'] == 'in' ? 'in' : 'eq')
                    : $settings->get('reference_tree_query_type', 'eq');
                $cleanedOptions['branch'] = (bool) (isset($options['branch'])
                    ? $options['branch']
                    : $settings->get('reference_tree_branch', false));
                $cleanedOptions['expanded'] = (bool) (isset($options['expanded'])
                    ? $options['expanded']
                    : $settings->get('reference_tree_expanded', false));
                break;
        }

        return $cleanedOptions;
    }

    /**
     * Get the list of references, the total for each one and the first item.
     *
     * When the type is not a property, a filter is added and the list of
     * titles is returned. If there are multiple title, they are returned all.
     *
     * @param int $termId May be the resource class id.
     * @param string $type "properties" (default), "resource_classes", "item_sets", or
     * "resource_templates".
     * @param string $entityClass All resources types if empty. For item sets,
     * it is always "Item".
     * @param array $order Sort and direction: ['alphabetic' => 'ASC'] (default),
     * ['count' => 'DESC'], or any available column as sort.
     * @param array $query An api search formatted query to limit results.
     * @param array $values Allow to limit the answer to the specified values.
     * @param int $perPage
     * @param int $page One-based page number.
     * @param string $output May be "associative" (default), "list" or "withFirst".
     * @param bool $initial Get initial letter (useful for non-acii references).
     * @param bool $includeWithoutMeta Get total of resources with no metadata.
     * @return array Associative list of references, with the total, the first
     * first record, and the first character, according to the parameters.
     */
    protected function getReferencesList(
        $termId,
        $type,
        $entityClass,
        $order = null,
        array $query = null,
        array $values = [],
        $perPage = null,
        $page = null,
        $output = null,
        $initial = false,
        $includeWithoutMeta = false
    ) {
        if (empty($termId)) {
            return $this->getReferencesMetaList($type, $entityClass, $order, $query, $values, $perPage, $page, $output, $initial, $includeWithoutMeta);
        }

        $term = $this->returnTerm($termId, $type);
        if (empty($term)) {
            return [];
        }

        return $this->returnReferences($term, $entityClass, $order, $query, $values, $perPage, $page, $output, $initial, $includeWithoutMeta);
    }

    /**
     * Get the list of references by metadata name, the total for each one and
     * the first item.
     *
     * When the type is not a property, a filter is added and the list of
     * titles is returned. If there are multiple title, they are returned all.
     *
     * @param string $type "properties" (default), "resource_classes", "item_sets", or
     * "resource_templates".
     * @param string $entityClass All resources types if empty. For item sets,
     * it is always "Item".
     * @param array $order Sort and direction: ['alphabetic' => 'ASC'] (default),
     * ['count' => 'DESC'], or any available column as sort.
     * @param array $query An api search formatted query to limit results.
     * @param array $values Allow to limit the answer to the specified values.
     * @param int $perPage
     * @param int $page One-based page number.
     * @param string $output May be "associative" (default), "list" or "withFirst".
     * @param bool $initial Get initial letter (useful for non-acii references).
     * @param bool $includeWithoutMeta Get total of resources with no metadata.
     * @return array Associative list of references, with the total, the first
     * first record, and the first character, according to the parameters.
     */
    protected function getReferencesMetaList(
        $type,
        $entityClass,
        $order = null,
        array $query = null,
        array $values = [],
        $perPage = null,
        $page = null,
        $output = null,
        $initial = false,
        $includeWithoutMeta = false
    ) {
        $types = [
            'properties' => 'o:property',
            'resource_classes' => 'o:resource_class',
            'resource_templates' => 'o:resource_template',
            'item_sets' => 'o:item_set',
        ];
        $term = isset($types[$type]) ? $types[$type] : 'o:property';

        return $this->returnReferences($term, $entityClass, $order, $query, $values, $perPage, $page, $output, $initial, $includeWithoutMeta);
    }

    /**
     * Count the references for a term.
     *
     * When the type is not a property, a filter is added and the list of
     * titles is returned.
     *
     * @todo Manage multiple entity classes (items, item sets, medias) at once.
     *
     * @param int $termId May be the resource class id, resource template id, or
     * item set id too.
     * @param string $type "properties" (default), "resource_classes", "item_sets", or
     * "resource_templates".
     * @param string $entityClass All resources types if empty. For item sets,
     * it is always "Item".
     * @param array $query An api search formatted query to limit results.
     * @return int The number of references if only one entity class is set.
     */
    protected function countReferences($termId, $type, $entityClass, array $query = null)
    {
        $term = $this->returnTerm($termId, $type);
        if (empty($term)) {
            return 0;
        }

        $resourceName = $this->mapEntityToResourceName($entityClass);

        $references = $this->references;
        $result = $references([$term], $query, ['resource_name' => $resourceName])->count();
        return $result ? reset($result) : 0;
    }

    protected function returnTerm($termId, $type)
    {
        switch ($type) {
            case 'resource_classes':
                try {
                    $term = $this->api->read('resource_classes', ['id' => $termId])->getContent();
                    return $term->term();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return null;
                }
                break;
            case 'resource_templates':
                try {
                    $term = $this->api->read('resource_templates', ['id' => $termId])->getContent();
                    return $term->label();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return null;
                }
                break;
            case 'properties':
            default:
                try {
                    $term = $this->api->read('properties', ['id' => $termId])->getContent();
                    return $term->term();
                } catch (\Omeka\Api\Exception\NotFoundException $e) {
                    return null;
                }
                break;
        }
    }

    protected function returnReferences(
        $term,
        $entityClass,
        $order = null,
        array $query = null,
        array $values = [],
        $perPage = null,
        $page = null,
        $output = null,
        $initial = false,
        $includeWithoutMeta = false
    ) {
        if ($order) {
            $sortOrder = reset($order);
            $sortBy = strtolower(key($order));
            if ($sortBy === 'count') {
                $sortBy = 'total';
            }
        } else {
            $sortOrder = null;
            $sortBy = null;
        }

        $options = [
            'resource_name' => $this->mapEntityToResourceName($entityClass),
            // Options sql.
            'per_page' => $perPage,
            'page' => $page,
            'sort_by' => $sortBy,
            'sort_order' => $sortOrder,
            'filters' => [
                'languages' => [],
            ],
            'values' => $values,
            // Output options.
            'first_id' => $output === 'withFirst',
            'initial' => $initial,
            'lang' => false,
            'include_without_meta' => $includeWithoutMeta,
            'output' => in_array($output, ['list', 'withFirst']) ? 'list' : 'associative',
        ];

        $references = $this->references;
        $result = $references([$term], $query, $options)->list();
        if (empty($result)) {
            return [];
        }

        $result = reset($result);
        return $result['o-module-reference:values'];
    }

    /**
     * Convert a value into a property id or a resource class id.
     *
     * @param mixed $term May be the property id, the term, or the object.
     * @param string $type "properties" (default), "resource_classes", "item_sets", or
     * "resource_templates".
     * @return int|string The term id if any, or an empty string for item sets
     * or resource templates.
     */
    protected function getTermId($term, $type = 'properties')
    {
        if (is_numeric($term)) {
            return (int) $term;
        }

        if (in_array($type, ['item_sets', 'resource_templates'])) {
            return $term === '' ? '' : (int) $term;
        }

        if (is_object($term)) {
            return $term instanceof \Omeka\Api\Representation\AbstractRepresentation
                ? $term->id()
                : $term->getId();
        }

        if (!strpos($term, ':')) {
            return $type === 'properties' || $type === 'resource_classes'
                ? ''
                : 0;
        }

        $result = $this->api->searchOne($type, ['term' => $term])->getContent();
        if (empty($result)) {
            return 0;
        }
        return $result->id();
    }

    protected function getType($type = 'properties')
    {
        $types = [
            'properties',
            'resource_classes',
            'item_sets',
            'resource_templates',
        ];
        return in_array($type, $types)
            ? $type
            : 'properties';
    }

    /**
     * Normalize the resource name as an entity class.
     *
     * @param string $resourceName
     * @return string
     */
    protected function mapResourceNameToEntity($resourceName)
    {
        $resourceEntityMap = [
            null => \Omeka\Entity\Resource::class,
            'resources' => \Omeka\Entity\Resource::class,
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'items' => \Omeka\Entity\Item::class,
            'media' => \Omeka\Entity\Media::class,
            'o:Resource' => \Omeka\Entity\Resource::class,
            'o:ItemSet' => \Omeka\Entity\ItemSet::class,
            'o:Item' => \Omeka\Entity\Item::class,
            'o:Media' => \Omeka\Entity\Media::class,
            \Omeka\Entity\Resource::class => \Omeka\Entity\Resource::class,
            \Omeka\Entity\ItemSet::class => \Omeka\Entity\ItemSet::class,
            \Omeka\Entity\Item::class => \Omeka\Entity\Item::class,
            \Omeka\Entity\Media::class => \Omeka\Entity\Media::class,
            \Omeka\Api\Representation\AbstractResourceRepresentation::class => \Omeka\Entity\Resource::class,
            \Omeka\Api\Representation\ItemSetRepresentation::class => \Omeka\Entity\ItemSet::class,
            \Omeka\Api\Representation\ItemRepresentation::class => \Omeka\Entity\Item::class,
            \Omeka\Api\Representation\MediaRepresentation::class => \Omeka\Entity\Media::class,
        ];
        return isset($resourceEntityMap[$resourceName])
            ? $resourceEntityMap[$resourceName]
            : null;
    }

    /**
     * Normalize the entity class as a resource name.
     *
     * @param string $entityClass
     * @return string
     */
    protected function mapEntityToResourceName($entityClass)
    {
        $entityResourceMap = [
            \Omeka\Entity\Resource::class => 'resources',
            \Omeka\Entity\ItemSet::class => 'item_sets',
            \Omeka\Entity\Item::class => 'items',
            \Omeka\Entity\Media::class => 'media',
        ];
        return isset($entityResourceMap[$entityClass])
            ? $entityResourceMap[$entityClass]
            : null;
    }
}
