<?php
namespace Reference\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\Manager as AdapterManager;
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
     * @param AdapterManager
     */
    protected $adapterManager;

    /**
     * @param Api
     */
    protected $api;

    /**
     * @param bool
     */
    protected $supportAnyValue;

    /**
     * @param bool
     */
    protected $isOldOmeka;

    /**
     * @param EntityManager $entityManager
     * @param AdapterManager $adapterManager
     * @param Api $api
     * @param bool $supportAnyValue
     */
    public function __construct(
        EntityManager $entityManager,
        AdapterManager $adapterManager,
        Api $api,
        $supportAnyValue
    ) {
        $this->entityManager = $entityManager;
        $this->adapterManager = $adapterManager;
        $this->api = $api;
        $this->supportAnyValue = $supportAnyValue;
        $this->isOldOmeka = strtok(\Omeka\Module::VERSION, '.') < 2;
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
            'term' => $termId,
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
        return $partial('common/reference-tree', [
            'references' => $result,
            'term' => $termId,
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

        $entityManager = $this->entityManager;
        $qb = $entityManager->createQueryBuilder();
        $expr = $qb->expr();

        switch ($type) {
            case 'resource_classes':
            case 'resource_templates':
                $resourceClassOrTemplateId = $termId;
                $termId = $this->DC_Title_id;

                $qb
                    ->select([
                        'DISTINCT value.value AS val',
                        // "Distinct" avoids to count duplicate values in properties in
                        // a resource: we count resources, not properties.
                        $expr->countDistinct('resource.id') . ' AS total',
                    ])
                    // The use of resource checks visibility automatically.
                    ->from(\Omeka\Entity\Resource::class, 'resource')
                    ->leftJoin(
                        \Omeka\Entity\Value::class,
                        'value',
                        Join::WITH,
                        'value.resource = resource AND value.property = :property_id'
                    )
                    ->setParameter('property_id', $termId)
                    ->groupBy('val')
                ;

                if ($type === 'resource_class') {
                    $qb
                        ->where($expr->eq('resource.resourceClass', ':resource_class'))
                        ->setParameter('resource_class', (int) $resourceClassOrTemplateId);
                } else {
                    $qb
                        ->where($expr->eq('resource.resourceTemplate', ':resource_template'))
                        ->setParameter('resource_template', (int) $resourceClassOrTemplateId);
                }

                if ($entityClass !== \Omeka\Entity\Resource::class) {
                    $qb
                        ->innerJoin($entityClass, 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
                }
                break;

            case 'properties':
            default:
                $qb
                    ->select([
                        $this->supportAnyValue ? 'ANY_VALUE(value.value) AS val' : 'value.value AS val',
                        // "Distinct" avoids to count duplicate values in properties in
                        // a resource: we count resources, not properties.
                        $expr->countDistinct('resource.id') . ' AS total',
                    ])
                    ->from(\Omeka\Entity\Value::class, 'value')
                    // This join allow to check visibility automatically too.
                    ->innerJoin($entityClass, 'resource', Join::WITH, $expr->eq('value.resource', 'resource'))
                    ->andWhere($expr->eq('value.property', ':property'))
                    ->setParameter('property', $termId)
                    // Only literal values.
                    ->andWhere($expr->isNotNull('value.value'))
                    ->groupBy('val')
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
                        ->addOrderBy('val', 'ASC');
                    break;
                case 'alphabetic':
                    $order = 'val';
                    // no break;
                default:
                    $qb
                        ->orderBy($order, $direction);
            }
        } else {
            $qb
                ->orderBy('val', 'ASC');
        }

        // Don't add useless order by resource id, since value are unique.
        // Furthermore, it may break mySql 5.7.5 and later, where ONLY_FULL_GROUP_BY
        // is set by default and requires to be grouped.

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
                    $expr->upper($expr->substring('value.value', 1, 1)) . 'AS initial',
                ]);
        }

        $this->limitQuery($qb, $entityClass, $query);

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
                    } else {
                        $result = array_map(function ($v) {
                            $v['total'] = (int) $v['total'];
                            $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v['initial']);
                            if ($trans) {
                                $v['initial'] = $trans;
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
                return array_combine(array_column($result, 'val'), $result);

            case 'associative':
            default:
                $result = $qb->getQuery()->getScalarResult();

                // Array column cannot be used in one step, because the null
                // value (no title) should be converted to "", not to "0".
                // $result = array_column($result, 'total', 'value');
                $result = array_combine(
                    array_column($result, 'val'),
                    array_column($result, 'total')
                );
                return array_map('intval', $result);
        }
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
        $entityManager = $this->entityManager;
        $qb = $entityManager->createQueryBuilder();
        $expr = $qb->expr();

        switch ($type) {
            // Count the number of items by item set.
            // TODO Extract the title via Omeka v2.0.
            case 'item_sets':
                // TODO Get all item sets, even without items (or private items).
                /*
                SELECT DISTINCT item_set.id AS val, COUNT(item_item_set.item_id) AS total
                FROM resource resource
                INNER JOIN item_set item_set ON item_set.id = resource.id
                LEFT JOIN item_item_set item_item_set ON item_item_set.item_set_id = item_set.id
                GROUP BY val;
                */

                $entityClass = \Omeka\Entity\Item::class;
                $qb = $entityManager->createQueryBuilder();
                $qb
                    ->select(
                        'item_set.id as val',
                        'COUNT(resource.id) AS total'
                    )
                    // The use of resource checks visibility automatically.
                    ->from(\Omeka\Entity\Resource::class, 'resource')
                    ->innerJoin(\Omeka\Entity\Item::class, 'item', Join::WITH, $expr->eq('item.id', 'resource.id'))
                    // The left join allows to get the total of items without
                    // item set.
                    ->leftJoin('item.itemSets', 'item_set', Join::WITH, $expr->neq('item_set.id', 0))
                    // Check visibility automatically for item sets.
                    ->leftJoin(
                        \Omeka\Entity\Resource::class,
                        'resource_item_set',
                        Join::WITH,
                        $expr->eq('resource_item_set.id', 'item_set.id')
                    )
                    ->groupBy('val')
                ;
                break;

            case 'resource_templates':
                $qb = $entityManager->createQueryBuilder();
                $qb
                    ->select(
                        'resource_template.label as val',
                        'COUNT(resource.id) AS total'
                    )
                    // The use of resource checks visibility automatically.
                    ->from(\Omeka\Entity\Resource::class, 'resource')
                    // The left join allows to get the total of items without
                    // resource template.
                    ->leftJoin(
                        \Omeka\Entity\ResourceTemplate::class,
                        'resource_template',
                        Join::WITH,
                        $expr->eq('resource_template.id', 'resource.resourceTemplate')
                    )
                    ->groupBy('val')
                ;
                if ($entityClass !== \Omeka\Entity\Resource::class) {
                    $qb
                        ->innerJoin($entityClass, 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
                }
                break;

            case 'resource_classes':
                /*
                SELECT resource_class.label AS val, resource.id AS val2, COUNT(resource.id) AS total
                FROM resource resource
                INNER JOIN item item ON item.id = resource.id
                LEFT JOIN resource_class ON resource_class.id = resource.resource_class_id
                GROUP BY val;
                */

                $qb = $entityManager->createQueryBuilder();
                $qb
                    ->select(
                        // 'resource_class.label as val',
                        "CONCAT(vocabulary.prefix, ':', resource_class.localName) AS val",
                        'COUNT(resource.id) AS total'
                    )
                    // The use of resource checks visibility automatically.
                    ->from(\Omeka\Entity\Resource::class, 'resource')
                    // The left join allows to get the total of items without
                    // resource class.
                    ->leftJoin(
                        \Omeka\Entity\ResourceClass::class,
                        'resource_class',
                        Join::WITH,
                        $expr->eq('resource_class.id', 'resource.resourceClass')
                    )
                    ->innerJoin(
                        \Omeka\Entity\Vocabulary::class,
                        'vocabulary',
                        Join::WITH,
                        $expr->eq('vocabulary.id', 'resource_class.vocabulary')
                    )
                    ->groupBy('val')
                ;
                if ($entityClass !== \Omeka\Entity\Resource::class) {
                    $qb
                        ->innerJoin($entityClass, 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
                }
                break;

            case 'properties':
            default:
                $qb = $entityManager->createQueryBuilder();
                $qb
                    ->select(
                        // 'property.label as val',
                        "CONCAT(vocabulary.prefix, ':', property.localName) AS val",
                        // "Distinct" avoids to count resources with multiple
                        // values multiple times for the same property: we count
                        // resources, not properties.
                        $expr->countDistinct('value.resource') . ' AS total'
                    )
                    // The use of resource checks visibility automatically.
                    ->from(\Omeka\Entity\Resource::class, 'resource')
                    ->innerJoin(\Omeka\Entity\Value::class, 'value', Join::WITH, $expr->eq('value.resource', 'resource'))
                    // The left join allows to get the total of items without
                    // property.
                    ->leftJoin(
                        \Omeka\Entity\Property::class,
                        'property',
                        Join::WITH,
                        $expr->eq('property.id', 'value.property')
                    )
                    ->innerJoin(
                        \Omeka\Entity\Vocabulary::class,
                        'vocabulary',
                        Join::WITH,
                        $expr->eq('vocabulary.id', 'property.vocabulary')
                    )
                    ->groupBy('val')
                ;
                if ($entityClass !== \Omeka\Entity\Resource::class) {
                    $qb
                        ->innerJoin($entityClass, 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
                }
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
                        ->addOrderBy('val', 'ASC');
                    break;
                case 'alphabetic':
                    $order = 'val';
                    // no break;
                default:
                    $qb
                        ->orderBy($order, $direction);
            }
        } else {
            $qb
                ->orderBy('val', 'ASC');
        }

        // Don't add useless order by resource id, since value are unique.
        // Furthermore, it may break mySql 5.7.5 and later, where ONLY_FULL_GROUP_BY
        // is set by default and requires to be grouped.

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
                    $expr->upper($expr->substring('value.value', 1, 1)) . 'AS initial',
                ]);
        }

        $this->limitQuery($qb, $entityClass, $query);

        if ($values) {
            switch ($type) {
                case 'item_sets':
                    $qb
                        ->andWhere('item_set.id IN (:ids)')
                        ->setParameter('ids', $values);
                    break;
                case 'resource_classes':
                    if ($this->isTerm($values[0])) {
                        $values = $this->listResourceClassIds($values);
                    }
                    // no break.
                case 'resource_templates':
                    $table = trim($type, 'es');
                    if (is_numeric($values[0])) {
                        $qb
                            ->andWhere($table . '.id IN (:ids)')
                            ->setParameter('ids', $values);
                    } else {
                        $qb
                            ->andWhere($table . '.label IN (:labels)')
                            ->setParameter('labels', $values);
                    }
                    break;
                case 'properties':
                default:
                    break;
            }
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
                    } else {
                        $result = array_map(function ($v) {
                            $v['total'] = (int) $v['total'];
                            $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $v['initial']);
                            if ($trans) {
                                $v['initial'] = $trans;
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
                $result = array_combine(array_column($result, 'val'), $result);

                if (!$includeWithoutMeta) {
                    unset($result['']);
                }

                return $result;

            case 'associative':
            default:
                $result = $qb->getQuery()->getScalarResult();

                // Array column cannot be used in one step, because the null
                // value (no title) should be converted to "", not to "0".
                // $result = array_column($result, 'total', 'value');
                $result = array_combine(
                    array_column($result, 'val'),
                    array_column($result, 'total')
                );

                if (!$includeWithoutMeta) {
                    unset($result['']);
                }

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
        $entityManager = $this->entityManager;
        $qb = $entityManager->createQueryBuilder();
        $expr = $qb->expr();

        switch ($type) {
            case 'item_sets':
                if ($entityClass !== \Omeka\Entity\Item::class) {
                    return 0;
                }
                $qb
                    ->select([
                        $expr->countDistinct('resource.id'),
                    ])
                    ->from(\Omeka\Entity\Resource::class, 'resource')
                    ->andWhere($expr->eq('resource.itemSet', ':item_set'))
                    ->setParameter('item_set', (int) $termId);
                break;

            case 'resource_templates':
                $qb
                    ->select([
                        $expr->countDistinct('resource.id'),
                    ])
                    ->from(\Omeka\Entity\Resource::class, 'resource')
                    ->andWhere($expr->eq('resource.resourceTemplate', ':resource_template'))
                    ->setParameter('resource_template', (int) $termId);
                break;

            case 'resource_classes':
                $qb
                    ->select([
                        $expr->countDistinct('resource.id'),
                    ])
                    ->from(\Omeka\Entity\Resource::class, 'resource')
                    ->andWhere($expr->eq('resource.resourceClass', ':resource_class'))
                    ->setParameter('resource_class', (int) $termId);
                break;

            case 'properties':
            default:
                $qb
                    ->select([
                        // Here, this is the count of references, not resources.
                        $expr->countDistinct('value.value'),
                    ])
                    ->from(\Omeka\Entity\Value::class, 'value')
                    // This join allow to check visibility automatically too.
                    ->innerJoin(\Omeka\Entity\Resource::class, 'resource', Join::WITH, $expr->eq('value.resource', 'resource'))
                    ->andWhere($expr->eq('value.property', ':property'))
                    ->setParameter('property', (int) $termId)
                    ->andWhere($expr->isNotNull('value.value'));
                break;
        }

        if ($entityClass !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($entityClass, 'res', Join::WITH, 'res.id = resource.id');
        }

        $this->limitQuery($qb, $entityClass, $query);

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Limit the results with a query (generally the site query).
     *
     * @param QueryBuilder $qb
     * @param string $entityClass
     * @param array $query
     */
    protected function limitQuery(QueryBuilder $qb, $entityClass, array $query = null)
    {
        if (empty($query)) {
            return;
        }

        $alias = $this->isOldOmeka ? $entityClass : 'omeka_root';
        $subQb = $this->entityManager->createQueryBuilder()
            ->select($alias . '.id')
            ->from($entityClass, $alias);
        /** @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter $adapter */
        $resourceName = $this->mapEntityToResourceName($entityClass);
        $adapter = $this->adapterManager->get($resourceName);
        $adapter->buildQuery($subQb, $query);

        // There is no colision: the adapter query uses alias "omeka_" + index.
        $qb
            ->andWhere($qb->expr()->in('resource.id', $subQb->getDQL()));

        $subParams = $subQb->getParameters();
        foreach ($subParams as $parameter) {
            $qb->setParameter(
                $parameter->getName(),
                $parameter->getValue(),
                $parameter->getType()
            );
        }
    }

    /**
     * Determine whether a string is a valid JSON-LD term.
     *
     * @see \Omeka\Api\Adapter\AbstractEntityAdapter::isTerm()
     *
     * @param string $term
     * @return bool
     */
    protected function isTerm($term)
    {
        return (bool) preg_match('/^[a-z0-9-_]+:[a-z0-9-_]+$/i', $term);
    }

    /**
     * Convert a list of terms into a list of property ids.
     *
     * @param array $values
     * @return array Only values that are terms are converted into ids, the
     * other are removed.
     */
    protected function listPropertyIds(array $values)
    {
        return array_intersect_key($this->getPropertyIds(), array_fill_keys($values, null));
    }

    /**
     * Get all property ids by term.
     *
     * @return array Associative array of ids by term.
     */
    protected function getPropertyIds()
    {
        static $properties;

        if (is_null($properties)) {
            $qb = $this->entityManager->createQueryBuilder();
            $qb
                ->select([
                    "CONCAT(vocabulary.prefix, ':', property.localName) AS term",
                    'property.id AS id',
                ])
                ->from(\Omeka\Entity\Property::class, 'property')
                ->innerJoin(
                    \Omeka\Entity\Vocabulary::class,
                    'vocabulary',
                    Join::WITH,
                    $qb->expr()->eq('vocabulary.id', 'property.vocabulary')
                )
            ;
            $properties = $qb->getQuery()->getScalarResult();
        }

        return $properties;
    }

    /**
     * Convert a list of terms into a list of resource class ids.
     *
     * @param array $values
     * @return array Only values that are terms are converted into ids, the
     * other are removed.
     */
    protected function listResourceClassIds(array $values)
    {
        return array_intersect_key($this->getResourceClassIds(), array_fill_keys($values, null));
    }

    /**
     * Get all resource class ids by term.
     *
     * @return array Associative array of ids by term.
     */
    protected function getResourceClassIds()
    {
        static $resourceClasses;

        if (is_null($resourceClasses)) {
            $qb = $this->entityManager->createQueryBuilder();
            $qb
                ->select([
                    "CONCAT(vocabulary.prefix, ':', resource_class.localName) AS term",
                    'resource_class.id AS id',
                ])
                ->from(\Omeka\Entity\ResourceClass::class, 'resource_class')
                ->innerJoin(
                    \Omeka\Entity\Vocabulary::class,
                    'vocabulary',
                    Join::WITH,
                    $qb->expr()->eq('vocabulary.id', 'resource_class.vocabulary')
                )
            ;
            $resourceClasses = $qb->getQuery()->getScalarResult();
        }

        return $resourceClasses;
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
            return 0;
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
