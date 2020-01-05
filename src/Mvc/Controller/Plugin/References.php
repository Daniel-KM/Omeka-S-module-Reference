<?php
namespace Reference\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Omeka\Api\Adapter\Manager as AdapterManager;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Mvc\Controller\Plugin\Translate;
// use Reference\Mvc\Controller\Plugin\Reference;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class References extends AbstractPlugin
{
    /**
     * @var int
     */
    protected $DC_Title_id = 1;

    /**
     * @param EntityManager
     */
    protected $entityManager;

    /**
     * @param AdapterManager
     */
    protected $adapterManager;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var Reference
     */
    protected $reference;

    /**
     * @var Translate
     */
    protected $translate;

    /**
     * @var \Omeka\Api\Representation\PropertyRepresentation[]
     */
    protected $properties;

    /**
     * @var \Omeka\Api\Representation\ResourceClassRepresentation[]
     */
    protected $resourceClasses;

    /**
     * @var \Omeka\Api\Representation\ResourceTemplateRepresentation[]
     */
    protected $resourceTemplates;

    /**
     * @param bool
     */
    protected $supportAnyValue;

    /**
     * @param bool
     */
    protected $isOldOmeka;

    /**
     * @var array
     */
    protected $metadata;

    /**
     * @var array
     */
    protected $query;

    /**
     * @var array
     */
    protected $options;

    /**
     * @param EntityManager $entityManager
     * @param AdapterManager $adapterManager
     * @param Api $api
     * @param Reference $reference
     * @param Translate $translate
     * @param \Omeka\Api\Representation\PropertyRepresentation[] $properties
     * @param \Omeka\Api\Representation\ResourceClassRepresentation[] $resourceClasses
     * @param \Omeka\Api\Representation\ResourceTemplateRepresentation[] $resourceTemplates
     * @param bool $supportAnyValue
     */
    public function __construct(
        EntityManager $entityManager,
        AdapterManager $adapterManager,
        Api $api,
        Reference $reference,
        Translate $translate,
        array $properties,
        array $resourceClasses,
        array $resourceTemplates,
        $supportAnyValue
    ) {
        $this->entityManager = $entityManager;
        $this->adapterManager = $adapterManager;
        $this->api = $api;
        $this->reference = $reference;
        $this->translate = $translate;
        $this->properties = $properties;
        $this->resourceClasses = $resourceClasses;
        $this->resourceTemplates = $resourceTemplates;
        $this->supportAnyValue = $supportAnyValue;
        $this->isOldOmeka = \Omeka\Module::VERSION < 2;
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
     * @return self
     */
    public function __invoke(array $metadata = null, array $query = null, array $options = null)
    {
        return $this
            ->setMetadata($metadata)
            ->setQuery($query)
            ->setOptions($options);
    }

    /**
     * @param array $metadata
     * @return self
     */
    public function setMetadata(array $metadata = null)
    {
        $this->metadata = $metadata ? array_unique($metadata) : [];
        return $this;
    }

    /**
     * @return array
     */
    public function getMetadata()
    {
        return $this->metadata;
    }

    /**
     * @param array $query
     * @return self
     */
    public function setQuery(array $query = null)
    {
        // Remove useless keys.
        $filter = function ($v) {
            return is_string($v) ? (bool) strlen($v) : (bool) $v;
        };
        $this->query = $query ? array_filter($query, $filter) : [];
        return $this;
    }

    /**
     * @return array
     */
    public function getQuery()
    {
        return $this->query;
    }

    /**
     * @param array $options
     * @return self
     */
    public function setOptions(array $options = null)
    {
        $defaults = [
            'resource_name' => 'items',
            // Not an option, but simpler to set it here.
            'entity_class' => \Omeka\Entity\Item::class,
            // Options sql.
            'per_page' => 25,
            'page' => 1,
            'sort_by' => 'count',
            'sort_order' => 'DESC',
            // Output options.
            'link_to_single' => false,
            'initial' => false,
            'values' => [],
            'include_without_meta' => false,
            'output' => 'associative',
        ];
        if ($options) {
            $resourceName = in_array(@$options['resource_name'], ['items', 'item_sets', 'media', 'resources'])
                ? $options['resource_name']
                : $defaults['resource_name'];
            $this->options = [
                'resource_name' => $resourceName,
                'entity_class' => $this->mapResourceNameToEntity($resourceName),
                'per_page' => @$options['per_page'] ?: $defaults['per_page'],
                'page' => @$options['page'] ?: $defaults['page'],
                'sort_by' => @$options['sort_by'] ? $options['sort_by'] : 'alphabetic',
                'sort_order' => strtolower(@$options['sort_order']) === 'asc' ? 'ASC' : 'DESC',
                'link_to_single' => (bool) @$options['link_to_single'],
                'initial' => (bool) @$options['initial'],
                'values' => @$options['values'] ?: [],
                'include_without_meta' => (bool) @$options['include_without_meta'],
                'output' => in_array(@$options['output'], ['associative', 'list', 'withFirst']) ? $options['output'] : 'associative',
            ];
        } else {
            $this->options = $defaults;
        }
        return $this;
    }

    /**
     * @return array
     */
    public function getOptions()
    {
        return $this->options;
    }

    /**
     * @return array
     */
    public function list()
    {
        $fields = $this->getMetadata();
        if (empty($fields)) {
            return [];
        }

        $options = $this->getOptions();

        $api = $this->api;
        $translate = $this->translate;

        // TODO Convert all queries into a single or two sql queries (at least for properties and classes).
        // TODO Return all needed columns.

        $result = [];
        foreach ($fields as $inputField) {
            $field = $this->prepareField($inputField);
            $result[$field['term']] = [
                'o:label' => $field['label'],
                'o-module-reference:values' => [],
            ];

            switch ($field['type']) {
                case 'properties':
                    $values = $this->listResourcesForProperty($field['id']);
                    $result[$field['term']] = [
                        'o:id' => $field['id'],
                        'o:term' => $field['term'],
                        'o:label' => $field['label'],
                        'o-module-reference:values' => [],
                    ];
                    foreach (array_filter($values) as $value => $count) {
                        $result[$field['term']]['o-module-reference:values'][] = [
                            'o:label' => $value,
                            '@language' => null,
                            'count' => $count,
                        ];
                    }
                    break;

                case 'resource_classes':
                    $values = $this->listResourcesForResourceClass($field['id']);
                    $result[$field['term']] = [
                        'o:id' => $field['id'],
                        'o:term' => $field['term'],
                        'o:label' => $field['label'],
                        'o-module-reference:values' => [],
                    ];
                    foreach (array_filter($values) as $value => $count) {
                        $result[$field['term']]['o-module-reference:values'][] = [
                            'o:label' => $value,
                            '@language' => null,
                            'count' => $count,
                        ];
                    }
                    break;

                case 'resource_templates':
                    $values = $this->listResourcesForResourceTemplate($field['id']);
                    $result[$field['term']] = [
                        'o:id' => $field['id'],
                        'o:term' => $field['term'],
                        'o:label' => $field['label'],
                        'o-module-reference:values' => [],
                    ];
                    foreach (array_filter($values) as $value => $count) {
                        $result[$field['term']]['o-module-reference:values'][] = [
                            'o:label' => $value,
                            '@language' => null,
                            'count' => $count,
                        ];
                    }
                    break;

                case 'o:property':
                    $values = $this->listProperties();
                    foreach (array_filter($values) as $value => $count) {
                        $property = $this->properties[$value];
                        $result[$field['term']]['o-module-reference:values'][] = [
                            'o:id' => $property->id(),
                            'o:term' => $property->term(),
                            'o:label' => $translate($property->label()),
                            '@language' => null,
                            'count' => $count,
                        ];
                    }
                    break;

                case 'o:resource_class':
                    $values = $this->listResourceClasses();
                    foreach (array_filter($values) as $value => $count) {
                        $resourceClass = $this->resourceClasses[$value];
                        $result[$field['term']]['o-module-reference:values'][] = [
                            'o:id' => $resourceClass->id(),
                            'o:term' => $resourceClass->term(),
                            'o:label' => $translate($resourceClass->label()),
                            '@language' => null,
                            'count' => $count,
                        ];
                    }
                    break;

                case 'o:resource_template':
                    $values = $this->listResourceTemplates();
                    foreach (array_filter($values) as $value => $count) {
                        $resourceTemplate = $this->resourceTemplates[$value];
                        $result[$field['term']]['o-module-reference:values'][] = [
                            'o:id' => $resourceTemplate->id(),
                            'o:label' => $resourceTemplate->label(),
                            '@language' => null,
                            'count' => $count,
                        ];
                    }
                    break;

                case 'o:item_set':
                    // Manage an exception for the resource "items" exception.
                    if ($field['type'] === 'o:item_set' && $options['resource_name'] !== 'items') {
                        $values = [];
                    } else {
                        $values = $this->listItemSets();
                    }
                    foreach (array_filter($values) as $value => $count) {
                        // TODO Improve this process via the resource title (Omeka 2).
                        $meta = $api->read('item_sets', ['id' => $value])->getContent();
                        $result[$field['term']]['o-module-reference:values'][] = [
                            'o:id' => (int) $value,
                            'o:label' => $meta->displayTitle(),
                            '@language' => null,
                            'count' => $count,
                        ];
                    }
                    break;

                // Unknown.
                default:
                    $result[$field['term']][] = [
                        'o:label' => $field['label'],
                        'o-module-reference:values' => [],
                    ];
                    break;
            }
        }

        return $result;
    }

    /**
     * Count the total of distinct values for a term, a template or an item set.
     *
     * @return int[] The number of references for each type, according to query.
     */
    public function count()
    {
        $fields = $this->getMetadata();
        if (empty($fields)) {
            return [];
        }

        // @todo Manage multiple type at once.
        // @todo Manage multiple resource names (items, item sets, medias) at once.

        $result = [];
        foreach ($fields as $inputField) {
            $field = $this->prepareField($inputField);
            switch ($field['type']) {
                case 'properties':
                    $result[$field['term']] = $this->countResourcesForProperties($field['id']);
                    break;
                case 'resource_classes':
                    $result[$field['term']] = $this->countResourcesForResourceClasses($field['id']);
                    break;
                case 'resource_templates':
                    $result[$field['term']] = $this->countResourcesForResourceTemplates($field['id']);
                    break;
                case 'item_sets':
                    $result[$field['term']] = $this->countResourcesForItemSets($field['id']);
                    break;
                default:
                    $result[$field['term']] = null;
                    break;
            }
        }

        return $result;
    }

    /**
     * Get the list of used values for a proeprty, the total for each one and
     * the first item.
     *
     * @param int $termId
     * @return array Associative list of references, with the total, the first
     * first record, and the first character, according to the parameters.
     */
    protected function listResourcesForProperty($termId)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select([
                $this->supportAnyValue ? 'ANY_VALUE(value.value) AS val' : 'value.value AS val',
                // "Distinct" avoids to count duplicate values in properties in
                // a resource: we count resources, not properties.
                $expr->countDistinct('resource.id') . ' AS total',
            ])
            ->from(\Omeka\Entity\Value::class, 'value')
            // This join allow to check visibility automatically too.
            ->innerJoin($this->options['entity_class'], 'resource', Join::WITH, $expr->eq('value.resource', 'resource'))
            ->andWhere($expr->eq('value.property', ':property'))
            ->setParameter('property', $termId)
            // Only literal values.
            ->andWhere($expr->isNotNull('value.value'))
            ->groupBy('val')
        ;

        $this->appendAdditionalData($qb, 'properties');
        return $this->outputMetadata($qb, 'properties');
    }

    /**
     * Get the list of used values for a resource class, the total for each one
     * and the first item.
     *
     * @param int $termId
     * @return array Associative list of references, with the total, the first
     * first record, and the first character, according to the parameters.
     */
    protected function listResourcesForResourceClass($termId)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $resourceClassId = $termId;
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
            ->where($expr->eq('resource.resourceClass', ':resource_class'))
            ->setParameter('resource_class', (int) $resourceClassId)
        ;

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
        }

        $this->appendAdditionalData($qb, 'resource_classes');
        return $this->outputMetadata($qb, 'resource_classes');
    }

    /**
     * Get the list of used values for a resource template, the total for each
     * one and the first item.
     *
     * @param int $termId
     * @return array Associative list of references, with the total, the first
     * first record, and the first character, according to the parameters.
     */
    protected function listResourcesForResourceTemplate($termId)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $resourceTemplateId = $termId;
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
            ->where($expr->eq('resource.resourceTemplate', ':resource_template'))
            ->setParameter('resource_template', (int) $resourceTemplateId)
        ;

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
        }

        $this->appendAdditionalData($qb, 'resource_templates');
        return $this->outputMetadata($qb, 'resource_templates');
    }

    /**
     * Get the list of used properties references by metadata name, the total
     * for each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * first record, and the first character, according to the parameters.
     */
    protected function listProperties()
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

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
        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
        }

        $this->appendAdditionalData($qb, 'o:property');
        return $this->outputMetadata($qb, 'o:property');
    }

    /**
     * Get the list of used resource classes by metadata name, the total for
     * each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * first record, and the first character, according to the parameters.
     */
    protected function listResourceClasses()
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        /*
         SELECT resource_class.label AS val, resource.id AS val2, COUNT(resource.id) AS total
         FROM resource resource
         INNER JOIN item item ON item.id = resource.id
         LEFT JOIN resource_class ON resource_class.id = resource.resource_class_id
         GROUP BY val;
         */

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
        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
        }

        $this->appendAdditionalData($qb, 'o:resource_class');
        return $this->outputMetadata($qb, 'o:resource_class');
    }

    /**
     * Get the list of used resource templates by metadata name, the total for
     * each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * first record, and the first character, according to the parameters.
     */
    protected function listResourceTemplates()
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

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
        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
        }

        $this->appendAdditionalData($qb, 'o:resource_template');
        return $this->outputMetadata($qb, 'o:resource_template');
    }

    /**
     * Get the list of used item sets, the total for each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * first record, and the first character, according to the parameters.
     */
    protected function listItemSets()
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        // Count the number of items by item set.
        // TODO Extract the title via Omeka v2.0.

        // TODO Get all item sets, even without items (or private items).
        /*
         SELECT DISTINCT item_set.id AS val, COUNT(item_item_set.item_id) AS total
         FROM resource resource
         INNER JOIN item_set item_set ON item_set.id = resource.id
         LEFT JOIN item_item_set item_item_set ON item_item_set.item_set_id = item_set.id
         GROUP BY val;
         */

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

        $this->appendAdditionalData($qb, 'o:item_set');
        return $this->outputMetadata($qb, 'o:item_set');
    }

    protected function appendAdditionalData(QueryBuilder $qb, $type)
    {
        $expr = $qb->expr();

        $sortBy = $this->options['sort_by'];
        $sortOrder = $this->options['sort_order'];
        switch ($sortBy) {
            case 'count':
                $qb
                    ->orderBy('total', $sortOrder)
                    // Add alphabetic order for ergonomy.
                    ->addOrderBy('val', 'ASC');
                break;
            case 'alphabetic':
                $sortBy = 'val';
                // no break.
            // Any available column.
            default:
                $qb
                    ->orderBy($sortBy, $sortOrder);
        }

        // Don't add useless order by resource id, since value are unique.
        // Furthermore, it may break mySql 5.7.5 and later, where ONLY_FULL_GROUP_BY
        // is set by default and requires to be grouped.

        if ($this->options['link_to_single']) {
            // Add the first resource id.
            $qb
                ->addSelect([
                    'MIN(resource.id) AS first_id',
                ]);
        }

        if ($this->options['initial']) {
            // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
            $qb
                ->addSelect([
                    // 'CONVERT(UPPER(LEFT(value.value, 1)) USING latin1) AS initial',
                    $expr->upper($expr->substring('value.value', 1, 1)) . 'AS initial',
                ]);
        }

        $this->limitQuery($qb);

        if ($this->options['values']) {
            switch ($type) {
                case 'properties':
                case 'resource_classes':
                case 'resource_templates':
                    $qb
                        ->andWhere('value.value IN (:values)')
                        ->setParameter('values', $this->options['values']);
                    break;
                case 'o:property':
                    $values = is_numeric($this->options['values'][0])
                        ? $this->options['values']
                        : $this->listPropertyIds($this->options['values']);
                    $qb
                        ->andWhere('property' . '.id IN (:ids)')
                        ->setParameter('ids', $values);
                    break;
                case 'o:resource_class':
                    $values = is_numeric($this->options['values'][0])
                        ? $this->options['values']
                        : $this->listResourceClassIds($this->options['values']);
                    $qb
                        ->andWhere('resource_class' . '.id IN (:ids)')
                        ->setParameter('ids', $values);
                    break;
                case 'o:resource_template':
                    if (is_numeric($this->options['values'][0])) {
                        $qb
                            ->andWhere('resource_template' . '.id IN (:ids)')
                            ->setParameter('ids', $this->options['values']);
                    } else {
                        $qb
                            ->andWhere('resource_template' . '.label IN (:labels)')
                            ->setParameter('labels', $this->options['values']);
                    }
                    break;
                case 'o:item_set':
                    $qb
                        ->andWhere('item_set.id IN (:ids)')
                        ->setParameter('ids', $this->options['values']);
                    break;
                default:
                    break;
            }
        }

        if ($this->options['per_page']) {
            $qb->setMaxResults($this->options['per_page']);
            if ($this->options['page'] > 1) {
                $offset = ($this->options['page'] - 1) * $this->options['per_page'];
                $qb->setFirstResult($offset);
            }
        }
    }

    protected function outputMetadata(QueryBuilder $qb, $type)
    {
        switch ($this->options['output']) {
            case 'list':
            case 'withFirst':
                $result = $qb->getQuery()->getScalarResult();
                if ($this->options['initial'] && (extension_loaded('intl') || extension_loaded('iconv'))) {
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

                if (!$this->options['include_without_meta']) {
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

                if (!$this->options['include_without_meta']) {
                    unset($result['']);
                }

                return array_map('intval', $result);
        }
    }

    protected function countResourcesForProperties($termId)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

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

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, 'res.id = resource.id');
        }

        $this->limitQuery($qb);

        return $qb->getQuery()->getSingleScalarResult();
    }

    protected function countResourcesForResourceClasses($termId)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select([
                $expr->countDistinct('resource.id'),
            ])
            ->from(\Omeka\Entity\Resource::class, 'resource')
            ->andWhere($expr->eq('resource.resourceClass', ':resource_class'))
            ->setParameter('resource_class', (int) $termId);

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, 'res.id = resource.id');
        }

        $this->limitQuery($qb);

        return $qb->getQuery()->getSingleScalarResult();
    }

    protected function countResourcesForResourceTemplate($id)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select([
                $expr->countDistinct('resource.id'),
            ])
            ->from(\Omeka\Entity\Resource::class, 'resource')
            ->andWhere($expr->eq('resource.resourceTemplate', ':resource_template'))
            ->setParameter('resource_template', (int) $id);

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, 'res.id = resource.id');
        }

        $this->limitQuery($qb);

        return $qb->getQuery()->getSingleScalarResult();
    }

    protected function countResourcesForItemSet($id)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        if ($this->options['entity_class'] !== \Omeka\Entity\Item::class) {
            return 0;
        }
        $qb
            ->select([
                $expr->countDistinct('resource.id'),
            ])
            ->from(\Omeka\Entity\Resource::class, 'resource')
            ->andWhere($expr->eq('resource.itemSet', ':item_set'))
            ->setParameter('item_set', (int) $id);

        // Always an item.
        $qb
            ->innerJoin(\Omeka\Entity\Item::class, 'res', Join::WITH, 'res.id = resource.id');

        $this->limitQuery($qb);

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Limit the results with a query (generally the site query).
     *
     * @param QueryBuilder $qb
     */
    protected function limitQuery(QueryBuilder $qb)
    {
        if (empty($this->query)) {
            return;
        }

        $alias = $this->isOldOmeka ? $this->options['entity_class'] : 'omeka_root';
        $subQb = $this->entityManager->createQueryBuilder()
            ->select($alias . '.id')
            ->from($this->options['entity_class'], $alias);
        /** @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter $adapter */
        $adapter = $this->adapterManager
            ->get($this->options['resource_name'])
            ->buildQuery($subQb, $this->query);

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

    protected function prepareField($field)
    {
        static $labels;

        $metaToTypes = [
            'o:property' => 'properties',
            'o:resource_class' => 'resource_classes',
            'o:resource_template' => 'resource_templates',
            // Item sets is only for items.
            'o:item_set' => 'item_sets',
        ];

        if (isset($metaToTypes[$field])) {
            if (is_null($labels)) {
                $translate = $this->translate;
                $labels = [
                    'o:property' => $translate('Properties'), // @translate
                    'o:resource_class' => $translate('Classes'), // @translate
                    'o:resource_template' => $translate('Templates'), // @translate
                    'o:item_set' => $translate('Item sets'), // @translate
                ];
            }

            return [
                'type' => $field,
                'metatype' => $metaToTypes[$field],
                'term' => $field,
                'label' => $labels[$field],
            ];
        }

        if (isset($this->properties[$field])) {
            $property = $this->properties[$field];
            return [
                'type' => 'properties',
                'id' => $property->id(),
                'term' => $field,
                'label' => $property->label(),
            ];
        }

        if (isset($this->resourceClasses[$field])) {
            $resourceClass = $this->resourceClasses[$field];
            return [
                'type' => 'resource_classes',
                'id' => $resourceClass->id(),
                'term' => $field,
                'label' => $resourceClass->label(),
            ];
        }

        if (isset($this->resourceTemplates[$field])) {
            $resourceTemplate = $this->resourceTemplates[$field];
            return [
                'type' => 'resource_templates',
                'id' => $resourceTemplate->id(),
                'term' => $field,
                'label' => $resourceTemplate->label(),
            ];
        }

        return [
            'type' => null,
            'term' => $field,
            'label' => $field,
        ];
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
        $result = array_intersect_key($this->properties, array_fill_keys($values, null));
        return array_map(function ($v) {
            return $v->id();
        }, $result);
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
        $result = array_intersect_key($this->resourceClasses, array_fill_keys($values, null));
        return array_map(function ($v) {
            return $v->id();
        }, $result);
    }

    /**
     * Convert a list of labels into a list of resource template ids.
     *
     * @param array $values
     * @return array Only values that are terms are converted into ids, the
     * other are removed.
     */
    protected function listResourceTemplateIds(array $values)
    {
        $result = array_intersect_key($this->resourceTemplates, array_fill_keys($values, null));
        return array_map(function ($v) {
            return $v->id();
        }, $result);
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
            'items' => \Omeka\Entity\Item::class,
            'item_sets' => \Omeka\Entity\ItemSet::class,
            'media' => \Omeka\Entity\Media::class,
            'resources' => \Omeka\Entity\Resource::class,
        ];
        return isset($resourceEntityMap[$resourceName])
            ? $resourceEntityMap[$resourceName]
            : \Omeka\Entity\Resource::class;
    }
}
