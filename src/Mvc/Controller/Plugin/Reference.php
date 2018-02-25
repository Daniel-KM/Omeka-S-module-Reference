<?php
namespace Reference\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Omeka\Api\Representation\PropertyRepresentation;
use Omeka\Api\Representation\ResourceClassRepresentation;
use Omeka\Mvc\Controller\Plugin\Api;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

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
     * @param EntityManager
     */
    protected $entityManager;

    /**
     * @param Api
     */
    protected $api;

    /**
     * @param EntityManager $entityManager
     * @param Api $api
     */
    public function __construct(EntityManager $entityManager, Api $api)
    {
        $this->entityManager = $entityManager;
        $this->api = $api;
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
     * this view plugin.
     */
    public function __invoke($term = null, $type = null, $resourceName = null, $perPage = null, $page = null)
    {
        if (empty($term)) {
            return $this;
        }
        return $this->getList($term, $type, $resourceName, $perPage, $page);
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
        $type = $type === 'resource_classes' ? 'resource_classes' : 'properties';

        $termId = $this->getTermId($term, $type);
        if (empty($termId)) {
            return;
        }

        $entityClass = $this->mapResourceNameToEntity($resourceName);
        if (empty($entityClass)) {
            return;
        }

        $references = $this->getReferencesList($termId, $type, $entityClass, [], $perPage, $page);
        return $references;
    }

    /**
     * Get a list of references as tree.
     *
     * @deprecated 3.4.5 Useless since tree is stored as array.
     *
     * @param string $references The default one if null.
     * @return array
     */
    public function getTree($references = null)
    {
        if (is_null($references)) {
            $settings = $this->getController()->settings();
            $tree = $settings->get('reference_tree_hierarchy', []);
        } elseif (is_string($references)) {
            $tree = array_filter(explode(PHP_EOL, $references));
        } else {
            $tree = $references;
        }
        return $tree;
    }

    /**
     * Convert a tree from string format to a flat array of texts with level.
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
     *     Europe => 0,
     *     France => 1
     *     Paris => 2
     *     United Kingdom => 1
     *     England => 2
     *     London => 3
     *     Scotland => 2
     *     Asia => 0
     *     Japan => 1
     * ]
     *
     * @param string $dashTree A tree with levels represented with dashes.
     * All strings should be unique.
     * @return array Flat associative array with text as key and level as value
     * (0 based).
     */
    public function convertTreeToLevels($dashTree)
    {
        $values = array_filter(explode(PHP_EOL, $dashTree));
        $levels = array_reduce($values, function ($result, $item) {
            $first = substr($item, 0, 1);
            $space = strpos($item, ' ');
            $level = ($first !== '-' || $space === false) ? 0 : $space;
            $value = trim($level == 0 ? $item : substr($item, $space));
            $result[$value] = $level;
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
     * @param string $type "properties" (default) or "resource_classes".
     * @param string $resourceName
     * @return int The number of references if only one resource name is set.
     */
    public function count($term, $type = null, $resourceName = null)
    {
        $type = $type === 'resource_classes' ? 'resource_classes' : 'properties';

        $termId = $this->getTermId($term, $type);
        if (empty($termId)) {
            return;
        }

        $entityClass = $this->mapResourceNameToEntity($resourceName);
        if (empty($entityClass)) {
            return;
        }

        return $this->countReferences($termId, $type, $entityClass);
    }

    /**
     * Display the list of references via a partial view.
     *
     * @param array $references Array of references elements to show.
     * @param array $args Specify the references with "term" and optionnaly
     * "type" and "resource_name"
     * @param array $options Options to display references. Values are booleans:
     * - raw: Show references as raw text, not links (default to false)
     * - skiplinks: Add the list of letters at top and bottom of the page
     * - headings: Add each letter as headers
     * @return string Html list.
     */
    public function displayList($references, array $args, array $options = [])
    {
        if (empty($references) || empty($args['term'])) {
            return;
        }

        $type = isset($args['type']) && $args['type'] === 'resource_classes' ? 'resource_classes' : 'properties';

        $termId = $this->getTermId($args['term'], $type);
        if (empty($termId)) {
            return;
        }

        if (isset($args['resource_name'])) {
            $entityClass = $this->mapResourceNameToEntity($args['resource_name']);
            if (empty($entityClass)) {
                return;
            }
        } else {
            $entityClass = \Omeka\Entity\Resource::class;
        }

        $resourceName = $this->mapEntityToResourceName($entityClass);

        $options = $this->cleanOptions($options);

        $output = $options['link_to_single'] ? 'withFirst' : 'list';
        $references = $this->getReferencesList($termId, $type, $entityClass, [], null, null, $output);

        $controller = $this->getController();
        $partial = $controller->viewHelpers()->get('partial');
        $html = $partial('common/reference-list', [
            'references' => $references,
            'term' => $termId,
            'type' => $type,
            'resourceName' => $resourceName,
            'options' => $options,
        ]);

        return $html;
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
     * @param array $args Specify the references with "term" and optionnaly
     * "type" and "resource_name"
     * @param array $options Options to display the references. Values are booleans:
     * - raw: Show subjects as raw text, not links (default to false)
     * - expanded: Show tree as expanded (defaul to config)
     * @return string Html list.
     */
    public function displayTree($references, array $args, array $options = [])
    {
        if (empty($references) || empty($args['term'])) {
            return;
        }

        $type = isset($args['type']) && $args['type'] === 'resource_classes' ? 'resource_classes' : 'properties';

        $termId = $this->getTermId($args['term'], $type);
        if (empty($termId)) {
            return;
        }

        if (isset($args['resource_name'])) {
            $entityClass = $this->mapResourceNameToEntity($args['resource_name']);
            if (empty($entityClass)) {
                return;
            }
        } else {
            $entityClass = \Omeka\Entity\Resource::class;
        }

        $resourceName = $this->mapEntityToResourceName($entityClass);

        $options = $this->cleanOptions($options);

        // Sql searches are case insensitive, so a convert should be done.
        $hasMb = function_exists('mb_strtolower');
        $lowerReferences = $hasMb
            ? array_map('mb_strtolower', array_keys($references))
            : array_map('strtolower', array_keys($references));
        $output = $options['link_to_single'] ? 'withFirst' : 'list';
        $totals = $this->getReferencesList($termId, $type, $entityClass, $lowerReferences, null, null, $output);
        $lowerTotals = [];
        if (function_exists('mb_strtolower')) {
            foreach ($totals as $key => $value) {
                $lowerTotals[mb_strtolower($key)] = $value;
            }
        } else {
            foreach ($totals as $key => $value) {
                $lowerTotals[strtolower($key)] = $value;
            }
        }

        // Merge of the two references arrays.
        $result = [];
        foreach ($references as $reference => $level) {
            $lowerReference = $hasMb ? mb_strtolower($reference) : sttolower($reference);
            if (isset($lowerTotals[$lowerReference])) {
                $result[$lowerReference] = [
                    'total' => $lowerTotals[$lowerReference]['total'],
                    'first_id' => $lowerTotals[$lowerReference]['first_id'],
                ];
            } else {
                $result[$lowerReference] = ['total' => 0, 'first_id' => null];
            }
            $result[$lowerReference]['value'] = $reference;
            $result[$lowerReference]['level'] = $level;
        }

        $controller = $this->getController();
        $partial = $controller->viewHelpers()->get('partial');
        $html = $partial('common/reference-tree', [
            'references' => $result,
            'term' => $termId,
            'type' => $type,
            'resourceName' => $resourceName,
            'options' => $options,
        ]);

        return $html;
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

        $cleanedOptions['query_type'] = isset($options['query_type'])
            ? ($options['query_type'] == 'in' ? 'in' : 'eq')
            : $settings->get('reference_query_type', 'eq');
        $cleanedOptions['link_to_single'] = (bool) (isset($options['link_to_single'])
            ? $options['link_to_single']
            : $settings->get('reference_link_to_single'));
        $cleanedOptions['total'] = (bool) (isset($options['total'])
            ? $options['total']
            : $settings->get('reference_total', true));

        switch ($mode) {
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
                break;

            case 'tree':
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
     * @param string $type "properties" (default) or "resource_classes".
     * @param string $entityClass
     * @param array $values Allow to limit the answer to the specified values.
     * @param int $perPage
     * @param int $page One-based page number.
     * @param string $output May be "associative" (default), "list" or "withFirst".
     * @return array Associative list of references, with the total and the
     * first record, according to the output parameter.
     */
    protected function getReferencesList(
        $termId,
        $type,
        $entityClass,
        $values = [],
        $perPage = null,
        $page = null,
        $output = null
    ) {
        $entityManager = $this->entityManager;
        $qb = $entityManager->createQueryBuilder();

        switch ($type) {
            case 'resource_classes':
                $resourceClassId = $termId;
                $termId = $this->DC_Title_id;

                $qb
                    ->select([
                        'DISTINCT value.value',
                        // "Distinct" avoids to count duplicate values in properties in
                        // a resource: we count resources, not properties.
                        $qb->expr()->countDistinct('resource.id') . ' AS total',
                    ])
                    // This checks visibility automatically.
                    ->from(\Omeka\Entity\Resource::class, 'resource')
                    ->leftJoin(
                        \Omeka\Entity\Value::class,
                        'value',
                        'WITH',
                        'value.resource = resource AND value.property = :property_id'
                    )
                    ->setParameter('property_id', $termId)
                    ->where($qb->expr()->eq('resource.resourceClass', ':resource_class'))
                    ->setParameter('resource_class', (int) $resourceClassId)
                    ->groupBy('value.value')
                    ->orderBy('value.value', 'ASC')
                    ->addOrderBy('resource.id', 'ASC');

                if ($entityClass !== \Omeka\Entity\Resource::class) {
                    $qb
                        ->innerJoin($entityClass, 'res', 'WITH', 'resource.id = res.id');
                }
                break;

            case 'properties':
            default:
                $qb
                    ->select([
                        'value.value',
                        // "Distinct" avoids to count duplicate values in properties in
                        // a resource: we count resources, not properties.
                        $qb->expr()->countDistinct('resource.id') . ' AS total',
                    ])
                    ->from(\Omeka\Entity\Value::class, 'value')
                    // This join allow to check visibility automatically too.
                    ->innerJoin($entityClass, 'resource', 'WITH', 'value.resource = resource')
                    ->andWhere($qb->expr()->eq('value.property', ':property'))
                    ->setParameter('property', $termId)
                    // Only literal values.
                    ->andWhere($qb->expr()->isNotNull('value.value'))
                    ->groupBy('value.value')
                    ->orderBy('value.value', 'ASC')
                    ->addOrderBy('resource.id', 'ASC');
                break;
        }

        if ($output === 'withFirst') {
            $qb
                ->addSelect([
                    'MIN(resource.id) AS first_id',
                ]);
        }

        if ($values) {
            $qb
                ->andWhere('value.value IN (:values)')
                ->setParameter('values', $values);
        }

        if ($perPage) {
            $qb->setMaxResults($perPage);
            if ($page > 1) {
                $offset = ($page - 1) * $perPage;
                $qb->setFirstResult($offset);
            }
        }

        switch ($output) {
            case 'list':
            case 'withFirst':
                $result = $qb->getQuery()->getScalarResult();
                $result = array_map(function ($v) {
                    $v['total'] = (int) $v['total'];
                    return $v;
                }, $result);
                $result = array_combine(array_column($result, 'value'), $result);
                return $result;
            case 'associative':
            default:
                $result = $qb->getQuery()->getScalarResult();
                // Array column cannot be used in one step, because the null
                // value (no title) should be converted to "", not to "0".
                // $result = array_column($result, 'total', 'value');
                $result = array_combine(
                    array_column($result, 'value'),
                    array_column($result, 'total')
                );
                return array_map('intval', $result);
        }
    }

    /**
     * Count the references for a term.
     *
     * When the type is not a property, a filter is added and the list of
     * titles is returned.
     *
     * @todo Manage multiple entity classes (items, item sets, medias) at once.
     *
     * @param int $termId May be the resource class id.
     * @param string $type "properties" or "resource_classes".
     * @param string $entityClass
     * @return int The number of references if only one entity class is set.
     */
    protected function countReferences($termId, $type, $entityClass)
    {
        $entityManager = $this->entityManager;
        $qb = $entityManager->createQueryBuilder();

        switch ($type) {
            case 'resource_classes':
                $qb
                    ->select([
                        $qb->expr()->countDistinct('resource.id'),
                    ])
                    ->from(\Omeka\Entity\Resource::class, 'resource')
                    ->andWhere($qb->expr()->eq('resource.resourceClass', ':resource_class'))
                    ->setParameter('resource_class', (int) $termId);
                break;

            case 'properties':
            default:
                $qb
                    ->select([
                        // Here, this is the count of references, not resources.
                        $qb->expr()->countDistinct('value.value'),
                    ])
                    ->from(\Omeka\Entity\Value::class, 'value')
                    // This join allow to check visibility automatically too.
                    ->innerJoin(\Omeka\Entity\Resource::class, 'resource', 'WITH', 'value.resource = resource')
                    ->andWhere($qb->expr()->eq('value.property', ':property'))
                    ->setParameter('property', (int) $termId)
                    ->andWhere($qb->expr()->isNotNull('value.value'));
                break;
        }

        if ($entityClass !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($entityClass, 'res', 'WITH', 'resource.id = res.id');
        }

        $totalRecords = $qb->getQuery()->getSingleScalarResult();
        return $totalRecords;
    }

    /**
     * Convert a value into a property id or a resource class id.
     *
     * @param mixed $term May be the property id, the term, or the object.
     * @param string $type "properties" (default) or "resource_classes".
     * @return int .
     */
    protected function getTermId($term, $type = 'properties')
    {
        if (is_numeric($term)) {
            return (int) $term;
        }

        if (is_object($term)) {
            return $term instanceof \Omeka\Api\Representation\AbstractRepresentation
                ? $term->id()
                : $term->getId();
        }

        if (!strpos($term, ':')) {
            return;
        }

        $result = $this->api->searchOne($type, ['term' => $term])->getContent();
        if (empty($result)) {
            return;
        }
        return $result->id();
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
        if (isset($resourceEntityMap[$resourceName])) {
            return $resourceEntityMap[$resourceName];
        }
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
        if (isset($entityResourceMap[$entityClass])) {
            return $entityResourceMap[$entityClass];
        }
    }
}
