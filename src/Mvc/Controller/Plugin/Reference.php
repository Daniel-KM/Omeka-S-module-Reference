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
     * @todo Manage admin references.
     *
     * @param int|string|PropertyRepresentation|ResourceClassRepresentation $term
     * @param string $type "properties" (default) or "resource_classes".
     * @param string $resourceName All resources types if empty.
     * @param array $order Sort and direction: ['alphabetic' => 'ASC'] (default),
     * ['count' => 'DESC'], or any available column as sort.
     * @param array $query An api search formatted query to limit results.
     * @param int $perPage
     * @param int $page One-based page number.
     * @return Reference|array|null The result or null if called directly, else
     * this view plugin.
     */
    public function __invoke($term = null, $type = null, $resourceName = null, $order = null,$query = null, $perPage = null, $page = null)
    {
        if (empty($term)) {
            return $this;
        }
        return $this->getList($term, $type, $resourceName, $order, $query, $perPage, $page);
    }

    /**
     * Get the list of references of a property or a resource class.
     *
     * @param int|string|PropertyRepresentation|ResourceClassRepresentation $term
     * @param string $type "properties" (default) or "resource_classes".
     * @param string $resourceName
     * @param array $order Sort and direction: ['alphabetic' => 'ASC'] (default),
     * ['count' => 'DESC'], or any available column as sort.
     * @param array $query An api search formatted query to limit results.
     * @param int $perPage
     * @param int $page One-based page number.
     * @return array Associative array with total and first record ids.
     */
    public function getList($term, $type = null, $resourceName = null, $order = null, $query = null, $perPage = null, $page = null)
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

        $references = $this->getReferencesList($termId, $type, $entityClass, $order, $query, [], $perPage, $page, null, false);
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
            // The str_replace() allows to fix Apple copy/paste.
            $tree = array_filter(explode(PHP_EOL, str_replace(["\r\n", "\n\r", "\r", "\n"], PHP_EOL, $references)));
        } else {
            $tree = $references;
        }
        return $tree;
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
        $values = array_filter(explode(PHP_EOL, str_replace(["\r\n", "\n\r", "\r", "\n"], PHP_EOL, $dashTree)));
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
     * @deprecated 3.4.7 Use convertTreeToLevels() that manage duplicates terms.
     *
     * @param string $dashTree A tree with levels represented with dashes.
     * All strings should be unique.
     * @return array Flat associative array with text as key and level as value
     * (0 based).
     */
    public function convertTreeToFlatLevels($dashTree)
    {
        // The str_replace() allows to fix Apple copy/paste.
        $values = array_filter(explode(PHP_EOL, str_replace(["\r\n", "\n\r", "\r", "\n"], PHP_EOL, $dashTree)));
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
     * @param string $type "properties" (default) or "resource_classes".
     * @param string $resourceName
     * @param array $query An api search formatted query to limit results.
     * @return int The number of references if only one resource name is set.
     */
    public function count($term, $type = null, $resourceName = null, $query = null)
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
     * - skiplinks: Add the list of letters at top and bottom of the page
     * - headings: Add each letter as headers
     * @return string Html list.
     */
    public function displayListForTerm($term, array $args = [], array $options = [])
    {
        $type = isset($args['type']) && $args['type'] === 'resource_classes' ? 'resource_classes' : 'properties';

        $termId = $this->getTermId($term, $type);
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

        $order = empty($args['order']) ? null : $args['order'];
        $query = empty($args['query']) ? null : $args['query'];
        $perPage = empty($args['per_page']) ? null : (int) $args['per_page'];
        $page = empty($args['page']) ? null : (int) $args['page'];
        $output = $options['link_to_single'] ? 'withFirst' : 'list';
        $initial = $options['skiplinks'] || $options['headings'];

        $references = $this->getReferencesList($termId, $type, $entityClass, $order, $query, [], $perPage, $page, $output, $initial);

        $controller = $this->getController();
        $partial = $controller->viewHelpers()->get('partial');
        $html = $partial('common/reference', [
            'references' => $references,
            'term' => $termId,
            'type' => $type,
            'resourceName' => $resourceName,
            'options' => $options,
            'order' => $order,
            'query' => $query,
            'perPage' => $perPage,
            'page' => $page,
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
     * @param array $referenceLevels References and levels to show.
     * @param array $args Specify the references with "term" (dcterms:subject by
     * default), "type", "resource_name", and "query"
     * @param array $options Options to display the references. Values are booleans:
     * - raw: Show subjects as raw text, not links (default to false)
     * - link_to_single: When there is one result for a term, link it directly
     * to the resource, and not to the list page (default to config)
     * - branch: The managed terms are branches (with the path separated with
     * " :: " (default to config)
     * - expanded: Show tree as expanded (default to config)
     * @return string Html list.
     */
    public function displayTree($references, array $args, array $options = [])
    {
        if (empty($references)) {
            return;
        }

        $type = isset($args['type']) && $args['type'] === 'resource_classes' ? 'resource_classes' : 'properties';

        $term = empty($args['term']) ? $this->DC_Subject_id : $args['term'];
        $termId = $this->getTermId($term, $type);
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
            $totals = $this->getReferencesList($termId, $type, $entityClass, null, $query, $lowerBranches, null, null, $output, $initial);
        }
        // Simple tree.
        else {
            $lowerReferences = $hasMb
                ? array_map(function($v) {
                    return mb_strtolower(key($v));
                }, $references)
                : array_map(function($v) {
                    return strtolower(key($v));
                }, $references);
            $totals = $this->getReferencesList($termId, $type, $entityClass, null, $query, $lowerReferences, null, null, $output, $initial);
        }

        $lowerTotals = [];
        if ($hasMb) {
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
        $lowers = $isBranch ? $lowerBranches : $lowerReferences;
        foreach ($references as $key => $referenceLevel) {
            $level = reset($referenceLevel);
            $reference = key($referenceLevel);
            $lower = $lowers[$key];
            if (isset($lowerTotals[$lower])) {
                $referenceData = [
                    'total' => $lowerTotals[$lower]['total'],
                    'first_id' => $options['link_to_single']
                        ? $lowerTotals[$lower]['first_id']
                        : null,
                ];
            } else {
                $referenceData = [
                    'total' => 0,
                    'first_id' => null,
                ];
            }
            $referenceData['value'] = $reference;
            $referenceData['level'] = $level;
            if ($isBranch) {
                $referenceData['branch'] = $branches[$key];
            }
            $result[] = $referenceData;
        }

        $controller = $this->getController();
        $partial = $controller->viewHelpers()->get('partial');
        $html = $partial('common/reference-tree', [
            'references' => $result,
            'term' => $termId,
            'type' => $type,
            'resourceName' => $resourceName,
            'options' => $options,
            'perPage' => null,
            'page' => null,
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
     * @param string $type "properties" (default) or "resource_classes".
     * @param string $entityClass
     * @param array $order Sort and direction: ['alphabetic' => 'ASC'] (default),
     * ['count' => 'DESC'], or any available column as sort.
     * @param array $query An api search formatted query to limit results.
     * @param array $values Allow to limit the answer to the specified values.
     * @param int $perPage
     * @param int $page One-based page number.
     * @param string $output May be "associative" (default), "list" or "withFirst".
     * @param bool $initial Get initial letter (useful for non-acii references).
     * @return array Associative list of references, with the total, the first
     * first record, and the first character, according to the parameters.
     */
    protected function getReferencesList(
        $termId,
        $type,
        $entityClass,
        $order = null,
        $query = null,
        $values = [],
        $perPage = null,
        $page = null,
        $output = null,
        $initial = false
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
                ;

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
                ;
                break;
        }

        if ($order) {
            $direction = reset($order);
            $order = strtolower(key($order));
            switch ($order) {
                case 'count':
                    $qb
                        ->orderBy('total', $direction)
                        // Add alphabetic order for ergonomy.
                        ->addOrderBy('value.value', 'ASC');
                    break;
                case 'alphabetic':
                    $order = 'value.value';
                    // No break;
                default:
                    $qb
                        ->orderBy($order, $direction);
            }
        } else {
            $qb
                ->orderBy('value.value', 'ASC');
        }
        // Always add an order by id for consistency.
        $qb
            ->addOrderBy('resource.id', 'ASC');

        if ($output === 'withFirst') {
            $qb
                ->addSelect([
                    'MIN(resource.id) AS first_id',
                ]);
        }

        if ($initial) {
            // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
            $qb
                ->addSelect([
                    // 'CONVERT(UPPER(LEFT(value.value, 1)) USING latin1) AS initial',
                    $qb->expr()->upper($qb->expr()->substring('value.value', 1, 1)) . 'AS initial',
                ]);
        }

        // TODO Allow to use a query for resources.
        // TODO Use a temporary table or use get the qb from the adapter.
        if ($query && $entityClass !== \Omeka\Entity\Resource::class) {
            $resourceName = $this->mapEntityToResourceName($entityClass);
            $ids = $this->api->search($resourceName, $query, ['returnScalar' => 'id'])->getContent();
            if ($ids) {
                $qb
                    ->andWhere('resource.id IN (:ids)')
                    ->setParameter('ids', $ids);
            } else {
                return [];
            }
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
                if ($initial && (extension_loaded('intl') || extension_loaded('iconv'))) {
                    if (extension_loaded('intl')) {
                        $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
                        $result = array_map(function ($v) use ($transliterator) {
                            $v['total'] = (int) $v['total'];
                            $v['initial'] = $transliterator->transliterate($v['initial']);
                            return $v;
                        }, $result);
                    } elseif (extension_loaded('iconv')) {
                        $result = array_map(function ($v) {
                            $v['total'] = (int) $v['total'];
                            if ($conv = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v['initial'])) {
                                $v['initial'] = $conv;
                            }
                            return $v;
                        }, $result);
                    }
                } else {
                    $result = array_map(function ($v) {
                        $v['total'] = (int) $v['total'];
                        return $v;
                    }, $result);
                }
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
     * @param array $query An api search formatted query to limit results.
     * @return int The number of references if only one entity class is set.
     */
    protected function countReferences($termId, $type, $entityClass, $query = null)
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

        // TODO Allow to use a query for resources.
        // TODO Use a temporary table or use get the qb from the adapter.
        if ($query && $entityClass !== \Omeka\Entity\Resource::class) {
            $resourceName = $this->mapEntityToResourceName($entityClass);
            $ids = $this->api->search($resourceName, $query, ['returnScalar' => 'id'])->getContent();
            if ($ids) {
                $qb
                    ->andWhere('resource.id IN (:ids)')
                    ->setParameter('ids', $ids);
            } else {
                return 0;
            }
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
