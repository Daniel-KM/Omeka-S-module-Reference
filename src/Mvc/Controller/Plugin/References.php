<?php declare(strict_types=1);

namespace Reference\Mvc\Controller\Plugin;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Adapter\Manager as AdapterManager;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Mvc\Controller\Plugin\Translate;

class References extends AbstractPlugin
{
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
        Translate $translate,
        array $properties,
        array $resourceClasses,
        array $resourceTemplates,
        $supportAnyValue
    ) {
        $this->entityManager = $entityManager;
        $this->adapterManager = $adapterManager;
        $this->api = $api;
        $this->translate = $translate;
        $this->properties = $properties;
        $this->resourceClasses = $resourceClasses;
        $this->resourceTemplates = $resourceTemplates;
        $this->supportAnyValue = $supportAnyValue;
    }

    /**
     * Get the references.
     *
     * @param array $metadata Classes, properties terms, template names, or
     * other Omeka metadata names.
     * @param array $query An Omeka search query.
     * @param array $options Options for output.
     * - resource_name: items (default), "item_sets", "media", "resources".
     * - sort_by: "alphabetic" (default), "total", or any available column.
     * - sort_order: "asc" (default) or "desc".
     * - filters: array Limit values to the specified data. Currently managed:
     *   - "languages": list of languages. Values without language are returned
     *     with the empty value "". This option is used only for properties.
     *   - "datatypes": array Filter property values according to the data types.
     *     Default datatypes are "literal", "resource", "resource:item", "resource:itemset",
     *     "resource:media" and "uri".
     *     Warning: "resource" is not the same than specific resources.
     *     Use module Bulk Edit or Bulk Check to specify all resources automatically.
     *   - "begin": array Filter property values that begin with these strings,
     *     generally one or more initials.
     *   - "end": array Filter property values that end with these strings.
     * - values: array Allow to limit the answer to the specified values.
     * - first: false (default), or true (get first resource).
     * - list_by_max: 0 (default), or the max number of resources for each reference)
     *   The max number should be below 1024 (mysql limit for group_concat).
     * - fields: the fields to use for the list of resources, if any. If not
     *   set, the output is an associative array with id as key and title as
     *   value. If set, value is an array of the specified fields.
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
        unset($query['per_page']);
        unset($query['page']);
        unset($query['offset']);
        unset($query['limit']);
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
            'per_page' => 0,
            'page' => 1,
            'sort_by' => 'alphabetic',
            'sort_order' => 'ASC',
            'filters' => [
                'languages' => [],
                'datatypes' => [],
                'begin' => [],
                'end' => [],
            ],
            'values' => [],
            // Output options.
            'first' => false,
            'list_by_max' => 0,
            'fields' => [],
            'initial' => false,
            'distinct' => false,
            'datatype' => false,
            'lang' => false,
            'include_without_meta' => false,
            'output' => 'list',
        ];
        if ($options) {
            $resourceName = in_array(@$options['resource_name'], ['items', 'item_sets', 'media', 'resources'])
                ? $options['resource_name']
                : $defaults['resource_name'];
            $first = !empty($options['first']);
            $listByMax = empty($options['list_by_max']) ? 0 : (int) $options['list_by_max'];
            $fields = empty($options['fields']) ? [] : $options['fields'];
            $initial = !empty($options['initial']);
            $distinct = !empty($options['distinct']);
            $datatype = !empty($options['datatype']);
            $lang = !empty($options['lang']);
            $this->options = [
                'resource_name' => $resourceName,
                'entity_class' => $this->mapResourceNameToEntity($resourceName),
                'per_page' => isset($options['per_page']) && is_numeric($options['per_page']) ? (int) $options['per_page'] : $defaults['per_page'],
                'page' => $options['page'] ?? $defaults['page'],
                'sort_by' => $options['sort_by'] ?? 'alphabetic',
                'sort_order' => isset($options['sort_order']) && strtolower((string) $options['sort_order']) === 'desc' ? 'DESC' : 'ASC',
                'filters' => @$options['filters'] ? $options['filters'] + $defaults['filters'] : $defaults['filters'],
                'values' => $options['values'] ?? [],
                'first' => $first,
                'list_by_max' => $listByMax,
                'fields' => $fields,
                'initial' => $initial,
                'distinct' => $distinct,
                'datatype' => $datatype,
                'lang' => $lang,
                'include_without_meta' => (bool) @$options['include_without_meta'],
                'output' => @$options['output'] === 'associative' && !$first && !$listByMax && !$initial && !$distinct && !$datatype && !$lang
                    ? 'associative'
                    : 'list',
            ];

            // The check for length avoids to add a filter on values without any
            // language. It should be specified as "||" (or leading/trailing "|").
            if (is_string($this->options['filters']['languages']) && strlen($this->options['filters']['languages'])) {
                $this->options['filters']['languages'] = explode('|', str_replace(',', '|', $this->options['filters']['languages']));
            }
            $this->options['filters']['languages'] = array_unique(array_map('trim', $this->options['filters']['languages']));
            if (!is_array($this->options['filters']['datatypes'])) {
                $this->options['filters']['datatypes'] = explode('|', str_replace(',', '|', $this->options['filters']['datatypes']));
            }
            $this->options['filters']['datatypes'] = array_unique(array_filter(array_map('trim', $this->options['filters']['datatypes'])));

            // No trim for begin/end.
            if (!is_array($this->options['filters']['begin'])) {
                $this->options['filters']['begin'] = explode('|', str_replace(',', '|', $this->options['filters']['begin']));
            }
            $this->options['filters']['begin'] = array_unique(array_filter($this->options['filters']['begin']));
            if (!is_array($this->options['filters']['end'])) {
                $this->options['filters']['end'] = explode('|', str_replace(',', '|', $this->options['filters']['end']));
            }
            $this->options['filters']['end'] = array_unique(array_filter($this->options['filters']['end']));

            if (!is_array($this->options['fields'])) {
                $this->options['fields'] = explode('|', str_replace(',', '|', $this->options['fields']));
            }
            $this->options['fields'] = array_unique(array_filter(array_map('trim', $this->options['fields'])));
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
        $isAssociative = $this->options['output'] === 'associative';

        // TODO Convert all queries into a single or two sql queries (at least for properties and classes).
        // TODO Return all needed columns.

        $result = [];
        foreach ($fields as $inputField) {
            $field = $this->prepareField($inputField);
            $result[$field['term']] = [
                'o:label' => $field['label'],
                'o:references' => [],
            ];

            switch ($field['type']) {
                case 'properties':
                    $values = $this->listDataForProperty($field['id']);
                    $result[$field['term']] = [
                        '@type' => $field['@type'],
                        'o:id' => $field['id'],
                        'o:term' => $field['term'],
                        'o:label' => $field['label'],
                        'o:references' => $values,
                    ];
                    break;

                case 'resource_titles':
                    $values = $this->listDataForResourceTitle();
                    $result[$field['term']] = [
                        '@type' => $field['@type'],
                        'o:id' => '0',
                        'o:term' => $field['term'],
                        'o:label' => $field['label'],
                        'o:references' => $values,
                    ];
                    break;

                case 'resource_classes':
                    $values = $this->listDataForResourceClass($field['id']);
                    $result[$field['term']] = [
                        '@type' => $field['@type'],
                        'o:id' => $field['id'],
                        'o:term' => $field['term'],
                        'o:label' => $field['label'],
                        'o:references' => $values,
                    ];
                    break;

                case 'resource_templates':
                    $values = $this->listDataForResourceTemplate($field['id']);
                    $result[$field['term']] = [
                        '@type' => $field['@type'],
                        'o:id' => $field['id'],
                        'o:term' => $field['term'],
                        'o:label' => $field['label'],
                        'o:references' => $values,
                    ];
                    break;

                case 'item_sets':
                    $values = $this->listDataForItemSet($field['id']);
                    $result[$field['term']] = [
                        '@type' => $field['@type'],
                        'o:id' => $field['id'],
                        'o:label' => $field['label'],
                        'o:references' => $values,
                    ];
                    break;

                case 'o:property':
                    $values = $this->listProperties();
                    if ($isAssociative) {
                        $result[$field['term']]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $value => $valueData) {
                            $property = $this->properties[$valueData['val']];
                            $result[$field['term']]['o:references'][] = [
                                'o:id' => $property->id(),
                                'o:term' => $property->term(),
                                'o:label' => $this->translate->__invoke($property->label()),
                                '@language' => null,
                            ] + $valueData;
                        }
                    }
                    break;

                case 'o:resource_class':
                    $values = $this->listResourceClasses();
                    if ($isAssociative) {
                        $result[$field['term']]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $value => $valueData) {
                            $resourceClass = $this->resourceClasses[$valueData['val']];
                            $result[$field['term']]['o:references'][] = [
                                'o:id' => $resourceClass->id(),
                                'o:term' => $resourceClass->term(),
                                'o:label' => $this->translate->__invoke($resourceClass->label()),
                                '@language' => null,
                            ] + $valueData;
                        }
                    }
                    break;

                case 'o:resource_template':
                    $values = $this->listResourceTemplates();
                    if ($isAssociative) {
                        $result[$field['term']]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $value => $valueData) {
                            $resourceTemplate = $this->resourceTemplates[$valueData['val']];
                            $result[$field['term']]['o:references'][] = [
                                'o:id' => $resourceTemplate->id(),
                                'o:label' => $resourceTemplate->label(),
                                '@language' => null,
                            ] + $valueData;
                        }
                    }
                    break;

                case 'o:item_set':
                    // Manage an exception for the resource "items" exception.
                    if ($field['type'] === 'o:item_set' && $options['resource_name'] !== 'items') {
                        $values = [];
                    } else {
                        $values = $this->listItemSets();
                    }
                    if ($isAssociative) {
                        $result[$field['term']]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $value => $valueData) {
                            // TODO Improve this process via the resource title (Omeka 2).
                            $meta = $this->api->read('item_sets', ['id' => $valueData['val']])->getContent();
                            $result[$field['term']]['o:references'][] = [
                                '@type' => 'o:ItemSet',
                                'o:id' => (int) $value,
                                'o:label' => $meta->displayTitle(),
                                '@language' => null,
                            ] + $valueData;
                        }
                    }
                    break;

                // Unknown.
                default:
                    $result[$field['term']][] = [
                        'o:label' => $field['label'],
                        'o:references' => [],
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
                    $result[$field['term']] = $this->countResourcesForProperty($field['id']);
                    break;
                case 'resource_classes':
                    $result[$field['term']] = $this->countResourcesForResourceClass($field['id']);
                    break;
                case 'resource_templates':
                    $result[$field['term']] = $this->countResourcesForResourceTemplate($field['id']);
                    break;
                case 'item_sets':
                    $result[$field['term']] = $this->countResourcesForItemSet($field['id']);
                    break;
                default:
                    $result[$field['term']] = null;
                    break;
            }
        }

        return $result;
    }

    /**
     * Get the list of used values for a property, the total for each one and
     * the first item.
     *
     * @param int $termId
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listDataForProperty($termId)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        // Note: Doctrine requires simple label, without quote or double quote:
        // "o:label" is not possible, neither "count".

        $qb
            ->select([
                $this->supportAnyValue
                    ? "ANY_VALUE(COALESCE(value.value, valueResource.title, value.uri)) AS val"
                    : "COALESCE(value.value, valueResource.title, value.uri) AS val",
                // "Distinct" avoids to count duplicate values in properties in
                // a resource: we count resources, not properties.
                $expr->countDistinct('resource.id') . ' AS total',
            ])
            ->from(\Omeka\Entity\Value::class, 'value')
            // This join allow to check visibility automatically too.
            ->innerJoin($this->options['entity_class'], 'resource', Join::WITH, $expr->eq('value.resource', 'resource'))
            // The values should be distinct for each type.
            ->leftJoin($this->options['entity_class'], 'valueResource', Join::WITH, $expr->eq('value.valueResource', 'valueResource'))
            ->andWhere($expr->eq('value.property', ':property'))
            ->setParameter('property', $termId)
            ->groupBy('val')
        ;

        $this->filterByDatatype($qb);
        $this->filterByLanguage($qb);
        $this->filterByBeginOrEnd($qb);
        $this->manageOptions($qb, 'properties');
        return $this->outputMetadata($qb, 'properties');
    }

    /**
     * Get the list of used values for the title, the total for each one and the
     * first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listDataForResourceTitle()
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        // Note: Doctrine requires simple label, without quote or double quote:
        // "o:label" is not possible, neither "count".

        $qb
            ->select([
                $this->supportAnyValue
                ? "ANY_VALUE(resource.title) AS val"
                : "resource.title AS val",
                // "Distinct" avoids to count duplicate values in properties in
                // a resource: we count resources, not properties.
                $expr->countDistinct('resource.id') . ' AS total',
            ])
            ->from(\Omeka\Entity\Resource::class, 'resource')
            // This join allow to check visibility automatically too.
            ->innerJoin($this->options['entity_class'], 'res', Join::WITH, $expr->eq('res', 'resource'))
            ->groupBy('val')
        ;

        // TODO Improve filter for "o:title".
        // $this->filterByDatatype($qb);
        // $this->filterByLanguage($qb);
        $this->filterByBeginOrEnd($qb, 'resource.title');
        $this->manageOptions($qb, 'resource_titles');
        return $this->outputMetadata($qb, 'properties');
    }

    /**
     * Get the list of used values for a resource class, the total for each one
     * and the first item.
     *
     * @param int $resourceClassId
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listDataForResourceClass($resourceClassId)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select([
                'DISTINCT resource.title AS val',
                $expr->count('resource.id') . ' AS total',
            ])
            // The use of resource checks visibility automatically.
            ->from(\Omeka\Entity\Resource::class, 'resource')
            ->where($expr->eq('resource.resourceClass', ':resource_class'))
            ->setParameter('resource_class', (int) $resourceClassId)
            ->groupBy('val');

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
        }

        $this->manageOptions($qb, 'resource_classes');
        return $this->outputMetadata($qb, 'resource_classes');
    }

    /**
     * Get the list of used values for a resource template, the total for each
     * one and the first item.
     *
     * @param int $resourceTemplateId
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listDataForResourceTemplate($resourceTemplateId)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select([
                'DISTINCT resource.title AS val',
                $expr->count('resource.id') . ' AS total',
            ])
            // The use of resource checks visibility automatically.
            ->from(\Omeka\Entity\Resource::class, 'resource')
            ->where($expr->eq('resource.resourceTemplate', ':resource_template'))
            ->setParameter('resource_template', (int) $resourceTemplateId)
            ->groupBy('val');

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
        }

        $this->manageOptions($qb, 'resource_templates');
        return $this->outputMetadata($qb, 'resource_templates');
    }

    /**
     * Get the list of used values for an item set, the total for each one and
     * the first item.
     *
     * @param int $itemSetId
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listDataForItemSet($itemSetId)
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        if ($this->options['entity_class'] !== \Omeka\Entity\Item::class) {
            return [];
        }

        $qb
            ->select([
                'DISTINCT resource.title AS val',
                $expr->count('resource.id') . ' AS total',
            ])
            // The use of resource checks visibility automatically.
            ->from(\Omeka\Entity\Resource::class, 'resource')
            // Always an item.
            ->innerJoin(\Omeka\Entity\Item::class, 'res', Join::WITH, 'res.id = resource.id')
            ->innerJoin('res.itemSets', 'item_set', Join::WITH, 'item_set.id = :item_set')
            ->setParameter('item_set', (int) $itemSetId)
            ->groupBy('val')
        ;

        $this->manageOptions($qb, 'item_sets');
        return $this->outputMetadata($qb, 'item_sets');
    }

    /**
     * Get the list of used properties references by metadata name, the total
     * for each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
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

        $this->filterByLanguage($qb);
        $this->manageOptions($qb, 'o:property');
        return $this->outputMetadata($qb, 'o:property');
    }

    /**
     * Get the list of used resource classes by metadata name, the total for
     * each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
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

        $this->manageOptions($qb, 'o:resource_class');
        return $this->outputMetadata($qb, 'o:resource_class');
    }

    /**
     * Get the list of used resource templates by metadata name, the total for
     * each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
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

        $this->manageOptions($qb, 'o:resource_template');
        return $this->outputMetadata($qb, 'o:resource_template');
    }

    /**
     * Get the list of used item sets, the total for each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
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

        // By exeption, the query for item sets should add public site, because
        // item sets are limited by sites.
        $this->limitItemSetsToSite($qb);

        $this->manageOptions($qb, 'o:item_set');
        return $this->outputMetadata($qb, 'o:item_set');
    }

    protected function limitItemSetsToSite(QueryBuilder $qb): void
    {
        // @see \Omeka\Api\Adapter\ItemSetAdapter::buildQuery()
        if (isset($this->query['site_id']) && is_numeric($this->query['site_id'])) {
            $expr = $qb->expr();

            // TODO Check if this useful here.
            // Though $site isn't used here, this is intended to ensure that the
            // user cannot perform a query against a private site he doesn't
            // have access to.
            try {
                $this->adapterManager->get('sites')->findEntity($this->query['site_id']);
            } catch (\Omeka\Api\Exception\NotFoundException$e) {
            }

            $qb
                ->innerJoin('item_set.siteItemSets', 'ref_site_item_set')
                ->andWhere($expr->eq('ref_site_item_set.site', ':ref_site_item_set_site'))
                ->setParameter(':ref_site_item_set_site', $this->query['site_id']);
        }
    }

    protected function filterByDatatype(QueryBuilder $qb): void
    {
        if ($this->options['filters']['datatypes']) {
            $expr = $qb->expr();
            $qb
                ->andWhere($expr->in('value.type', ':datatypes'))
                ->setParameter('datatypes', $this->options['filters']['datatypes'], \Doctrine\DBAL\Types\Type::SIMPLE_ARRAY);
        }
    }

    protected function filterByLanguage(QueryBuilder $qb): void
    {
        if ($this->options['filters']['languages']) {
            $expr = $qb->expr();
            $hasEmptyLanguage = in_array('', $this->options['filters']['languages']);
            if ($hasEmptyLanguage) {
                $qb
                    ->andWhere($expr->orX(
                        $expr->in('value.lang', ':languages'),
                        // FIXME For an unknown reason, doctrine may crash with "IS NULL" in some non-reproductible cases. Db version related?
                        $expr->isNull('value.lang')
                    ))
                    ->setParameter('languages', $this->options['filters']['languages'], \Doctrine\DBAL\Types\Type::SIMPLE_ARRAY);
            } else {
                $qb
                    ->andWhere($expr->in('value.lang', ':languages'))
                    ->setParameter('languages', $this->options['filters']['languages'], \Doctrine\DBAL\Types\Type::SIMPLE_ARRAY);
            }
        }
    }

    protected function filterByBeginOrEnd(QueryBuilder $qb, $column = 'value.value'): void
    {
        $expr = $qb->expr();
        // This is a and by default.
        foreach (['begin', 'end'] as $filter) {
            if ($this->options['filters'][$filter]) {
                if ($filter === 'begin') {
                    $filterB = '';
                    $filterE = '%';
                } else {
                    $filterB = '%';
                    $filterE = '';
                }

                // Use "or like" in most of the cases, else a regex (slower).
                // TODO Add more checks and a php unit.
                if (count($this->options['filters'][$filter]) === 1) {
                    $qb
                        ->andWhere($expr->like($column, ":filter_$filter"))
                        ->setParameter(
                            "filter_$filter",
                            $filterB . str_replace(['%', '_'], ['\%', '\_'], reset($this->options['filters'][$filter])) . $filterE
                        );
                } elseif (count($this->options['filters'][$filter]) <= 20) {
                    $orX = [];
                    foreach (array_values($this->options['filters'][$filter]) as $key => $string) {
                        $orX[] = $expr->like($column, sprintf(':filter_%s_%d)', $filter, ++$key));
                        $qb
                            ->setParameter(
                                "filter_{$filter}_$key",
                                $filterB . str_replace(['%', '_'], ['\%', '\_'], $string) . $filterE
                            );
                    }
                    $qb
                        ->andWhere($expr->orX(...$orX));
                } else {
                    $regexp = implode('|', array_map('preg_quote', $this->options['filters'][$filter]));
                    $qb
                        ->andWhere("REGEXP($column, :filter_filter) = true")
                        ->setParameter("filter_$filter", $regexp);
                }
            }
        }
    }

    protected function manageOptions(QueryBuilder $qb, $type): void
    {
        $expr = $qb->expr();
        if (in_array($type, ['resource_classes', 'resource_templates', 'item_sets', 'resource_titles'])
            && $this->options['initial']
        ) {
            // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
            $qb
                ->addSelect([
                    // 'CONVERT(UPPER(LEFT(value.value, 1)) USING latin1) AS initial',
                    $this->supportAnyValue
                        ? 'ANY_VALUE(' . $expr->upper($expr->substring('resource.title', 1, 1)) . ') AS initial'
                        : $expr->upper($expr->substring('resource.title', 1, 1)) . ' AS initial',
                ]);
        }

        if ($type === 'properties' && $this->options['initial']) {
            // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
            $qb
                ->addSelect([
                    // 'CONVERT(UPPER(LEFT(COALESCE(value.value, $linkedResourceTitle), 1)) USING latin1) AS initial',
                    $this->supportAnyValue
                        ? 'ANY_VALUE(' . $expr->upper($expr->substring("COALESCE(value.value, valueResource.title, value.uri)", 1, 1)) . ') AS initial'
                        : $expr->upper($expr->substring("COALESCE(value.value, valueResource.title, value.uri)", 1, 1)) . ' AS initial',
                ]);
        }

        if ($type === 'properties' && $this->options['distinct']) {
            $qb
                ->addSelect([
                    // TODO Warning with type "resource", that may be the same than "resource:item", etc.
                    'valueResource.id AS res',
                    'value.uri AS uri',
                ])
                ->addGroupBy('res')
                ->addGroupBy('uri');
        }

        if ($type === 'properties' && $this->options['datatype']) {
            $qb
                ->addSelect([
                    $this->supportAnyValue
                        ? 'ANY_VALUE(value.type) AS type'
                        : 'value.type AS type',
                ]);
            // No need to group by type: it is already managed with group by distinct "val,res,uri".
        }

        if ($type === 'properties' && $this->options['lang']) {
            $qb
                ->addSelect([
                    $this->supportAnyValue
                        ? 'ANY_VALUE(value.lang) AS lang'
                        : 'value.lang AS lang',
                ]);
            if ($this->options['distinct']) {
                $qb
                    ->addGroupBy('lang');
            }
        }

        // Add the first resource id.
        if ($this->options['first']) {
            $qb
                ->addSelect([
                    'MIN(resource.id) AS first',
                ]);
        }

        if ($this->options['list_by_max']
            // TODO May be simplified for "resource_titles".
            && ($type === 'properties' || $type === 'resource_titles')
        ) {
            $qb
                // Add and order by title, because it's the most common and
                // simple. Use a single select to avoid issue with null and
                // duplicate titles.
                // that should not exist in Omeka data. The unit separator is
                // not used but tabulation in order to check results simpler.
                // Mysql max length: 1024.
                ->leftJoin(\Omeka\Entity\Resource::class, 'ress', Join::WITH, $expr->eq($type === 'resource_titles' ? 'resource' : 'value.resource', 'ress'))
                ->addSelect([
                    // Note: for doctrine, separators must be set as parameters.
                    'GROUP_CONCAT(ress.id, :unit_separator, ress.title SEPARATOR :group_separator) AS resources',
                ])
                ->setParameter('unit_separator', chr(0x1F))
                ->setParameter('group_separator', chr(0x1D))
            ;
        }

        if ($this->options['values']) {
            switch ($type) {
                case 'properties':
                case 'resource_classes':
                case 'resource_templates':
                    $qb
                        ->andWhere('value.value IN (:values)')
                        ->setParameter('values', $this->options['values']);
                    break;
                case 'resource_titles':
                    // TODO Nothing to filter for resource titles?
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

        $this->searchQuery($qb);

        // Don't add useless order by resource id, since value are unique.
        // Furthermore, it may break mySql 5.7.5 and later, where ONLY_FULL_GROUP_BY
        // is set by default and requires to be grouped.

        $sortBy = $this->options['sort_by'];
        $sortOrder = $this->options['sort_order'];
        switch ($sortBy) {
            case 'total':
                $qb
                    ->orderBy('total', $sortOrder)
                    // Add alphabetic order for ergonomy.
                    ->addOrderBy('val', 'ASC');
                break;
            case 'alphabetic':
                $sortBy = 'val';
                // Any available column.
                // no break
            default:
                $qb
                    ->orderBy($sortBy, $sortOrder);
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
        $result = $qb->getQuery()->getScalarResult();
        if (!count($result)) {
            return $result;
        }

        if ($this->options['output'] === 'associative') {
            // Array column cannot be used in one step, because the null value
            // (no title) should be converted to "", not to "0".
            // $result = array_column($result, 'total', 'val');
            $result = array_combine(
                array_column($result, 'val'),
                array_column($result, 'total')
            );

            if (!$this->options['include_without_meta']) {
                unset($result['']);
            }

            return array_map('intval', $result);
        }

        $first = reset($result);
        if (extension_loaded('intl') && $this->options['initial']) {
            $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
            $result = array_map(function ($v) use ($transliterator) {
                $v['total'] = (int) $v['total'];
                $v['initial'] = $transliterator->transliterate((string) $v['initial']);
                return $v;
            }, $result);
        } elseif (extension_loaded('iconv') && $this->options['initial']) {
            $result = array_map(function ($v) {
                $v['total'] = (int) $v['total'];
                $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $v['initial']);
                $v['initial'] = $trans === false ? (string) $v['initial'] : $trans;
                return $v;
            }, $result);
        } elseif ($this->options['initial']) {
            // Convert null into empty string.
            $result = array_map(function ($v) {
                $v['total'] = (int) $v['total'];
                $v['initial'] = (string) $v['initial'];
                return $v;
            }, $result);
        } else {
            $result = array_map(function ($v) {
                $v['total'] = (int) $v['total'];
                return $v;
            }, $result);
        }

        $hasFirst = array_key_exists('first', $first);
        if ($hasFirst) {
            $result = array_map(function ($v) {
                $v['first'] = (int) $v['first'] ?: null;
                return $v;
            }, $result);
        }

        $hasListBy = array_key_exists('resources', $first);
        if ($hasListBy) {
            $result = array_map(function ($v) {
                $list = array_map(function ($vv) {
                    return explode(chr(0x1F), $vv, 2);
                }, explode(chr(0x1D), (string) $v['resources']));
                $v['resources'] = array_column($list, 1, 0);
                return $v;
            }, $result);

            if ($this->options['fields']) {
                $fields = array_fill_keys($this->options['fields'], true);
                $result = array_map(function ($v) use ($fields) {
                    // Search resources is not available.
                    if ($this->options['resource_name'] === 'resource') {
                        $v['resources'] = array_map(function ($title, $id) use ($fields) {
                            try {
                                return array_intersect_key(
                                    $this->api->read('resources', ['id' => $id])->getContent()->jsonSerialize(),
                                    $fields
                                    );
                            } catch (\Omeka\Api\Exception\NotFoundException $e) {
                                // May not be possible, except with weird rights.
                                // return array_intersect_key(['o:id' => $id, 'o:title' => $title], $fields);
                                return [];
                            }
                        }, $v['resources'], array_keys($v['resources']));
                    } else {
                        $resources = $this->api->search($this->options['resource_name'], ['id' => array_keys($v['resources']), 'sort_by' => 'title', 'sort_order' => 'asc'])->getContent();
                        $v['resources'] = array_map(function ($r) use ($fields) {
                            return array_intersect_key($r->jsonSerialize(), $fields);
                        }, $resources);
                    }
                    return $v;
                }, $result);
            }
        }

        if ($this->options['include_without_meta']) {
            return $result;
        }

        // Remove all empty values ("val").
        // But do not remove a uri or a resource without label.
        if (count($first) <= 2 || !array_key_exists('type', $first)) {
            $result = array_combine(array_column($result, 'val'), $result);
            unset($result['']);
            return array_values($result);
        }

        return array_filter($result, function ($v) {
            return $v['val'] !== ''
                || $v['type'] === 'uri'
                || strpos($v['type'], 'resource') === 0;
        });
    }

    protected function countResourcesForProperty($termId)
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

        $this->searchQuery($qb);

        return $qb->getQuery()->getSingleScalarResult();
    }

    protected function countResourcesForResourceClass($termId)
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

        $this->searchQuery($qb);

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

        $this->searchQuery($qb);

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
            ->innerJoin(\Omeka\Entity\Item::class, 'res', Join::WITH, 'res.id = resource.id')
            // See \Omeka\Api\Adapter\ItemAdapter::buildQuery()
            ->innerJoin(
                'res.itemSets',
                'item_set',
                Join::WITH,
                $expr->in('item_set.id', ':item_sets')
            )
            ->setParameter('item_sets', (int) $id);

        $this->searchQuery($qb);

        return $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Limit the results with a query (generally the site query).
     *
     * @param QueryBuilder $qb
     */
    protected function searchQuery(QueryBuilder $qb): void
    {
        if (empty($this->query)) {
            return;
        }

        $subQb = $this->entityManager->createQueryBuilder()
            ->select('omeka_root.id')
            ->from($this->options['entity_class'], 'omeka_root');

        // Support of "starts with" is needed to get all subjects for a letter.
        // So, the properties part of the query is managed separately.
        $mainQuery = $this->query;
        unset($mainQuery['property']);

        /** @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::search() */
        /** @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter $adapter */
        $adapter = $this->adapterManager->get($this->options['resource_name']);
        $adapter->buildBaseQuery($subQb, $mainQuery);
        $adapter->buildQuery($subQb, $mainQuery);
        if (isset($this->query['property']) && is_array($this->query['property'])) {
            $this->buildPropertyQuery($subQb, $adapter);
        }

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
     * Improve the default property query for resources.
     *
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildPropertyQuery()
     * @see \AdvancedSearchPlus\Module::buildPropertyQuery()
     *
     * Complete \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildPropertyQuery()
     *
     * Query format:
     *
     * - property[{index}][joiner]: "and" OR "or" joiner with previous query
     * - property[{index}][property]: property ID
     * - property[{index}][text]: search text
     * - property[{index}][type]: search type
     *   - eq: is exactly (core)
     *   - neq: is not exactly (core)
     *   - in: contains (core)
     *   - nin: does not contain (core)
     *   - ex: has any value (core)
     *   - nex: has no value (core)
     *   - list: is in list
     *   - nlist: is not in list
     *   - sw: starts with
     *   - nsw: does not start with
     *   - ew: ends with
     *   - new: does not end with
     *   - res: has resource
     *   - nres: has no resource
     *
     * @param QueryBuilder $qb
     * @param AbstractResourceEntityAdapter $adapter
     */
    protected function buildPropertyQuery(QueryBuilder $qb, AbstractResourceEntityAdapter $adapter): void
    {
        // if (empty($this->query['property']) || !is_array($this->query['property'])) {
        //     return;
        // }

        $valuesJoin = 'omeka_root.values';
        $where = '';
        $expr = $qb->expr();

        $escape = function ($string) {
            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $string);
        };

        foreach ($this->query['property'] as $queryRow) {
            if (!(
                is_array($queryRow)
                && array_key_exists('property', $queryRow)
                && array_key_exists('type', $queryRow)
            )) {
                continue;
            }
            $propertyId = $queryRow['property'];
            $queryType = $queryRow['type'];
            $joiner = $queryRow['joiner'] ?? '';
            $value = $queryRow['text'] ?? '';

            if (!strlen((string) $value) && $queryType !== 'nex' && $queryType !== 'ex') {
                continue;
            }

            $valuesAlias = $adapter->createAlias();
            $positive = true;

            switch ($queryType) {
                case 'neq':
                    $positive = false;
                    // no break.
                case 'eq':
                    $param = $adapter->createNamedParameter($qb, $value);
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $adapter->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->eq("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->eq("$valuesAlias.value", $param),
                        $expr->eq("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nin':
                    $positive = false;
                    // no break.
                case 'in':
                    $param = $adapter->createNamedParameter($qb, '%' . $escape($value) . '%');
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $adapter->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->like("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nlist':
                    $positive = false;
                    // no break.
                case 'list':
                    $list = is_array($value) ? $value : explode("\n", $value);
                    $list = array_filter(array_map('trim', array_map('strval', $list)), 'strlen');
                    if (empty($list)) {
                        continue 2;
                    }
                    $param = $adapter->createNamedParameter($qb, $list);
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $adapter->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->eq("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->in("$valuesAlias.value", $param),
                        $expr->in("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nsw':
                    $positive = false;
                    // no break.
                case 'sw':
                    $param = $adapter->createNamedParameter($qb, $escape($value) . '%');
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $adapter->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->like("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                    break;

                case 'new':
                    $positive = false;
                    // no break.
                case 'ew':
                    $param = $adapter->createNamedParameter($qb, '%' . $escape($value));
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $adapter->getEntityManager()
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->like("$subqueryAlias.title", $param));
                    $predicateExpr = $expr->orX(
                        $expr->in("$valuesAlias.valueResource", $subquery->getDQL()),
                        $expr->like("$valuesAlias.value", $param),
                        $expr->like("$valuesAlias.uri", $param)
                    );
                    break;

                case 'nres':
                    $positive = false;
                    // no break.
                case 'res':
                    $predicateExpr = $expr->eq(
                    "$valuesAlias.valueResource",
                    $adapter->createNamedParameter($qb, $value)
                    );
                    break;

                case 'nex':
                    $positive = false;
                    // no break.
                case 'ex':
                    $predicateExpr = $expr->isNotNull("$valuesAlias.id");
                    break;

                default:
                    continue 2;
            }

            $joinConditions = [];
            // Narrow to specific property, if one is selected
            if ($propertyId) {
                if (is_numeric($propertyId)) {
                    $propertyId = (int) $propertyId;
                } else {
                    $property = $adapter->getPropertyByTerm($propertyId);
                    if ($property) {
                        $propertyId = $property->getId();
                    } else {
                        $propertyId = 0;
                    }
                }
                $joinConditions[] = $expr->eq("$valuesAlias.property", (int) $propertyId);
            }

            if ($positive) {
                $whereClause = '(' . $predicateExpr . ')';
            } else {
                $joinConditions[] = $predicateExpr;
                $whereClause = $expr->isNull("$valuesAlias.id");
            }

            if ($joinConditions) {
                $qb->leftJoin($valuesJoin, $valuesAlias, 'WITH', $expr->andX(...$joinConditions));
            } else {
                $qb->leftJoin($valuesJoin, $valuesAlias);
            }

            if ($where == '') {
                $where = $whereClause;
            } elseif ($joiner == 'or') {
                $where .= " OR $whereClause";
            } else {
                $where .= " AND $whereClause";
            }
        }

        if ($where) {
            $qb->andWhere($where);
        }
    }

    /**
     * Get field from a string: property, class, template, item set or specific.
     *
     * @param string|int $field
     * @return array
     */
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
                '@type' => 'o:Property',
                'type' => 'properties',
                'id' => $property->id(),
                'term' => $field,
                'label' => $property->label(),
            ];
        }

        if ($field === 'o:title') {
            return [
                '@type' => 'o:Property',
                'type' => 'resource_titles',
                'id' => null,
                'term' => 'o:title',
                'label' => 'Title', // @translate
            ];
        }

        if (isset($this->resourceClasses[$field])) {
            $resourceClass = $this->resourceClasses[$field];
            return [
                '@type' => 'o:ResourceClass',
                'type' => 'resource_classes',
                'id' => $resourceClass->id(),
                'term' => $field,
                'label' => $resourceClass->label(),
            ];
        }

        if (isset($this->resourceTemplates[$field])) {
            $resourceTemplate = $this->resourceTemplates[$field];
            return [
                '@type' => 'o:ResourceTemplate',
                'type' => 'resource_templates',
                'id' => $resourceTemplate->id(),
                'term' => $field,
                'label' => $resourceTemplate->label(),
            ];
        }

        if (is_numeric($field)) {
            try {
                $itemSet = $this->api->read('item_sets', $field)->getContent();
                return [
                    '@type' => 'o:ItemSet',
                    'type' => 'item_sets',
                    'id' => $itemSet->id(),
                    'term' => $field,
                    'label' => $itemSet->displayTitle(),
                ];
            } catch (\Omeka\Api\Exception\NotFoundException $e) {
            }
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
        return $resourceEntityMap[$resourceName] ?? \Omeka\Entity\Resource::class;
    }
}
