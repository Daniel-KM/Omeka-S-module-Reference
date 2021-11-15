<?php declare(strict_types=1);

namespace Reference\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Adapter\Manager as AdapterManager;
use Omeka\Entity\User;
use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Mvc\Controller\Plugin\Translate;
use Omeka\Permissions\Acl;

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
     * @var Acl
     */
    protected $acl;

    /**
     * @var ?User
     */
    protected $user;

    /**
     * @var Api
     */
    protected $api;

    /**
     * @var Translate
     */
    protected $translate;

    /**
     * @param bool
     */
    protected $supportAnyValue;

    /**
     * List of property main data by term and id.
     *
     * @var array
     */
    protected $propertiesByTermsAndIds;

    /**
     * List of resource class main data by term and id.
     *
     * @var array
     */
    protected $resourceClassesByTermsAndIds;

    /**
     * List of resource template main data by label and id.
     *
     * @var array
     */
    protected $resourceTemplatesByLabelsAndIds;

    /**
     * List of item sets by title and id.
     *
     * Warning: titles are not unique.
     *
     * @var array
     */
    protected $itemSetsByTitlesAndIds;

    /**
     * List of owners by name and id.
     *
     * Warning: only users with resources are owners.
     *
     * @var array
     */
    protected $ownersByNameAndIds;

    /**
     * List of sites by slug and id.
     *
     * @var array
     */
    protected $sitesBySlugAndIds;

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

    public function __construct(
        EntityManager $entityManager,
        AdapterManager $adapterManager,
        Acl $acl,
        ?User $user,
        Api $api,
        Translate $translate,
        $supportAnyValue
    ) {
        $this->entityManager = $entityManager;
        $this->adapterManager = $adapterManager;
        $this->acl = $acl;
        $this->user = $user;
        $this->api = $api;
        $this->translate = $translate;
        $this->supportAnyValue = $supportAnyValue;
    }

    /**
     * Get the references.
     *
     * @param array $metadata Classes, properties terms, template names, or
     * other Omeka metadata names. Similar types of metadata may be grouped to
     * get aggregated references, for example ['Dates' => ['dcterms:date', 'dcterms:issued']],
     * with the key used as key and label in the result.
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
     * - single_reference_format: false (default), or true to keep the old output
     *   without the deprecated warning for single references without named key.
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
     * List of metadata to get references for.
     *
     * A metadata may be a single field, or an array of fields.

     * @param array $metadata
     * @return self
     */
    public function setMetadata(array $metadata = null)
    {
        $this->metadata = $metadata ?: [];

        // Check if one of the metadata fields is a short aggregated one.
        foreach ($this->metadata as &$fieldElement) {
            if (!is_array($fieldElement) && strpos($fieldElement, ',') !== false) {
                $fieldElement = array_filter(array_map('trim', explode(',', $fieldElement)));
            }
        }
        unset($fieldElement);

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
        unset($query['sort_by']);
        unset($query['sort_order']);
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
            'single_reference_format' => false,
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
                'include_without_meta' => !empty($options['include_without_meta']),
                'single_reference_format' => !empty($options['single_reference_format']),
                'output' => @$options['output'] === 'associative' && !$first && !$listByMax && !$initial && !$distinct && !$datatype && !$lang
                    ? 'associative'
                    : 'list',
            ];

            // The check for length avoids to add a filter on values without any
            // language. It should be specified as "||" (or leading/trailing "|").
            if (!is_array($this->options['filters']['languages'])) {
                $this->options['filters']['languages'] = explode('|', str_replace(',', '|', $this->options['filters']['languages'] ?: ''));
            }
            $this->options['filters']['languages'] = array_unique(array_map('trim', $this->options['filters']['languages']));
            if (!is_array($this->options['filters']['datatypes'])) {
                $this->options['filters']['datatypes'] = explode('|', str_replace(',', '|', $this->options['filters']['datatypes'] ?: ''));
            }
            $this->options['filters']['datatypes'] = array_unique(array_filter(array_map('trim', $this->options['filters']['datatypes'])));

            // No trim for begin/end.
            if (!is_array($this->options['filters']['begin'])) {
                $this->options['filters']['begin'] = explode('|', str_replace(',', '|', $this->options['filters']['begin'] ?? ''));
            }
            $this->options['filters']['begin'] = array_unique(array_filter($this->options['filters']['begin']));
            if (!is_array($this->options['filters']['end'])) {
                $this->options['filters']['end'] = explode('|', str_replace(',', '|', $this->options['filters']['end'] ?? ''));
            }
            $this->options['filters']['end'] = array_unique(array_filter($this->options['filters']['end']));

            if (!is_array($this->options['fields'])) {
                $this->options['fields'] = explode('|', str_replace(',', '|', $this->options['fields'] ?? ''));
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

        $isAssociative = $this->options['output'] === 'associative';

        // TODO Convert all queries into a single or two sql queries (at least for properties and classes).
        // TODO Return all needed columns.

        $result = [];
        foreach ($fields as $keyOrLabel => $inputField) {
            $dataFields = $this->prepareFields($inputField, $keyOrLabel);

            $keyResult = $dataFields['key_result'];

            if ($dataFields['is_single']
                && (empty($keyOrLabel) || is_numeric($keyOrLabel))
                && !$isAssociative
            ) {
                $result[$keyResult] = reset($dataFields['output']['o:request']['o:field']);
                if (!$this->options['single_reference_format']) {
                    $result[$keyResult] = ['deprecated' => 'This output format is deprecated. Set a string key to metadata to use the new format or append option "single_reference_format" to remove this warning.'] // @translate
                        + $result[$keyResult];
                }
            } else {
                $result[$keyResult] = $dataFields['output'];
            }

            if (in_array($dataFields['type'], ['properties', 'resource_classes', 'resource_templates', 'item_sets'])) {
                $ids = array_column($dataFields['output']['o:request']['o:field'], 'o:id');
            }

            switch ($dataFields['type']) {
                case 'properties':
                    $result[$keyResult]['o:references'] = $this->listDataForProperties($ids);
                    break;

                case 'resource_classes':
                    $result[$keyResult]['o:references'] = $this->listDataForResourceClasses($ids);
                    break;

                case 'resource_templates':
                    $result[$keyResult]['o:references'] = $this->listDataForResourceTemplates($ids);
                    break;

                case 'item_sets':
                    $result[$keyResult]['o:references'] = $this->listDataForItemSets($ids);
                    break;

                case 'resource_titles':
                    $result[$keyResult]['o:references'] = $this->listDataForResourceTitle();
                    break;

                case 'o:property':
                    $values = $this->listProperties();
                    if ($isAssociative) {
                        $result[$keyResult]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $valueData) {
                            $property = $this->getProperty($valueData['val']);
                            $property['o:label'] = $this->translate->__invoke($property['o:label']);
                            $result[$keyResult]['o:references'][] = $property + $valueData;
                        }
                    }
                    break;

                case 'o:resource_class':
                    $values = $this->listResourceClasses();
                    if ($isAssociative) {
                        $result[$keyResult]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $valueData) {
                            $resourceClass = $this->getResourceClass($valueData['val']);
                            $resourceClass['o:label'] = $this->translate->__invoke($resourceClass->label());
                            $result[$keyResult]['o:references'][] = $resourceClass + $valueData;
                        }
                    }
                    break;

                case 'o:resource_template':
                    $values = $this->listResourceTemplates();
                    if ($isAssociative) {
                        $result[$keyResult]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $valueData) {
                            $resourceTemplate = $this->getResourceTemplates($valueData['val']);
                            $result[$keyResult]['o:references'][] = $resourceTemplate + $valueData;
                        }
                    }
                    break;

                case 'o:item_set':
                    // Manage an exception for the resource "items".
                    if ($dataFields['type'] === 'o:item_set' && $this->options['resource_name'] !== 'items') {
                        $values = [];
                    } else {
                        $values = $this->listItemSets();
                    }
                    if ($isAssociative) {
                        $result[$keyResult]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $valueData) {
                            $meta = $this->getItemSet($valueData['val']);
                            $result[$keyResult]['o:references'][] = $meta + $valueData;
                        }
                    }
                    break;

                case 'o:owner':
                    $values = $this->listOwners();
                    if ($isAssociative) {
                        $result[$keyResult]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $valueData) {
                            $meta = $this->getOwner($valueData['val']);
                            $result[$keyResult]['o:references'][] = $meta + $valueData;
                        }
                    }
                    break;

                case 'o:site':
                    $values = $this->listSites();
                    if ($isAssociative) {
                        $result[$keyResult]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $valueData) {
                            $meta = $this->getSite($valueData['val']);
                            $result[$keyResult]['o:references'][] = $meta + $valueData;
                        }
                    }
                    break;

                // Unknown.
                default:
                    $result[$keyResult]['o:references'] = [];
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

        // @todo Manage multiple types at once.
        // @todo Manage multiple resource names (items, item sets, medias) at once.

        $result = [];
        foreach ($fields as $keyOrLabel => $inputField) {
            $dataFields = $this->prepareFields($inputField, $keyOrLabel);

            $keyResult = $dataFields['key_result'] ?: $keyOrLabel;
            if (!in_array($dataFields['type'], ['properties', 'resource_classes', 'resource_templates', 'item_sets'])) {
                $result[$keyResult] = null;
                continue;
            }

            $ids = array_column($dataFields['output']['o:request']['o:field'], 'o:id');
            switch ($dataFields['type']) {
                case 'properties':
                    $result[$keyResult] = $this->countResourcesForProperties($ids);
                    break;
                case 'resource_classes':
                    $result[$keyResult] = $this->countResourcesForResourceClasses($ids);
                    break;
                case 'resource_templates':
                    $result[$keyResult] = $this->countResourcesForResourceTemplates($ids);
                    break;
                case 'item_sets':
                    $result[$keyResult] = $this->countResourcesForItemSets($ids);
                    break;
                default:
                    // Nothing.
                    break;
            }
        }

        return $result;
    }

    /**
     * Get the list of used values for a list of properties, the total for each
     * one and the first item.
     *
     * @param int[] $propertyIds
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listDataForProperties(array $propertyIds): array
    {
        if (empty($propertyIds)) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        // TODO This is no more the case.
        // TODO Check if ANY_VALUE can be replaced by MIN in order to remove it.
        // Note: Doctrine requires simple label, without quote or double quote:
        // "o:label" is not possible, neither "count".

        $qb
            ->select(
                $this->supportAnyValue
                    ? 'ANY_VALUE(COALESCE(value.value, valueResource.title, value.uri)) AS val'
                    : 'COALESCE(value.value, valueResource.title, value.uri) AS val',
                // "Distinct" avoids to count duplicate values in properties in
                // a resource: we count resources, not properties.
                $expr->countDistinct('resource.id') . ' AS total'
            )
            ->from(\Omeka\Entity\Value::class, 'value')
            // This join allow to check visibility automatically too.
            ->innerJoin($this->options['entity_class'], 'resource', Join::WITH, $expr->eq('value.resource', 'resource'))
            // The values should be distinct for each type.
            ->leftJoin($this->options['entity_class'], 'valueResource', Join::WITH, $expr->eq('value.valueResource', 'valueResource'))
            ->andWhere($expr->in('value.property', ':properties'))
            ->setParameter('properties', array_map('intval', $propertyIds), Connection::PARAM_INT_ARRAY)
            ->groupBy('val')
        ;

        return $this
            ->filterByDatatype($qb)
            ->filterByLanguage($qb)
            ->filterByBeginOrEnd($qb)
            ->manageOptions($qb, 'properties')
            ->outputMetadata($qb, 'properties');
    }

    /**
     * Get the list of used values for a list of resource classes, the total for
     * each one and the first item.
     *
     * @param int[] $resourceClassIds
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listDataForResourceClasses(array $resourceClassIds): array
    {
        if (empty($resourceClassIds)) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select(
                'DISTINCT resource.title AS val',
                $expr->count('resource.id') . ' AS total'
            )
            // The use of resource checks visibility automatically.
            ->from(\Omeka\Entity\Resource::class, 'resource')
            ->where($expr->in('resource.resourceClass', ':resource_classes'))
            ->setParameter('resource_classes', array_map('intval', $resourceClassIds), Connection::PARAM_INT_ARRAY)
            ->groupBy('val');

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
        }

        return $this
            ->manageOptions($qb, 'resource_classes')
            ->outputMetadata($qb, 'resource_classes');
    }

    /**
     * Get the list of used values for a list of resource templates, the total
     * for each one and the first item.
     *
     * @param int[] $resourceTemplateIds
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listDataForResourceTemplates(array $resourceTemplateIds)
    {
        if (empty($resourceTemplateIds)) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select(
                'DISTINCT resource.title AS val',
                $expr->count('resource.id') . ' AS total'
            )
            // The use of resource checks visibility automatically.
            ->from(\Omeka\Entity\Resource::class, 'resource')
            ->where($expr->in('resource.resourceTemplate', ':resource_templates'))
            ->setParameter('resource_templates', array_map('intval', $resourceTemplateIds), Connection::PARAM_INT_ARRAY)
            ->groupBy('val');

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
        }

        return $this
            ->manageOptions($qb, 'resource_templates')
            ->outputMetadata($qb, 'resource_templates');
    }

    /**
     * Get the list of used values for a list of item sets, the total for each
     * one and the first item.
     *
     * @param int[] $itemSetIds
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listDataForItemSets(array $itemSetIds): array
    {
        if (empty($itemSetIds)) {
            return [];
        }

        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        if ($this->options['entity_class'] !== \Omeka\Entity\Item::class) {
            return [];
        }

        $qb
            ->select(
                'DISTINCT resource.title AS val',
                $expr->count('resource.id') . ' AS total'
            )
            // The use of resource checks visibility automatically.
            ->from(\Omeka\Entity\Resource::class, 'resource')
            // Always an item.
            ->innerJoin(\Omeka\Entity\Item::class, 'res', Join::WITH, 'res.id = resource.id')
            ->innerJoin(
                'res.itemSets',
                'item_set',
                Join::WITH,
                $expr->in('item_set.id', ':item_sets')
            )
            ->setParameter('item_sets', array_map('intval', $itemSetIds), Connection::PARAM_INT_ARRAY)
            ->groupBy('val')
        ;

        return $this
            ->manageOptions($qb, 'item_sets')
            ->outputMetadata($qb, 'item_sets');
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
            ->select(
                $this->supportAnyValue
                    ? 'ANY_VALUE(resource.title) AS val'
                    : 'resource.title AS val',
                // "Distinct" avoids to count duplicate values in properties in
                // a resource: we count resources, not properties.
                $expr->countDistinct('resource.id') . ' AS total'
            )
            ->from(\Omeka\Entity\Resource::class, 'resource')
            // This join allow to check visibility automatically too.
            ->innerJoin($this->options['entity_class'], 'res', Join::WITH, $expr->eq('res', 'resource'))
            ->groupBy('val')
        ;

        return $this
            // TODO Improve filter for "o:title".
            // ->filterByDatatype($qb)
            // ->filterByLanguage($qb)
            ->filterByBeginOrEnd($qb, 'resource.title')
            ->manageOptions($qb, 'resource_titles')
            ->outputMetadata($qb, 'properties');
    }

    /**
     * Get the list of used properties references by metadata name, the total
     * for each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listProperties(): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select(
                // 'property.label as val',
                // Important: use single quote for string ":", else doctrine fails.
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

        return $this
            ->filterByLanguage($qb)
            ->manageOptions($qb, 'o:property')
            ->outputMetadata($qb, 'o:property');
    }

    /**
     * Get the list of used resource classes by metadata name, the total for
     * each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listResourceClasses():array
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
                // Important: use single quote for string ":", else doctrine fails.
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

        return $this
            ->manageOptions($qb, 'o:resource_class')
            ->outputMetadata($qb, 'o:resource_class');
    }

    /**
     * Get the list of used resource templates by metadata name, the total for
     * each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listResourceTemplates(): array
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

        return $this
            ->manageOptions($qb, 'o:resource_template')
            ->outputMetadata($qb, 'o:resource_template');
    }

    /**
     * Get the list of used item sets, the total for each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listItemSets(): array
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

        return $this
            // By exeption, the query for item sets should add public site,
            // because item sets are limited by sites.
            ->limitItemSetsToSite($qb)
            ->manageOptions($qb, 'o:item_set')
            ->outputMetadata($qb, 'o:item_set');
    }

    /**
     * Get the list of owners, the total for each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listOwners()
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        // Count the number of items by owner.

        // TODO Get all owners, even without items (or private items).
        /*
         SELECT DISTINCT user.name AS val, COUNT(resource.user_id) AS total
         FROM resource resource
         LEFT JOIN user user ON user.id = resource.user_id
         GROUP BY val;
         */

        $qb
            ->select(
                'user.name as val',
                'COUNT(resource.id) AS total'
            )
            // The use of resource checks visibility automatically.
            ->from(\Omeka\Entity\Resource::class, 'resource')
            // Check visibility automatically.
            ->leftJoin(
                \Omeka\Entity\User::class,
                'user',
                Join::WITH,
                $expr->eq('user', 'resource.owner')
            )
            ->groupBy('val')
        ;

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, $expr->eq('res.id', 'resource.id'));
        }

        return $this
            ->manageOptions($qb, 'o:owner')
            ->outputMetadata($qb, 'o:owner');
    }

    /**
     * Get the list of sites, the total for each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listSites()
    {
        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        // Count the number of items by site.

        // TODO Get all sites, even without items (or private items).

        $qb
            ->select(
                'site.slug as val',
                'COUNT(resource.id) AS total'
            )
            // The use of resource checks visibility automatically.
            ->from(\Omeka\Entity\Resource::class, 'resource')
            ->innerJoin(
                \Omeka\Entity\Item::class,
                'res',
                Join::WITH,
                $expr->eq('res.id', 'resource.id')
            )
            ->leftJoin(
                'res.sites',
                'site'
            )
            ->groupBy('val')
        ;

        // TODO Count item sets and media by site.

        return $this
            ->manageOptions($qb, 'o:site')
            ->outputMetadata($qb, 'o:site');
    }

    protected function limitItemSetsToSite(QueryBuilder $qb): self
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
                // @see \Omeka\Api\Adapter\ItemSetAdapter::buildQuery()
                ->innerJoin('item_set.siteItemSets', 'ref_site_item_set')
                ->andWhere($expr->eq('ref_site_item_set.site', ':ref_site_item_set_site'))
                ->setParameter(':ref_site_item_set_site', $this->query['site_id']);
        }
        return $this;
    }

    protected function filterByDatatype(QueryBuilder $qb): self
    {
        if ($this->options['filters']['datatypes']) {
            $expr = $qb->expr();
            $qb
                ->andWhere($expr->in('value.type', ':datatypes'))
                ->setParameter('datatypes', $this->options['filters']['datatypes'], Connection::PARAM_STR_ARRAY);
        }
        return $this;
    }

    protected function filterByLanguage(QueryBuilder $qb): self
    {
        if ($this->options['filters']['languages']) {
            $expr = $qb->expr();
            // Note: For an unknown reason, doctrine may crash with "IS NULL" in
            // some non-reproductible cases. Db version related?
            $hasEmptyLanguage = in_array('', $this->options['filters']['languages']);
            $in = $expr->in('value.lang', ':languages');
            $filter = $hasEmptyLanguage ? $expr->orX($in, $expr->isNull('value.lang')) : $in;
            $qb
                ->andWhere($filter)
                ->setParameter('languages', $this->options['filters']['languages'], Connection::PARAM_STR_ARRAY);
        }
        return $this;
    }

    /**
     * Filter the list of references with a column.
     *
     *  @param string The column to filter, for example "value.value" or "resource.title".
     */
    protected function filterByBeginOrEnd(QueryBuilder $qb, $column = 'value.value'): self
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
        return $this;
    }

    protected function manageOptions(QueryBuilder $qb, $type): self
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
                        ? 'ANY_VALUE(' . $expr->upper($expr->substring('COALESCE(value.value, valueResource.title, value.uri)', 1, 1)) . ') AS initial'
                        : $expr->upper($expr->substring('COALESCE(value.value, valueResource.title, value.uri)', 1, 1)) . ' AS initial',
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
                ->leftJoin(
                    \Omeka\Entity\Resource::class,
                    'ress',
                    Join::WITH,
                    $expr->eq($type === 'resource_titles' ? 'resource' : 'value.resource', 'ress')
                )
                ->addSelect(
                    // Note: for doctrine, separators must be set as parameters.
                    'GROUP_CONCAT(ress.id, :unit_separator, ress.title SEPARATOR :group_separator) AS resources',
                )
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
                    $values = $this->getPropertyIds($this->options['values']);
                    if (!$values) {
                        $values = [0];
                    }
                    $qb
                        ->andWhere('property' . '.id IN (:ids)')
                        ->setParameter('ids', $values);
                    break;
                case 'o:resource_class':
                    $values = $this->getResourceClassIds($this->options['values']);
                    if (!$values) {
                        $values = [0];
                    }
                    $qb
                        ->andWhere('resource_class' . '.id IN (:ids)')
                        ->setParameter('ids', $values);
                    break;
                case 'o:resource_template':
                    $values = $this->getResourceTemplateIds($this->options['values']);
                    if (!$values) {
                        $values = [0];
                    }
                    $qb
                        ->andWhere('resource_template' . '.id IN (:ids)')
                        ->setParameter('ids', $this->options['values']);
                    break;
                case 'o:item_set':
                    $qb
                        ->andWhere('item_set.id IN (:ids)')
                        ->setParameter('ids', $this->options['values']);
                    break;
                case 'o:owner':
                    $qb
                        ->andWhere('user.id IN (:ids)')
                        ->setParameter('ids', $this->options['values']);
                    break;
                case 'o:site':
                    $qb
                        ->andWhere('site.id IN (:ids)')
                        ->setParameter('ids', $this->options['values']);
                    break;
                default:
                    break;
            }
        }

        $this->searchQuery($qb, $type);

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

        return $this;
    }

    protected function outputMetadata(QueryBuilder $qb, $type): array
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
                // FIXME Api call inside a loop.
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

    protected function countResourcesForProperties(array $propertyIds): int
    {
        if (empty($propertyIds)) {
            return 0;
        }

        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select(
                // Here, this is the count of references, not resources.
                $expr->countDistinct('value.value')
            )
            ->from(\Omeka\Entity\Value::class, 'value')
            // This join allow to check visibility automatically too.
            ->innerJoin(\Omeka\Entity\Resource::class, 'resource', Join::WITH, $expr->eq('value.resource', 'resource'))
            ->andWhere($expr->in('value.property', ':properties'))
            ->setParameter('properties', array_map('intval', $propertyIds), Connection::PARAM_INT_ARRAY)
            ->andWhere($expr->isNotNull('value.value'));

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, 'res.id = resource.id');
        }

        $this->searchQuery($qb);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    protected function countResourcesForResourceClasses(array $resourceClassIds): int
    {
        if (empty($resourceClassIds)) {
            return 0;
        }

        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select(
                $expr->countDistinct('resource.id')
            )
            ->from(\Omeka\Entity\Resource::class, 'resource')
            ->andWhere($expr->in('resource.resourceClass', ':resource_classes'))
            ->setParameter('resource_classes', array_map('intval', $resourceClassIds), Connection::PARAM_INT_ARRAY);

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, 'res.id = resource.id');
        }

        $this->searchQuery($qb);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    protected function countResourcesForResourceTemplates(array $resourceTemplateIds): int
    {
        if (empty($resourceTemplateIds)) {
            return 0;
        }

        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select(
                $expr->countDistinct('resource.id')
            )
            ->from(\Omeka\Entity\Resource::class, 'resource')
            ->andWhere($expr->in('resource.resourceTemplate', ':resource_templates'))
            ->setParameter('resource_templates', array_map('intval', $resourceTemplateIds), Connection::PARAM_INT_ARRAY);

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, 'res.id = resource.id');
        }

        $this->searchQuery($qb);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    protected function countResourcesForItemSets(array $itemSetIds): int
    {
        if (empty($itemSetIds)) {
            return 0;
        }

        $qb = $this->entityManager->createQueryBuilder();
        $expr = $qb->expr();

        if ($this->options['entity_class'] !== \Omeka\Entity\Item::class) {
            return 0;
        }
        $qb
            ->select(
                $expr->countDistinct('resource.id')
            )
            ->from(\Omeka\Entity\Resource::class, 'resource')
            ->innerJoin(\Omeka\Entity\Item::class, 'res', Join::WITH, 'res.id = resource.id')
            // See \Omeka\Api\Adapter\ItemAdapter::buildQuery()
            ->innerJoin(
                'res.itemSets',
                'item_set',
                Join::WITH,
                $expr->in('item_set.id', ':item_sets')
            )
            ->setParameter('item_sets', array_map('intval', $itemSetIds), Connection::PARAM_INT_ARRAY);

        $this->searchQuery($qb);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Limit the results with a query (generally the site query).
     */
    protected function searchQuery(QueryBuilder $qb, ?string $type = null): self
    {
        if (empty($this->query)) {
            return $this;
        }

        $subQb = $this->entityManager->createQueryBuilder()
            ->select('omeka_root.id')
            ->from($this->options['entity_class'], 'omeka_root');

        $expr = $qb->expr();

        // Support of "starts with" is needed to get all subjects for a letter.
        // So, the properties part of the query is managed separately.
        $mainQuery = $this->query;
        unset($mainQuery['property']);

        // When searching by item set or site, remove the matching query filter.
        if ($type === 'o:item_set') {
            unset($mainQuery['item_set_id']);
        }
        if ($type === 'o:site') {
            unset($mainQuery['site_id']);
        }

        /**
         * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::search()
         * @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter $adapter
         */
        $adapter = $this->adapterManager->get($this->options['resource_name']);
        $adapter->buildBaseQuery($subQb, $mainQuery);
        $adapter->buildQuery($subQb, $mainQuery);

        // Full text search is not managed by adapters, but by a special event.
        if (isset($this->query['fulltext_search'])) {
            $this->buildFullTextSearchQuery($subQb, $adapter);
        }

        // Managed separated properties.
        if (isset($this->query['property']) && is_array($this->query['property'])) {
            $this->buildPropertyQuery($subQb, $adapter);
        }

        // TODO Manage not only standard visibility, but modules ones.
        // TODO Check the visibility for the main queries.
        // Set visibility constraints for users without "view-all" privilege.
        if (!$this->acl->userIsAllowed('Omeka\Entity\Resource', 'view-all')) {
            $constraints = $expr->eq('omeka_root.isPublic', true);
            if ($this->user) {
                // Users can view all resources they own.
                $constraints = $expr->orX(
                    $constraints,
                    $expr->eq('omeka_root.owner', $this->user->getId())
                );
            }
            $subQb->andWhere($constraints);
        }

        // There is no colision: the adapter query uses alias "omeka_" + index.
        $qb
            ->andWhere($expr->in('resource.id', $subQb->getDQL()));

        $subParams = $subQb->getParameters();
        foreach ($subParams as $parameter) {
            $qb->setParameter(
                $parameter->getName(),
                $parameter->getValue(),
                $parameter->getType()
            );
        }

        return $this;
    }

    /**
     * Manage full text search.
     *
     * Full text search is not managed by adapters, but by a special event.
     *
     * This is an adaptation of the core method, except rights check.
     * @see \Omeka\Module::searchFulltext()
     */
    protected function buildFullTextSearchQuery(QueryBuilder $qb, AbstractResourceEntityAdapter $adapter): self
    {
        if (!($adapter instanceof \Omeka\Api\Adapter\FulltextSearchableInterface)) {
            return $this;
        }

        if (!isset($this->query['fulltext_search']) || ('' === trim($this->query['fulltext_search']))) {
            return $this;
        }

        $searchAlias = $adapter->createAlias();

        $match = sprintf(
            'MATCH(%s.title, %s.text) AGAINST (%s)',
            $searchAlias,
            $searchAlias,
            $adapter->createNamedParameter($qb, $this->query['fulltext_search'])
        );
        $joinConditions = sprintf(
            '%s.id = omeka_root.id AND %s.resource = %s',
            $searchAlias,
            $searchAlias,
            $adapter->createNamedParameter($qb, $adapter->getResourceName())
        );

        $qb
            ->innerJoin(\Omeka\Entity\FulltextSearch::class, $searchAlias, Join::WITH, $joinConditions)
            // Filter out resources with no similarity.
            ->andWhere(sprintf('%s > 0', $match))
            // Order by the relevance. Note the use of orderBy() and not
            // addOrderBy(). This should ensure that ordering by relevance
            // is the first thing being ordered.
            ->orderBy($match, 'DESC');

        return $this;
    }

    /**
     * Improve the default property query for resources.
     *
     * @todo Unlike advanced search, does not manage excluded fields.
     *
     * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildPropertyQuery()
     * @see \AdvancedSearch\Listener\SearchResourcesListener::buildPropertyQuery()
     *
     * Complete \Omeka\Api\Adapter\AbstractResourceEntityAdapter::buildPropertyQuery()
     *
     * Query format:
     *
     * - property[{index}][joiner]: "and" OR "or" OR "not" joiner with previous query
     * - property[{index}][property]: property ID
     * - property[{index}][text]: search text
     * - property[{index}][type]: search type
     * - property[{index}][datatype]: filter on data type(s)
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
     *   - res: has resource (core)
     *   - nres: has no resource (core)
     *   For date time only for now (a check is done to have a meaningful answer):
     *   TODO Remove the check for valid date time? Add another key (before/after)?
     *   Of course, it's better to use Numeric Data Types.
     *   - gt: greater than (after)
     *   - gte: greater than or equal
     *   - lte: lower than or equal
     *   - lt: lower than (before)
     *
     * @param QueryBuilder $qb
     * @param AbstractResourceEntityAdapter $adapter
     */
    protected function buildPropertyQuery(QueryBuilder $qb, AbstractResourceEntityAdapter $adapter): self
    {
        if (empty($this->query['property']) || !is_array($this->query['property'])) {
            return $this;
        }

        $valuesJoin = 'omeka_root.values';
        $where = '';
        $expr = $qb->expr();

        $escape = function ($string) {
            return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], (string) $string);
        };

        $reciprocalQueryTypes = [
            'eq' => 'neq',
            'neq' => 'eq',
            'in' => 'nin',
            'nin' => 'in',
            'ex' => 'nex',
            'nex' => 'ex',
            'list' => 'nlist',
            'nlist' => 'list',
            'sw' => 'nsw',
            'nsw' => 'sw',
            'ew' => 'new',
            'new' => 'ew',
            'res' => 'nres',
            'nres' => 'res',
            'gt' => 'lte',
            'gte' => 'lt',
            'lte' => 'gt',
            'lt' => 'gte',
        ];

        foreach ($this->query['property'] as $queryRow) {
            if (!(
                is_array($queryRow)
                && array_key_exists('property', $queryRow)
                && array_key_exists('type', $queryRow)
            )) {
                continue;
            }

            $queryType = $queryRow['type'];
            $joiner = $queryRow['joiner'] ?? '';
            $value = $queryRow['text'] ?? '';
            $dataType = $queryRow['datatype'] ?? '';

            // A value can be an array with types "list" and "nlist".
            if (!is_array($value)
                && !strlen((string) $value)
                && $queryType !== 'nex'
                && $queryType !== 'ex'
            ) {
                continue;
            }

            // Invert the query type for joiner "not".
            if ($joiner === 'not') {
                $joiner = 'and';
                $queryType = $reciprocalQueryTypes[$queryType];
            }

            $propertyIds = $queryRow['property'];
            if ($propertyIds) {
                $propertyIds = $this->getPropertyIds(is_array($propertyIds) ? $propertyIds : [$propertyIds]);
            }

            $valuesAlias = $adapter->createAlias();
            $positive = true;
            $incorrectValue = false;

            switch ($queryType) {
                case 'neq':
                    $positive = false;
                    // no break.
                case 'eq':
                    $param = $adapter->createNamedParameter($qb, $value);
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $this->entityManager
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
                    $subquery = $this->entityManager
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
                    $list = array_unique(array_filter(array_map('trim', array_map('strval', $list)), 'strlen'));
                    if (empty($list)) {
                        continue 2;
                    }
                    $param = $adapter->createNamedParameter($qb, $list);
                    $subqueryAlias = $adapter->createAlias();
                    $subquery = $this->entityManager
                        ->createQueryBuilder()
                        ->select("$subqueryAlias.id")
                        ->from('Omeka\Entity\Resource', $subqueryAlias)
                        ->where($expr->in("$subqueryAlias.title", $param));
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
                    $subquery = $this->entityManager
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
                    $subquery = $this->entityManager
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

                    // TODO Manage uri and resources with gt, gte, lte, lt (it has a meaning at least for resource ids, but separate).
                case 'gt':
                    $valueNorm = $this->getDateTimeFromValue($value, false);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $predicateExpr = $expr->gt(
                            "$valuesAlias.value",
                            $adapter->createNamedParameter($qb, $valueNorm)
                        );
                    }
                    break;
                case 'gte':
                    $valueNorm = $this->getDateTimeFromValue($value, true);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $predicateExpr = $expr->gte(
                            "$valuesAlias.value",
                            $adapter->createNamedParameter($qb, $valueNorm)
                        );
                    }
                    break;
                case 'lte':
                    $valueNorm = $this->getDateTimeFromValue($value, false);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $predicateExpr = $expr->lte(
                            "$valuesAlias.value",
                            $adapter->createNamedParameter($qb, $valueNorm)
                        );
                    }
                    break;
                case 'lt':
                    $valueNorm = $this->getDateTimeFromValue($value, true);
                    if (is_null($valueNorm)) {
                        $incorrectValue = true;
                    } else {
                        $predicateExpr = $expr->lt(
                            "$valuesAlias.value",
                            $adapter->createNamedParameter($qb, $valueNorm)
                        );
                    }
                    break;

                default:
                    continue 2;
            }

            $joinConditions = [];
            // Narrow to specific property, if one is selected.
            // The check is done against the requested property, like in core:
            // when user request is invalid, return an empty result.
            if ($queryRow['property']) {
                $joinConditions[] = count($propertyIds) < 2
                    // There may be 0 or 1 property id.
                    ? $expr->eq("$valuesAlias.property", (int) reset($propertyIds))
                    : $expr->in("$valuesAlias.property", $propertyIds);
            }

            // Avoid to get results when the query is incorrect.
            if ($incorrectValue) {
                $where = $expr->eq('omeka_root.id', 0);
                break;
            }

            if ($dataType) {
                if (is_array($dataType)) {
                    $dataTypeAlias = $adapter->createAlias();
                    $qb->setParameter($dataTypeAlias, $dataType, \Doctrine\DBAL\Connection::PARAM_STR_ARRAY);
                    $predicateExpr = $expr->andX(
                        $predicateExpr,
                        $expr->in("$valuesAlias.type", ':' . $dataTypeAlias)
                    );
                } else {
                    $dataTypeAlias = $adapter->createNamedParameter($qb, $dataType);
                    $predicateExpr = $expr->andX(
                        $predicateExpr,
                        $expr->eq("$valuesAlias.type", $dataTypeAlias)
                    );
                }
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

        return $this;
    }

    /**
     * Get precise field from a string or a list of strings.
     *
     * A field is a property, a class, a template, an item set or a specific
     * data, or a non-mixed list of them.
     *
     * When the field is a list of field, the check is done against the first
     * field only.
     *
     * @todo Get item sets by ids, not only by title.
     *
     * @param array|string $fields
     * @param string $keyOrLabelRequest Set a label for lists only.
     * @return array
     */
    protected function prepareFields($fields, $keyOrLabelRequest = null): array
    {
        $metaToTypes = [
            'o:property' => 'properties',
            'o:resource_class' => 'resource_classes',
            'o:resource_template' => 'resource_templates',
            // Item sets is only for items.
            'o:item_set' => 'item_sets',
            // Owners is only for items.
            'o:owner' => 'owners',
            // Sites is only for items.
            'o:site' => 'sites',
        ];

        $isSingle = !is_array($fields);
        $fields = $isSingle ? [$fields] : $fields;
        $field = reset($fields);

        $translate = $this->translate;

        $labelRequested = empty($keyOrLabelRequest) || is_numeric($keyOrLabelRequest)
            ? null
            : $keyOrLabelRequest;

        // Special fields.
        if (isset($metaToTypes[$field])) {
            $labels = [
                'o:property' => $translate('Properties'), // @translate
                'o:resource_class' => $translate('Classes'), // @translate
                'o:resource_template' => $translate('Templates'), // @translate
                'o:item_set' => $translate('Item sets'), // @translate
                'o:owner' => $translate('Owners'), // @translate
                'o:site' => $translate('Sites'), // @translate
            ];
            return [
                'type' => $field,
                'output' => [
                    'o:label' => $labelRequested ?? $labels[$field],
                    'o:request' => [
                        'o:field' => [[
                            '@type' => null,
                            'o:term' => $field,
                            'o:label' => $labels[$field],
                        ]],
                    ],
                ],
                'is_single' => $isSingle,
                'label_requested' => $labelRequested,
                'label_first' => $labels[$field],
                'key_result' => $labelRequested
                    // For compatibility with old format.
                    ?? ($isSingle ? $field : $keyOrLabelRequest),
            ];
        }

        // It's not possible to determine what is a numeric value.
        if (is_numeric($field)) {
            $meta = [];
            foreach ($fields as $fieldElement) {
                $meta[] = [
                    '@type' => null,
                    'o:label' => $fieldElement,
                ];
            }
            return [
                'type' => null,
                'output' => [
                    'o:label' => $labelRequested ?? $translate('[Unknown]'), // @translate
                    'o:request' => [
                        'o:field' => $meta,
                    ],
                ],
                'is_single' => $isSingle,
                'label_requested' => $labelRequested,
                'label_first' => $field,
                'key_result' => $keyOrLabelRequest,
            ];
        }

        // Cannot be mixed currently.
        if ($field === 'o:title') {
            $label = $translate('Title'); // @translate
            return [
                'type' => 'resource_titles',
                'output' => [
                    'o:label' => $labelRequested ?? $label,
                    'o:request' => [
                        'o:field' => [[
                            '@type' => null,
                            'o:term' => 'o:title',
                            'o:label' => $label,
                        ]],
                    ],
                ],
                'is_single' => $isSingle,
                'label_requested' => $labelRequested,
                'label_first' => $label,
                'key_result' => $labelRequested
                    // For compatibility with old format.
                    ?? ($isSingle ? 'o:title' : $keyOrLabelRequest),
            ];
        }

        $meta = $this->getProperties($fields);
        if ($meta) {
            foreach ($meta as &$metaElement) {
                unset($metaElement['@language']);
                $metaElement = [
                    '@type' => 'o:Property',
                ] + $metaElement;
            }
            unset($metaElement);
            $labelFirst = $translate(reset($meta)['o:label']);
            return [
                'type' => 'properties',
                'output' => [
                    'o:label' => $labelRequested ?? $labelFirst,
                    'o:request' => [
                        'o:field' => $meta,
                    ],
                ],
                'is_single' => $isSingle,
                'label_requested' => $labelRequested,
                'label_first' => $labelFirst,
                'key_result' => $labelRequested
                    // For compatibility with old format.
                    ?? ($isSingle ? reset($meta)['o:term'] : $keyOrLabelRequest),
            ];
        }

        $meta = $this->getResourceClasses($fields);
        if ($meta) {
            foreach ($meta as &$metaElement) {
                unset($metaElement['@language']);
                $metaElement = [
                    '@type' => 'o:ResourceClass',
                ] + $metaElement;
            }
            unset($metaElement);
            $labelFirst = $translate(reset($meta)['o:label']);
            return [
                'type' => 'resource_classes',
                'output' => [
                    'o:label' => $labelRequested ?? $labelFirst,
                    'o:request' => [
                        'o:field' => $meta,
                    ],
                ],
                'is_single' => $isSingle,
                'label_requested' => $labelRequested,
                'label_first' => $labelFirst,
                'key_result' => $labelRequested
                    // For compatibility with old format.
                    ?? ($isSingle ? reset($meta)['o:term'] : $keyOrLabelRequest),
            ];
        }

        $meta = $this->getResourceTemplates($fields);
        if ($meta) {
            foreach ($meta as &$metaElement) {
                unset($metaElement['@language']);
                $metaElement = [
                    '@type' => 'o:ResourceTemplate',
                ] + $metaElement;
            }
            unset($metaElement);
            $labelFirst = $translate(reset($meta)['o:label']);
            return [
                'type' => 'resource_templates',
                'output' => [
                    'o:label' => $labelRequested ?? $labelFirst,
                    'o:request' => [
                        'o:field' => $meta,
                    ],
                ],
                'is_single' => $isSingle,
                'label_requested' => $labelRequested,
                'label_first' => $labelFirst,
                'key_result' => $keyOrLabelRequest,
            ];
        }

        $meta = $this->getItemSets($fields);
        if ($meta) {
            foreach ($meta as &$metaElement) {
                unset($metaElement['@language']);
                $metaElement = [
                    '@type' => 'o:ItemSet',
                ] + $metaElement;
            }
            unset($metaElement);
            $labelFirst = $translate(reset($meta)['o:label']);
            return [
                'type' => 'item_sets',
                'output' => [
                    'o:label' => $labelRequested ?? $labelFirst,
                    'o:request' => [
                        'o:field' => $meta,
                    ],
                ],
                'is_single' => $isSingle,
                'label_requested' => $labelRequested,
                'label_first' => $labelFirst,
                'key_result' => $keyOrLabelRequest,
            ];
        }

        // Undetermined.
        $meta = [];
        foreach ($fields as $fieldElement) {
            $meta[] = [
                '@type' => null,
                'o:label' => $fieldElement,
            ];
        }
        unset($metaElement);
        return [
            'type' => null,
            'output' => [
                'o:label' => $labelRequested ?? $field,
                'o:request' => [
                    'o:field' => $meta,
                ],
            ],
            'is_single' => $isSingle,
            'label_requested' => $labelRequested,
            'label_first' => $field,
            'key_result' => $keyOrLabelRequest,
        ];
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

    /**
     * Get property ids by JSON-LD terms or by numeric ids.
     *
     * @return int[]
     */
    protected function getPropertyIds(array $termsOrIds): array
    {
        if (is_null($this->propertiesByTermsAndIds)) {
            $this->prepareProperties();
        }
        return array_column(array_intersect_key($this->propertiesByTermsAndIds, array_flip($termsOrIds)), 'o:id');
    }

    /**
     * Get a property id by JSON-LD term or by numeric id.
     */
    protected function getPropertyId($termOrId): ?int
    {
        if (is_null($this->propertiesByTermsAndIds)) {
            $this->prepareProperties();
        }
        return $this->propertiesByTermsAndIds[$termOrId]['o:id'] ?? null;
    }

    /**
     * Get property by JSON-LD terms or by numeric ids.
     *
     * @return int[]
     */
    protected function getProperties(array $termsOrIds): array
    {
        if (is_null($this->propertiesByTermsAndIds)) {
            $this->prepareProperties();
        }
        return array_values(array_intersect_key($this->propertiesByTermsAndIds, array_flip($termsOrIds)));
    }

    /**
     * Get a property by JSON-LD term or by numeric id.
     */
    protected function getProperty($termOrId): ?array
    {
        if (is_null($this->propertiesByTermsAndIds)) {
            $this->prepareProperties();
        }
        return $this->propertiesByTermsAndIds[$termOrId] ?? null;
    }

    /**
     * Prepare the list of properties.
     */
    protected function prepareProperties(): self
    {
        if (is_null($this->propertiesByTermsAndIds)) {
            $connection = $this->entityManager->getConnection();
            // Here, the dbal query builder is used.
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    'DISTINCT CONCAT(vocabulary.prefix, ":", property.local_name) AS "o:term"',
                    'property.label AS "o:label"',
                    'property.id AS "o:id"',
                    'NULL AS "@language"',
                    // Only the two first selects are needed, but some databases
                    // require "order by" or "group by" value to be in the select.
                    'vocabulary.id',
                    'property.id'
                )
                ->from('property', 'property')
                ->innerJoin('property', 'vocabulary', 'vocabulary', 'property.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('property.id', 'asc')
                ->addGroupBy('property.id')
            ;
            $results= $connection->executeQuery($qb)->fetchAllAssociative();
            $this->propertiesByTermsAndIds = [];
            foreach ($results as $result) {
                unset($result['id']);
                $result['o:id'] = (int) $result['o:id'];
                $this->propertiesByTermsAndIds[$result['o:id']] = $result;
                $this->propertiesByTermsAndIds[$result['o:term']] = $result;
            }
        }
        return $this;
    }

    /**
     * Get resource class ids by JSON-LD terms or by numeric ids.
     *
     * @return int[]
     */
    protected function getResourceClassIds(array $termsOrIds): array
    {
        if (is_null($this->resourceClassesByTermsAndIds)) {
            $this->prepareResourceClasses();
        }
        return array_column(array_intersect_key($this->resourceClassesByTermsAndIds, array_flip($termsOrIds)), 'o:id');
    }

    /**
     * Get resource class id by JSON-LD term or by numeric id.
     */
    protected function getResourceClassId($termOrId): ?int
    {
        if (is_null($this->resourceClassesByTermsAndIds)) {
            $this->prepareResourceClasses();
        }
        return $this->resourceClassesByTermsAndIds[$termOrId]['o:id'] ?? null;
    }

    /**
     * Get resource classes by JSON-LD terms or by numeric ids.
     *
     * @return int[]
     */
    protected function getResourceClasses(array $termsOrIds): array
    {
        if (is_null($this->resourceClassesByTermsAndIds)) {
            $this->prepareResourceClasses();
        }
        return array_values(array_intersect_key($this->resourceClassesByTermsAndIds, array_flip($termsOrIds)));
    }

    /**
     * Get resource class by JSON-LD term or by numeric id.
     */
    protected function getResourceClass($termOrId): ?array
    {
        if (is_null($this->resourceClassesByTermsAndIds)) {
            $this->prepareResourceClasses();
        }
        return $this->resourceClassesByTermsAndIds[$termOrId] ?? null;
    }

    /**
     * Prepare the list of resource classes.
     */
    protected function prepareResourceClasses(): self
    {
        if (is_null($this->resourceClassesByTermsAndIds)) {
            $connection = $this->entityManager->getConnection();
            // Here, the dbal query builder is used.
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    'DISTINCT CONCAT(vocabulary.prefix, ":", resource_class.local_name) AS "o:term"',
                    'resource_class.label AS "o:label"',
                    'resource_class.id AS "o:id"',
                    'NULL AS "@language"',
                    // Only the two first selects are needed, but some databases
                    // require "order by" or "group by" value to be in the select.
                    'vocabulary.id',
                    'resource_class.id'
                )
                ->from('resource_class', 'resource_class')
                ->innerJoin('resource_class', 'vocabulary', 'vocabulary', 'resource_class.vocabulary_id = vocabulary.id')
                ->orderBy('vocabulary.id', 'asc')
                ->addOrderBy('resource_class.id', 'asc')
                ->addGroupBy('resource_class.id')
            ;
            $results= $connection->executeQuery($qb)->fetchAllAssociative();
            $this->resourceClassesByTermsAndIds = [];
            foreach ($results as $result) {
                unset($result['id']);
                $result['o:id'] = (int) $result['o:id'];
                $this->resourceClassesByTermsAndIds[$result['o:id']] = $result;
                $this->resourceClassesByTermsAndIds[$result['o:term']] = $result;
            }
        }
        return $this;
    }

    /**
     * Get resource template ids by labels or by numeric ids.
     *
     * @return int[]
     */
    protected function getResourceTemplateIds(array $labelsOrIds): array
    {
        if (is_null($this->resourceTemplatesByLabelsAndIds)) {
            $this->prepareResourceTemplates();
        }
        return array_column(array_intersect_key($this->resourceTemplatesByLabelsAndIds, array_flip($labelsOrIds)), 'o:id');
    }

    /**
     * Get resource template id by label or by numeric id.
     */
    protected function getResourceTemplateId($labelOrId): ?int
    {
        if (is_null($this->resourceTemplatesByLabelsAndIds)) {
            $this->prepareResourceTemplates();
        }
        return $this->resourceTemplatesByLabelsAndIds[$labelOrId]['o:id'] ?? null;
    }

    /**
     * Get resource template ids by labels or by numeric ids.
     *
     * @return int[]
     */
    protected function getResourceTemplates(array $labelsOrIds): array
    {
        if (is_null($this->resourceTemplatesByLabelsAndIds)) {
            $this->prepareResourceTemplates();
        }
        return array_values(array_intersect_key($this->resourceTemplatesByLabelsAndIds, array_flip($labelsOrIds)));
    }

    /**
     * Get resource template by label or by numeric id.
     */
    protected function getResourceTemplate($labelOrId): ?array
    {
        if (is_null($this->resourceTemplatesByLabelsAndIds)) {
            $this->prepareResourceTemplates();
        }
        return $this->resourceTemplatesByLabelsAndIds[$labelOrId] ?? null;
    }

    /**
     * Prepare the list of resource templates.
     */
    protected function prepareResourceTemplates(): self
    {
        if (is_null($this->resourceTemplatesByLabelsAndIds)) {
            $connection = $this->entityManager->getConnection();
            // Here, the dbal query builder is used.
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    'DISTINCT resource_template.label AS "o:label"',
                    'resource_template.id AS "o:id"',
                    'NULL AS "@language"',
                    // Only the two first selects are needed, but some databases
                    // require "order by" or "group by" value to be in the select.
                    'resource_template.id'
                )
                ->from('resource_template', 'resource_template')
                ->orderBy('resource_template.id', 'asc')
                ->addGroupBy('resource_template.id')
            ;
            $results= $connection->executeQuery($qb)->fetchAllAssociative();
            $this->resourceTemplatesByLabelsAndIds = [];
            foreach ($results as $result) {
                unset($result['id']);
                $result['o:id'] = (int) $result['o:id'];
                $this->resourceTemplatesByLabelsAndIds[$result['o:id']] = $result;
                $this->resourceTemplatesByLabelsAndIds[$result['o:label']] = $result;
            }
        }
        return $this;
    }

    /**
     * Get item set ids by title or by numeric ids.
     *
     * Warning, titles are not unique.
     *
     * @return int[]
     */
    protected function getItemSetIds(array $titlesOrIds): array
    {
        if (is_null($this->itemSetsByTitlesAndIds)) {
            $this->prepareItemSets();
        }
        return array_column(array_intersect_key($this->itemSetsByTitlesAndIds, array_flip($titlesOrIds)), 'o:id');
    }

    /**
     * Get item set id by title or by numeric id.
     *
     * Warning, titles are not unique.
     */
    protected function getItemSetId($labelOrId): ?int
    {
        if (is_null($this->itemSetsByTitlesAndIds)) {
            $this->prepareItemSets();
        }
        return $this->itemSetsByTitlesAndIds[$labelOrId]['o:id'] ?? null;
    }

    /**
     * Get item set ids by titles or by numeric ids.
     *
     * Warning, titles are not unique.
     *
     * @return int[]
     */
    protected function getItemSets(array $titlesOrIds): array
    {
        if (is_null($this->itemSetsByTitlesAndIds)) {
            $this->prepareItemSets();
        }
        return array_values(array_intersect_key($this->itemSetsByTitlesAndIds, array_flip($titlesOrIds)));
    }

    /**
     * Get item set by title or by numeric id.
     *
     * Warning, titles are not unique.
     */
    protected function getItemSet($labelOrId): ?array
    {
        if (is_null($this->itemSetsByTitlesAndIds)) {
            $this->prepareItemSets();
        }
        return $this->itemSetsByTitlesAndIds[$labelOrId] ?? null;
    }

    /**
     * Prepare the list of item sets.
     *
     * Warning, titles are not unique.
     */
    protected function prepareItemSets(): self
    {
        if (is_null($this->itemSetsByTitlesAndIds)) {
            $connection = $this->entityManager->getConnection();
            // Here, the dbal query builder is used.
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    // Labels are not unique.
                    'DISTINCT resource.id AS "o:id"',
                    '"o:ItemSet" AS "@type"',
                    'resource.title AS "o:label"',
                    'NULL AS "@language"',
                    // Only the two first selects are needed, but some databases
                    // require "order by" or "group by" value to be in the select.
                    'resource.id'
                )
                ->from('resource', 'resource')
                ->innerJoin('resource', 'item_set', 'item_set', 'resource.id = item_set.id')
                // TODO Improve return of private item sets.
                ->where('resource.is_public', '1')
                ->orderBy('resource.id', 'asc')
                ->addGroupBy('resource.id')
            ;
            $results= $connection->executeQuery($qb)->fetchAllAssociative();
            $this->itemSetsByTitlesAndIds = [];
            foreach ($results as $result) {
                unset($result['id']);
                $result['o:id'] = (int) $result['o:id'];
                $this->itemSetsByTitlesAndIds[$result['o:id']] = $result;
                $this->itemSetsByTitlesAndIds[$result['o:label']] = $result;
            }
        }
        return $this;
    }

    /**
     * Get owner by user name or by numeric id.
     */
    protected function getOwner($nameOrId): ?array
    {
        if (is_null($this->ownersByNameAndIds)) {
            $this->prepareOwners();
        }
        return $this->ownersByNameAndIds[$nameOrId] ?? null;
    }

    /**
     * Prepare the list of owners (users with resources).
     */
    protected function prepareOwners(): self
    {
        if (is_null($this->ownersByNameAndIds)) {
            $connection = $this->entityManager->getConnection();
            // Here, the dbal query builder is used.
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    // Labels are not unique.
                    'DISTINCT user.name AS "o:label"',
                    '"o:User" AS "@type"',
                    'user.id AS "o:id"',
                    'NULL AS "@language"'
                )
                ->from('user', 'user')
                ->innerJoin('user', 'resource', 'resource', 'resource.user_id = user.id')
                // TODO Improve return of private resource for owners.
                ->where('resource.is_public', '1')
                ->orderBy('user.id', 'asc')
                ->addGroupBy('user.id')
            ;
            $results= $connection->executeQuery($qb)->fetchAllAssociative();
            $this->ownersByNameAndIds = [];
            foreach ($results as $result) {
                unset($result['id']);
                $result['o:id'] = (int) $result['o:id'];
                $this->ownersByNameAndIds[$result['o:id']] = $result;
                $this->ownersByNameAndIds[$result['o:label']] = $result;
            }
        }
        return $this;
    }

    /**
     * Get site by slug or by numeric id.
     */
    protected function getSite($slugOrId): ?array
    {
        if (is_null($this->sitesBySlugAndIds)) {
            $this->prepareSites();
        }
        return $this->sitesBySlugAndIds[$slugOrId] ?? null;
    }

    /**
     * Prepare the list of sites.
     */
    protected function prepareSites(): self
    {
        if (is_null($this->sitesBySlugAndIds)) {
            $connection = $this->entityManager->getConnection();
            // Here, the dbal query builder is used.
            $qb = $connection->createQueryBuilder();
            $qb
                ->select(
                    // Labels are not unique.
                    'DISTINCT site.slug AS "o:slug"',
                    'site.title AS "o:label"',
                    '"o:Site" AS "@type"',
                    'site.id AS "o:id"',
                    'NULL AS "@language"'
                )
                ->from('site', 'site')
                // TODO Improve return of private sites.
                ->where('site.is_public', '1')
                ->orderBy('site.id', 'asc')
                ->addGroupBy('site.id')
            ;
            $results= $connection->executeQuery($qb)->fetchAllAssociative();
            $this->sitesBySlugAndIds = [];
            foreach ($results as $result) {
                unset($result['id']);
                $result['o:id'] = (int) $result['o:id'];
                $this->sitesBySlugAndIds[$result['o:id']] = $result;
                $this->sitesBySlugAndIds[$result['o:label']] = $result;
            }
        }
        return $this;
    }

    /**
     * Copied from module AdvancedSearch to allow basic date range search.
     *
     * Convert into a standard DateTime. Manage some badly formatted values.
     *
     * Adapted from module NumericDataType.
     * The regex pattern allows partial month and day too.
     * @link https://mariadb.com/kb/en/datetime/
     * @see \NumericDataTypes\DataType\AbstractDateTimeDataType::getDateTimeFromValue()
     *
     * Allow mysql datetime too, not only iso 8601 (so with a space, not only a
     * "T" to separate date and time).
     *
     * Warning, year "0" does not exists, so output is null in that case.
     *
     * @param string $value
     * @param bool $defaultFirst
     * @return array|null
     */
    protected function getDateTimeFromValue($value, $defaultFirst = true): ?array
    {
        $yearMin = -292277022656;
        $yearMax = 292277026595;
        $patternIso8601 = '^(?<date>(?<year>-?\d{1,})(-(?<month>\d{1,2}))?(-(?<day>\d{1,2}))?)(?<time>((?:T| )(?<hour>\d{1,2}))?(:(?<minute>\d{1,2}))?(:(?<second>\d{1,2}))?)(?<offset>((?<offset_hour>[+-]\d{1,2})?(:(?<offset_minute>\d{1,2}))?)|Z?)$';
        static $dateTimes = [];

        $firstOrLast = $defaultFirst ? 'first' : 'last';
        if (isset($dateTimes[$value][$firstOrLast])) {
            return $dateTimes[$value][$firstOrLast];
        }

        $dateTimes[$value][$firstOrLast] = null;

        // Match against ISO 8601, allowing for reduced accuracy.
        $matches = [];
        if (!preg_match(sprintf('/%s/', $patternIso8601), $value, $matches)) {
            return null;
        }

        // Remove empty values.
        $matches = array_filter($matches, 'strlen');
        if (!isset($matches['date'])) {
            return null;
        }

        // An hour requires a day.
        if (isset($matches['hour']) && !isset($matches['day'])) {
            return null;
        }

        // An offset requires a time.
        if (isset($matches['offset']) && !isset($matches['time'])) {
            return null;
        }

        // Set the datetime components included in the passed value.
        $dateTime = [
            'value' => $value,
            'date_value' => $matches['date'],
            'time_value' => $matches['time'] ?? null,
            'offset_value' => $matches['offset'] ?? null,
            'year' => empty($matches['year']) ? null : (int) $matches['year'],
            'month' => isset($matches['month']) ? (int) $matches['month'] : null,
            'day' => isset($matches['day']) ? (int) $matches['day'] : null,
            'hour' => isset($matches['hour']) ? (int) $matches['hour'] : null,
            'minute' => isset($matches['minute']) ? (int) $matches['minute'] : null,
            'second' => isset($matches['second']) ? (int) $matches['second'] : null,
            'offset_hour' => isset($matches['offset_hour']) ? (int) $matches['offset_hour'] : null,
            'offset_minute' => isset($matches['offset_minute']) ? (int) $matches['offset_minute'] : null,
        ];

        // Set the normalized datetime components. Each component not included
        // in the passed value is given a default value.
        $dateTime['month_normalized'] = $dateTime['month'] ?? ($defaultFirst ? 1 : 12);
        // The last day takes special handling, as it depends on year/month.
        $dateTime['day_normalized'] = $dateTime['day']
        ?? ($defaultFirst ? 1 : self::getLastDay($dateTime['year'], $dateTime['month_normalized']));
        $dateTime['hour_normalized'] = $dateTime['hour'] ?? ($defaultFirst ? 0 : 23);
        $dateTime['minute_normalized'] = $dateTime['minute'] ?? ($defaultFirst ? 0 : 59);
        $dateTime['second_normalized'] = $dateTime['second'] ?? ($defaultFirst ? 0 : 59);
        $dateTime['offset_hour_normalized'] = $dateTime['offset_hour'] ?? 0;
        $dateTime['offset_minute_normalized'] = $dateTime['offset_minute'] ?? 0;
        // Set the UTC offset (+00:00) if no offset is provided.
        $dateTime['offset_normalized'] = isset($dateTime['offset_value'])
            ? ('Z' === $dateTime['offset_value'] ? '+00:00' : $dateTime['offset_value'])
            : '+00:00';

        // Validate ranges of the datetime component.
        if (($yearMin > $dateTime['year']) || ($yearMax < $dateTime['year'])) {
            return null;
        }
        if ((1 > $dateTime['month_normalized']) || (12 < $dateTime['month_normalized'])) {
            return null;
        }
        if ((1 > $dateTime['day_normalized']) || (31 < $dateTime['day_normalized'])) {
            return null;
        }
        if ((0 > $dateTime['hour_normalized']) || (23 < $dateTime['hour_normalized'])) {
            return null;
        }
        if ((0 > $dateTime['minute_normalized']) || (59 < $dateTime['minute_normalized'])) {
            return null;
        }
        if ((0 > $dateTime['second_normalized']) || (59 < $dateTime['second_normalized'])) {
            return null;
        }
        if ((-23 > $dateTime['offset_hour_normalized']) || (23 < $dateTime['offset_hour_normalized'])) {
            return null;
        }
        if ((0 > $dateTime['offset_minute_normalized']) || (59 < $dateTime['offset_minute_normalized'])) {
            return null;
        }

        // Adding the DateTime object here to reduce code duplication. To ensure
        // consistency, use Coordinated Universal Time (UTC) if no offset is
        // provided. This avoids automatic adjustments based on the server's
        // default timezone.
        // With strict type, "now" is required.
        $dateTime['date'] = new \DateTime('now', new \DateTimeZone($dateTime['offset_normalized']));
        $dateTime['date']
            ->setDate(
                $dateTime['year'],
                $dateTime['month_normalized'],
                $dateTime['day_normalized']
            )
            ->setTime(
                $dateTime['hour_normalized'],
                $dateTime['minute_normalized'],
                $dateTime['second_normalized']
            );

        // Cache the date/time as a sql date time.
        $dateTimes[$value][$firstOrLast] = $dateTime['date']->format('Y-m-d H:i:s');
        return $dateTimes[$value][$firstOrLast];
    }

    /**
     * Get the last day of a given year/month.
     *
     * @param int $year
     * @param int $month
     * @return int
     */
    protected function getLastDay($year, $month)
    {
        switch ($month) {
            case 2:
                // February (accounting for leap year)
                $leapYear = date('L', mktime(0, 0, 0, 1, 1, $year));
                return $leapYear ? 29 : 28;
            case 4:
            case 6:
            case 9:
            case 11:
                // April, June, September, November
                return 30;
            default:
                // January, March, May, July, August, October, December
                return 31;
        }
    }
}
