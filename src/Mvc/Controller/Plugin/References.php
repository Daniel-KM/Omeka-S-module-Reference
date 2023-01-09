<?php declare(strict_types=1);

namespace Reference\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\Query\Expr\Join;
use Doctrine\ORM\QueryBuilder;
use Laminas\EventManager\Event;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Adapter\AbstractResourceEntityAdapter;
use Omeka\Api\Adapter\Manager as AdapterManager;
use Omeka\Api\Request;
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
     * @param bool
     */
    protected $hasAdvancedSearch;

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
    protected $metadata = [];

    /**
     * @var array
     */
    protected $query = [];

    /**
     * @var array
     */
    protected $options = [];

    /**
     * The current process: "list", "count" or "initials".
     *
     * Only the process "initials" is set for now.
     *
     * @var string
     */
    protected $process;

    public function __construct(
        EntityManager $entityManager,
        AdapterManager $adapterManager,
        Acl $acl,
        ?User $user,
        Api $api,
        Translate $translate,
        $supportAnyValue,
        $hasAdvancedSearch
    ) {
        $this->entityManager = $entityManager;
        $this->adapterManager = $adapterManager;
        $this->acl = $acl;
        $this->user = $user;
        $this->api = $api;
        $this->translate = $translate;
        $this->supportAnyValue = $supportAnyValue;
        $this->hasAdvancedSearch = $hasAdvancedSearch;
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
     *     with a null, the string "null", or an empty string "" (deprecated).
     *     It is recommended to append it when a language is set. This option is
     *     used only for properties.
     *   - "datatypes": array Filter property values according to the data types.
     *     Default datatypes are "literal", "resource", "resource:item", "resource:itemset",
     *     "resource:media" and "uri"; other existing ones are managed.
     *     Warning: "resource" is not the same than specific resources.
     *     Use module Bulk Edit or Bulk Check to specify all resources automatically.
     *   - "begin": array Filter property values that begin with these strings,
     *     generally one or more initials.
     *   - "end": array Filter property values that end with these strings.
     * - values: array Allow to limit the answer to the specified values.
     * - first: false (default), or true (get first resource).
     * - list_by_max: 0 (default), or the max number of resources for each reference
     *   The max number should be below 1024 (mysql limit for group_concat).
     * - fields: the fields to use for the list of resources, if any. If not
     *   set, the output is an associative array with id as key and title as
     *   value. If set, value is an array of the specified fields.
     * - initial: false (default), or true (get first letter of each result), or
     *   integer (number of first characters to get for each "initial", useful
     *   for example to extract years from iso 8601 dates).
     * - distinct: false (default), or true (distinct values by type).
     * - datatype: false (default), or true (include datatype of values).
     * - lang: false (default), or true (include language of value to result).
     * - locale: empty (default) or a string or an ordered array Allow to get the
     *   returned values in the first specified language when a property has
     *   translated values. Use "null" to get a value without language.
     *   Unlike Omeka core, it get the translated title of linked resources.
     * TODO Use locale first or any locale (so all results preferably in the specified locale).
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
    public function __invoke(?array $metadata = [], ?array $query = [], ?array $options = [])
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
     */
    public function setMetadata(?array $metadata = [])
    {
        $this->metadata = $metadata ? array_filter($metadata) : [];

        // Check if one of the metadata fields is a short aggregated one.
        foreach ($this->metadata as $key => &$fieldElement) {
            if (!is_array($fieldElement) && strpos($fieldElement, ',') !== false) {
                $fieldElement = array_filter(array_map('trim', explode(',', $fieldElement)));
                if (!$fieldElement) {
                    unset($this->metadata[$key]);
                }
            }
        }
        unset($fieldElement);

        return $this;
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }

    public function setQuery(?array $query = []): self
    {
        // Remove useless keys.
        $filter = function ($v) {
            return is_string($v) ? (bool) strlen($v) : (bool) $v;
        };
        if ($query) {
            unset($query['sort_by']);
            unset($query['sort_order']);
            unset($query['per_page']);
            unset($query['page']);
            unset($query['offset']);
            unset($query['limit']);
            $this->query = array_filter($query, $filter);
        } else {
            $this->query = [];
        }
        return $this;
    }

    public function getQuery(): array
    {
        return $this->query;
    }

    public function setOptions(?array $options = []): self
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
            'locale' => [],
            'output' => 'list',
        ];
        if ($options) {
            $resourceName = in_array(@$options['resource_name'], ['items', 'item_sets', 'media', 'resources'])
                ? $options['resource_name']
                : $defaults['resource_name'];
            $first = !empty($options['first']);
            $listByMax = empty($options['list_by_max']) ? 0 : (int) $options['list_by_max'];
            $fields = empty($options['fields']) ? [] : $options['fields'];
            $initial = empty($options['initial']) ? false : (int) $options['initial'];
            $distinct = !empty($options['distinct']);
            $datatype = !empty($options['datatype']);
            $lang = !empty($options['lang']);
            // Currently, only one locale can be used, but it is managed as
            // array internally.
            if (empty($options['locale'])) {
                $locales = [];
            } else {
                $locales = is_array($options['locale']) ? $options['locale'] : [$options['locale']];
                $locales = array_filter(array_unique(array_map('trim', array_map('strval', $locales))), function ($v) {
                    return ctype_alnum(str_replace(['-', '_'], ['', ''], $v));
                });
                if (($pos = array_search('null', $locales)) !== false) {
                    $locales[$pos] = '';
                }
            }
            $this->options = [
                'resource_name' => $resourceName,
                'entity_class' => $this->mapResourceNameToEntityClass($resourceName),
                'per_page' => isset($options['per_page']) && is_numeric($options['per_page']) ? (int) $options['per_page'] : $defaults['per_page'],
                'page' => $options['page'] ?? $defaults['page'],
                'sort_by' => $options['sort_by'] ?? 'alphabetic',
                'sort_order' => isset($options['sort_order']) && strtolower((string) $options['sort_order']) === 'desc' ? 'DESC' : 'ASC',
                'filters' => isset($options['filters']) && is_array($options['filters']) ? $options['filters'] + $defaults['filters'] : $defaults['filters'],
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
                'locale' => $locales,
                'output' => @$options['output'] === 'associative' && !$first && !$listByMax && !$initial && !$distinct && !$datatype && !$lang
                    ? 'associative'
                    : 'list',
            ];

            // The check for length avoids to add a filter on values without any
            // language. It should be specified as "||" (or leading/trailing "|").
            // The value "null" can be used too and is recommended instead of an
            // empty string.
            if (!is_array($this->options['filters']['languages'])) {
                $this->options['filters']['languages'] = explode('|', str_replace(',', '|', $this->options['filters']['languages'] ?: ''));
            }
            $noEmptyLanguages = array_diff($this->options['filters']['languages'], ['null', null, '', 0, '0']);
            if (count($noEmptyLanguages) !== count($this->options['filters']['languages'])) {
                $this->options['filters']['languages'] = $noEmptyLanguages;
                $this->options['filters']['languages'][] = '';
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

    public function getOptions(): array
    {
        return $this->options;
    }

    public function list(): array
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
                            $resourceTemplate = $this->getResourceTemplate($valueData['val']);
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
     * If total is not correct, reindex the references in main settings.
     *
     * @return int[] The number of references for each type, according to query.
     */
    public function count(): array
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
                    $result[$keyResult] = 0;
                    break;
            }
        }

        return $result;
    }

    /**
     * Get the initials (first or more characters) of values for a field.
     *
     * The filters "begin" / "end" are skipped from the query.
     * @todo Some options are not yet managed: initials of item sets, sites.
     *
     * The option "initial" allows to set the number of characters by "initial"
     * (default 1).
     *
     * @return array Associative array with the list of initials for each field
     * and  the total of resources.
     */
    public function initials(): array
    {
        $fields = $this->getMetadata();
        if (empty($fields)) {
            return [];
        }

        // Use the same requests than list(), except select / group and begin.
        $this->process = 'initials';

        $currentOptions = $this->options;
        $this->options['first'] = false;
        $this->options['list_by_max'] = 0;
        $this->options['initial'] = false;
        // TODO Check options for initials.
        $this->options['distinct'] = false;
        $this->options['datatype'] = false;
        $this->options['lang'] = false;
        $this->options['fields'] = [];
        $this->options['output'] = 'associative';

        // Internal option to specify the number of characters of each "initial".
        $this->options['_initials'] = (int) $currentOptions['initial'] ?: 1;

        $result = [];
        foreach ($fields as $keyOrLabel => $inputField) {
            $dataFields = $this->prepareFields($inputField, $keyOrLabel);

            $keyResult = $dataFields['key_result'];

            $result[$keyResult] = $dataFields['output'];

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
                    $result[$keyResult]['o:references'] = $values;
                    break;

                case 'o:resource_class':
                    $values = $this->listResourceClasses();
                    $result[$keyResult]['o:references'] = $values;
                    break;

                case 'o:resource_template':
                    $values = $this->listResourceTemplates();
                    $result[$keyResult]['o:references'] = $values;
                    break;

                case 'o:item_set':
                    // Manage an exception for the resource "items".
                    if ($dataFields['type'] === 'o:item_set' && $this->options['resource_name'] !== 'items') {
                        $values = [];
                    } else {
                        $values = $this->listItemSets();
                    }
                    $result[$keyResult]['o:references'] = $values;
                    break;

                case 'o:owner':
                    $values = $this->listOwners();
                    $result[$keyResult]['o:references'] = $values;
                    break;

                case 'o:site':
                    $values = $this->listSites();
                    $result[$keyResult]['o:references'] = $values;
                    break;

                    // Unknown.
                default:
                    $result[$keyResult]['o:references'] = [];
                    break;
            }
        }

        // Normalize initials: a "É" (when first reference is "Éxxx") should be
        // converted into a "E".
        if (extension_loaded('intl')) {
            $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
            foreach ($result as &$resultData) {
                $initials = [];
                foreach ($resultData['o:references'] as $initial => $total) {
                    $initials[$transliterator->transliterate((string) $initial)] = $total;
                }
                $resultData['o:references'] = $initials;
            }
        } elseif (extension_loaded('iconv')) {
            foreach ($result as &$resultData) {
                $initials = [];
                foreach ($resultData['o:references'] as $initial => $total) {
                    $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $initial);
                    $initials[$trans === false ? $initial : $trans] = $total;
                }
                $resultData['o:references'] = $initials;
            }
        }

        $this->process = null;
        $this->options = $currentOptions;
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
            ->from(\Omeka\Entity\Value::class, 'value')
            // This join allow to check visibility automatically too.
            ->innerJoin($this->options['entity_class'], 'resource', Join::WITH, $expr->eq('value.resource', 'resource'))
            // The values should be distinct for each type.
            ->leftJoin($this->options['entity_class'], 'valueResource', Join::WITH, $expr->eq('value.valueResource', 'valueResource'))
            ->andWhere($expr->in('value.property', ':properties'))
            ->setParameter('properties', array_map('intval', $propertyIds), Connection::PARAM_INT_ARRAY)
            ->groupBy('val')
        ;

        if ($this->process === 'initials') {
            if ($this->options['locale']) {
                $qb
                    ->select(
                        // 'CONVERT(UPPER(LEFT(refmeta.text, 1)) USING latin1) AS val',
                        $val = $expr->upper($expr->substring('refmeta.text', 1, $this->options['_initials'])) . ' AS val',
                        // "Distinct" avoids to count duplicate values in properties in
                        // a resource: we count resources, not properties.
                        $expr->countDistinct('resource.id') . ' AS total'
                    )
                    ->innerJoin(
                        \Reference\Entity\Metadata::class,
                        'refmeta',
                        Join::WITH,
                        $expr->eq('refmeta.value', 'value.id')
                    )
                    ->andWhere($expr->in('refmeta.lang', ':locales'))
                    ->setParameter('locales', $this->options['locale'], Connection::PARAM_STR_ARRAY)
                ;
            } else {
                // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
                $qb
                    ->select(
                        // 'CONVERT(UPPER(LEFT(COALESCE(value.value, $linkedResourceTitle), $this->options['_initials'])) USING latin1) AS val',
                        $val = $this->supportAnyValue
                            ? 'ANY_VALUE(' . $expr->upper($expr->substring('COALESCE(value.value, valueResource.title, value.uri)', 1, $this->options['_initials'])) . ') AS val'
                            : $expr->upper($expr->substring('COALESCE(value.value, valueResource.title, value.uri)', 1, $this->options['_initials'])) . ' AS val',
                        // "Distinct" avoids to count duplicate values in properties in
                        // a resource: we count resources, not properties.
                        $expr->countDistinct('resource.id') . ' AS total'
                    )
                ;
            }
        } else {
            if ($this->options['locale']) {
                $qb
                    ->select(
                        $val = 'refmeta.text AS val',
                        // "Distinct" avoids to count duplicate values in properties in
                        // a resource: we count resources, not properties.
                        $expr->countDistinct('resource.id') . ' AS total'
                    )
                    ->innerJoin(
                        \Reference\Entity\Metadata::class,
                        'refmeta',
                        Join::WITH,
                        $expr->eq('refmeta.value', 'value.id')
                    )
                    ->andWhere($expr->in('refmeta.lang', ':locales'))
                    ->setParameter('locales', $this->options['locale'], Connection::PARAM_STR_ARRAY)
                ;
            } else {
                $qb
                    ->select(
                        $val = $this->supportAnyValue
                            ? 'ANY_VALUE(COALESCE(value.value, valueResource.title, value.uri)) AS val'
                            : 'COALESCE(value.value, valueResource.title, value.uri) AS val',
                        // "Distinct" avoids to count duplicate values in properties in
                        // a resource: we count resources, not properties.
                        $expr->countDistinct('resource.id') . ' AS total'
                    )
                ;
            }
        }

        return $this
            ->filterByDatatype($qb)
            ->filterByLanguage($qb)
            ->filterByBeginOrEnd($qb, substr($val, 0, -7))
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

        if ($this->process === 'initials') {
            $qb
                ->select(
                    'DISTINCT ' . $expr->upper($expr->substring('resource.title', 1, $this->options['_initials'])) . ' AS val',
                    $expr->count('resource.id') . ' AS total'
                );
        } else {
            $qb
                ->select(
                    'DISTINCT resource.title AS val',
                    $expr->count('resource.id') . ' AS total'
                );
        }
        $qb
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
            ->filterByBeginOrEnd($qb, 'resource.title')
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

        if ($this->process === 'initials') {
            $qb
                ->select(
                    'DISTINCT ' . $expr->upper($expr->substring('resource.title', 1, $this->options['_initials'])) . ' AS val',
                    $expr->count('resource.id') . ' AS total'
                );
        } else {
            $qb
                ->select(
                    'DISTINCT resource.title AS val',
                    $expr->count('resource.id') . ' AS total'
                );
        }
        $qb
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
            ->filterByBeginOrEnd($qb, 'resource.title')
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

        if ($this->process === 'initials') {
            $qb
                ->select(
                    'DISTINCT ' . $expr->upper($expr->substring('resource.title', 1, $this->options['_initials'])) . ' AS val',
                    $expr->count('resource.id') . ' AS total'
                );
        } else {
            $qb
                ->select(
                    'DISTINCT resource.title AS val',
                    $expr->count('resource.id') . ' AS total'
                );
        }
        $qb
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
            ->filterByBeginOrEnd($qb, 'resource.title')
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

        if ($this->process === 'initials') {
            $qb
                ->select(
                    $this->supportAnyValue
                        ? 'ANY_VALUE(' . $expr->upper($expr->substring('resource.title', 1, $this->options['_initials'])) . ') AS val'
                        : $expr->upper($expr->substring('resource.title', 1, $this->options['_initials'])) . ' AS val',
                    // "Distinct" avoids to count duplicate values in properties in
                    // a resource: we count resources, not properties.
                    $expr->countDistinct('resource.id') . ' AS total'
                );
        } else {
            $qb
                ->select(
                    $this->supportAnyValue
                        ? 'ANY_VALUE(resource.title) AS val'
                        : 'resource.title AS val',
                    // "Distinct" avoids to count duplicate values in properties in
                    // a resource: we count resources, not properties.
                    $expr->countDistinct('resource.id') . ' AS total'
                );
        }
        $qb
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

        // Initials don't have real meaning for a list of properties.
        if ($this->process === 'initials') {
            $qb
                ->select(
                    // 'property.label AS val',
                    // Important: use single quote for string ":", else doctrine fails.
                    $expr->upper($expr->substring("CONCAT(vocabulary.prefix, ':', property.localName)", 1, $this->options['_initials'])) . ' AS val',
                    // "Distinct" avoids to count resources with multiple
                    // values multiple times for the same property: we count
                    // resources, not properties.
                    $expr->countDistinct('value.resource') . ' AS total'
                );
        } else {
            $qb
                ->select(
                    // 'property.label AS val',
                    // Important: use single quote for string ":", else doctrine fails.
                    "CONCAT(vocabulary.prefix, ':', property.localName) AS val",
                    // "Distinct" avoids to count resources with multiple
                    // values multiple times for the same property: we count
                    // resources, not properties.
                    $expr->countDistinct('value.resource') . ' AS total'
                );
        }
        $qb
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

        // Initials don't have real meaning for a list of resource classes.
        if ($this->process === 'initials') {
            $qb
                ->select(
                    // 'resource_class.label AS val',
                    // Important: use single quote for string ":", else doctrine fails.
                    $expr->upper($expr->substring("CONCAT(vocabulary.prefix, ':', resource_class.localName)", 1, $this->options['_initials'])) . ' AS val',
                    'COUNT(resource.id) AS total'
                );
        } else {
            $qb
                ->select(
                    // 'resource_class.label AS val',
                    // Important: use single quote for string ":", else doctrine fails.
                    "CONCAT(vocabulary.prefix, ':', resource_class.localName) AS val",
                    'COUNT(resource.id) AS total'
                );
        }
        $qb
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

        if ($this->process === 'initials') {
            $qb
                ->select(
                    $expr->upper($expr->substring('resource_template.label', 1, $this->options['_initials'])) . ' AS val',
                    'COUNT(resource.id) AS total'
                );
        } else {
            $qb
                ->select(
                    'resource_template.label AS val',
                    'COUNT(resource.id) AS total'
                );
        }
        $qb
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

        if ($this->process === 'initials') {
            $qb
                ->select(
                    // FIXME List of initials of item sets.
                    $expr->upper($expr->substring('item_set.id', 1, $this->options['_initials'])) . ' AS val',
                    'COUNT(resource.id) AS total'
                );
        } else {
            $qb
                ->select(
                    'item_set.id AS val',
                    'COUNT(resource.id) AS total'
                );
        }
        $qb
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

        if ($this->process === 'initials') {
            $qb
                ->select(
                    $expr->upper($expr->substring('user.name', 1, $this->options['_initials'])) . ' AS val',
                    'COUNT(resource.id) AS total'
                );
        } else {
            $qb
                ->select(
                    'user.name AS val',
                    'COUNT(resource.id) AS total'
                );
        }
        $qb
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

        if ($this->process === 'initials') {
            $qb
                ->select(
                    // FIXME List of initials of sites.
                    $expr->upper($expr->substring('site.slug', 1, $this->options['_initials'])) . ' AS val',
                    'COUNT(resource.id) AS total'
                );
        } else {
            $qb
                ->select(
                    'site.slug AS val',
                    'COUNT(resource.id) AS total'
                );
        }
        $qb
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
     * The special key "0-9" allows to get any non-alphabetic characters.
     * @todo The special "0-9" cannot be mixed currently.
     *
     *  @param string The column to filter, for example "value.value" (default),
     *  "val", or "resource.title".
     */
    protected function filterByBeginOrEnd(QueryBuilder $qb, $column = 'value.value'): self
    {
        if ($this->process === 'initials') {
            return $this;
        }

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
                    $firstFilter = reset($this->options['filters'][$filter]);
                    if ($firstFilter === '0-9') {
                        $qb
                            ->andWhere("REGEXP($column, :filter_09) = false")
                            ->setParameter('filter_09', $filter === 'begin' ? '^[[:alpha:]]' :  '[[:alpha:]]$');
                    } else {
                        $qb
                            ->andWhere($expr->like($column, ":filter_$filter"))
                            ->setParameter(
                                "filter_$filter",
                                $filterB . str_replace(['%', '_'], ['\%', '\_'], $firstFilter) . $filterE
                            );
                    }
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
            // "initial" is a reserved word from the version 8.0.27 of Mysql,
            // but doctrine renames all aliases before and after querying.
            $qb
                ->addSelect(
                    // 'CONVERT(UPPER(LEFT(value.value, 1)) USING latin1) AS initial',
                    $this->supportAnyValue
                        ? 'ANY_VALUE(' . $expr->upper($expr->substring('resource.title', 1, $this->options['initial'])) . ') AS initial'
                        : $expr->upper($expr->substring('resource.title', 1, $this->options['initial'])) . ' AS initial'
                );
        }

        if ($type === 'properties' && $this->options['initial']) {
            // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
            $qb
                ->addSelect(
                    // 'CONVERT(UPPER(LEFT(COALESCE(value.value, $linkedResourceTitle), 1)) USING latin1) AS initial',
                    $this->supportAnyValue
                        ? 'ANY_VALUE(' . $expr->upper($expr->substring('COALESCE(value.value, valueResource.title, value.uri)', 1, $this->options['initial'])) . ') AS initial'
                        : $expr->upper($expr->substring('COALESCE(value.value, valueResource.title, value.uri)', 1, $this->options['initial'])) . ' AS initial'
                );
        }

        if ($type === 'properties' && $this->options['distinct']) {
            $qb
                ->addSelect(
                    // TODO Warning with type "resource", that may be the same than "resource:item", etc.
                    'valueResource.id AS res',
                    'value.uri AS uri'
                )
                ->addGroupBy('res')
                ->addGroupBy('uri');
        }

        if ($type === 'properties' && $this->options['datatype']) {
            $qb
                ->addSelect(
                    $this->supportAnyValue
                        ? 'ANY_VALUE(value.type) AS type'
                        : 'value.type AS type'
                );
            // No need to group by type: it is already managed with group by distinct "val,res,uri".
        }

        if ($type === 'properties' && $this->options['lang']) {
            $qb
                ->addSelect(
                    $this->supportAnyValue
                        ? 'ANY_VALUE(value.lang) AS lang'
                        : 'value.lang AS lang'
                );
            if ($this->options['distinct']) {
                $qb
                    ->addGroupBy('lang');
            }
        }

        // Add the first resource id.
        if ($this->options['first']) {
            $qb
                ->addSelect(
                    'MIN(resource.id) AS first'
                );
        }

        if ($this->options['list_by_max']
            // TODO May be simplified for "resource_titles".
            && ($type === 'properties' || $type === 'resource_titles')
        ) {
            // Add and order by title, because it is the most common and simple.
            // Use a single select to avoid issue with null, that should not
            // exist in Omeka values. The unit separator is used in order to
            // check results simpler.
            // Mysql max length: 1024.

            // Get the title by locale. Because title depends on template and
            // the resource stores only the first title, whatever the language,
            // the table reference metadata is used.
            // In order to manage ordered multiple languages, one join is added
            // to get the first title available. Furthermore, here, all linked
            // resources should be returned, even when the resource has no title
            // in the specified language. So a join is added without language,
            // that could be a join with the table of the resources.
            // Most of the times, there are only one language anyway, and
            // rarely more than one fallback anyway.
            if ($this->options['locale'] && $type !== 'resource_titles') {
                $coalesce = [];
                foreach ($this->options['locale'] as $locale) {
                    $strLocale = str_replace('-', '_', $locale);
                    $coalesce[] = "ress_$strLocale.text";
                    $qb
                        ->leftJoin(
                            \Reference\Entity\Metadata::class,
                            // The join is different than in listDataForProperties().
                            "ress_$strLocale",
                            Join::WITH,
                            $expr->andX(
                                $expr->eq('value.resource', "ress_$strLocale.resource"),
                                $expr->eq("ress_$strLocale.field", ':display_title'),
                                $expr->eq("ress_$strLocale.lang", ':locale_' . $strLocale)
                            )
                        )
                        ->setParameter('locale_' . $strLocale, $locale)
                    ;
                }
                $coalesce[] = 'ress.text';
                $ressText = $this->supportAnyValue
                    ? 'ANY_VALUE(COALESCE(' . implode(', ', $coalesce) . '))'
                    : 'COALESCE(' . implode(', ', $coalesce) . ')';
                $qb
                    ->leftJoin(
                        \Reference\Entity\Metadata::class,
                        // The join is different than in listDataForProperties().
                        'ress',
                        Join::WITH,
                        $expr->andX(
                            $expr->eq('value.resource', 'ress.resource'),
                            $expr->eq('ress.field', ':display_title')
                        )
                    )
                    ->setParameter('display_title', 'display_title')
                    ->addSelect(
                        // Note: for doctrine, separators must be set as parameters.
                        "GROUP_CONCAT(IDENTITY(ress.resource), :unit_separator, $ressText SEPARATOR :group_separator) AS resources"
                    )
                    ->setParameter('unit_separator', chr(0x1F))
                    ->setParameter('group_separator', chr(0x1D))
                ;
            } else {
                $qb
                    ->leftJoin(
                        \Omeka\Entity\Resource::class,
                        'ress',
                        Join::WITH,
                        $expr->eq($type === 'resource_titles' ? 'resource' : 'value.resource', 'ress')
                    )
                    ->addSelect(
                        // Note: for doctrine, separators must be set as parameters.
                        'GROUP_CONCAT(ress.id, :unit_separator, ress.title SEPARATOR :group_separator) AS resources'
                    )
                    ->setParameter('unit_separator', chr(0x1F))
                    ->setParameter('group_separator', chr(0x1D))
                ;
            }
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
                    $values = $this->getPropertyIds($this->options['values']) ?: [0];
                    $qb
                        ->andWhere('property' . '.id IN (:ids)')
                        ->setParameter('ids', $values);
                    break;
                case 'o:resource_class':
                    $values = $this->getResourceClassIds($this->options['values']) ?: [0];
                    $qb
                        ->andWhere('resource_class' . '.id IN (:ids)')
                        ->setParameter('ids', $values);
                    break;
                case 'o:resource_template':
                    $values = $this->getResourceTemplateIds($this->options['values']) ?: [0];
                    $qb
                        ->andWhere('resource_template' . '.id IN (:ids)')
                        ->setParameter('ids', $values);
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
        if ($this->options['initial']) {
            if (extension_loaded('intl')) {
                $transliterator = \Transliterator::createFromRules(':: NFD; :: [:Nonspacing Mark:] Remove; :: NFC;');
                $result = array_map(function ($v) use ($transliterator) {
                    $v['total'] = (int) $v['total'];
                    $v['initial'] = $transliterator->transliterate((string) $v['initial']);
                    return $v;
                }, $result);
            } elseif (extension_loaded('iconv')) {
                $result = array_map(function ($v) {
                    $v['total'] = (int) $v['total'];
                    $trans = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', (string) $v['initial']);
                    $v['initial'] = $trans === false ? (string) $v['initial'] : $trans;
                    return $v;
                }, $result);
            } else {
                // Convert null into empty string.
                $result = array_map(function ($v) {
                    $v['total'] = (int) $v['total'];
                    $v['initial'] = (string) $v['initial'];
                    return $v;
                }, $result);
            }
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
            $listByMax = $this->options['list_by_max'];
            $explodeResources = function (array $result) use ($listByMax) {
                return array_map(function ($v) use ($listByMax) {
                    $list = explode(chr(0x1D), (string) $v['resources']);
                    $list = array_map(function ($vv) {
                        return explode(chr(0x1F), $vv, 2);
                    }, $listByMax ? array_slice($list, 0, $listByMax) : $list);
                    $v['resources'] = array_column($list, 1, 0);
                    return $v;
                }, $result);
            };
            $result = $explodeResources($result);

            if ($this->options['fields']) {
                $fields = array_fill_keys($this->options['fields'], true);
                // FIXME Api call inside a loop. Use the new table reference_metadata.
                $result = array_map(function ($v) use ($fields) {
                    // Check required when a locale is used or for debug.
                    if (empty($v['resources'])) {
                        return $v;
                    }
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

    /**
     * Count the total of distinct values for a list of properties.
     *
     * @return int The number of references for each type, according to query.
     */
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
                $expr->countDistinct('refmeta.text')
            )
            ->from(\Reference\Entity\Metadata::class, 'refmeta')
            // This join allow to check visibility automatically too.
            ->innerJoin(\Omeka\Entity\Resource::class, 'resource', Join::WITH, $expr->eq('refmeta.resource', 'resource'))
            ->andWhere($expr->in('refmeta.field', ':properties'))
            ->setParameter('properties', $this->getPropertyTerms($propertyIds), Connection::PARAM_STR_ARRAY)
        ;

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->innerJoin($this->options['entity_class'], 'res', Join::WITH, 'res.id = resource.id');
        }

        $this->searchQuery($qb);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    /**
     * Count the total of distinct values for a list of resource classes.
     *
     * @return int The number of references for each type, according to query.
     */
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

    /**
     * Count the total of distinct values for a list of resource templates.
     *
     * @return int The number of references for each type, according to query.
     */
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

    /**
     * Count the total of distinct values for a list of item sets.
     *
     * @return int The number of references for each type, according to query.
     */
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
     * Limit the results with a query.
     *
     * The query is generally the site query, but may be complex with advanced
     * search.
     */
    protected function searchQuery(QueryBuilder $qb, ?string $type = null): self
    {
        if (empty($this->query)) {
            return $this;
        }

        if (empty($this->options['entity_class']) || $this->options['entity_class'] === \Omeka\Entity\Resource::class) {
            return $this;
        }

        $resourceName = $this->mapEntityClassToResourceName($this->options['entity_class']);
        if (empty($resourceName)) {
            return $this;
        }

        $expr = $qb->expr();

        $mainQuery = $this->query;

        // When searching by item set or site, remove the matching query filter.
        if ($type === 'o:item_set') {
            unset($mainQuery['item_set_id']);
        }
        if ($type === 'o:site') {
            unset($mainQuery['site_id']);
        }

        /**
         * @var \Omeka\Api\Adapter\AbstractResourceEntityAdapter $adapter
         * @see \Omeka\Api\Adapter\AbstractResourceEntityAdapter::search()
         */
        $adapter = $this->adapterManager->get($this->options['resource_name']);
        $subQb = $this->entityManager->createQueryBuilder()
            ->select('omeka_root.id')
            ->from($this->options['entity_class'], 'omeka_root');
        $adapter->buildBaseQuery($subQb, $mainQuery);
        // Manage advanced resquest of module Advanced Search for properties.
        // TODO Remove this fix when Advanced Search will bypass AbstractResourceEntityAdapter directly.
        if ($this->hasAdvancedSearch && !empty($mainQuery['property'])) {
            $props = $mainQuery['property'];
            unset($mainQuery['property']);
        } else {
            $props = null;
        }
        $adapter->buildQuery($subQb, $mainQuery);

        // Full text search is not managed by adapters, but by a special event.
        if (isset($this->query['fulltext_search'])) {
            $this->buildFullTextSearchQuery($subQb, $adapter);
        }
        $subQb->groupBy('omeka_root.id');

        if ($props) {
            $mainQuery['property'] = $props;
        }

        $request = new Request('search', $resourceName);
        $request->setContent($mainQuery);
        $event = new Event('api.search.query', $adapter, [
            'queryBuilder' => $subQb,
            'request' => $request,
        ]);
        $adapter->getEventManager()->triggerEvent($event);

        $subQb->select('omeka_root.id');

        // TODO Manage not only standard visibility, but modules ones.
        // TODO Check the visibility for the main queries.
        // Set visibility constraints for users without "view-all" privilege.
        if (!$this->acl->userIsAllowed(\Omeka\Entity\Resource::class, 'view-all')) {
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
            $qb
                ->setParameter(
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
     * @see \Folksonomy\View\Helper\TagCloud
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
     */
    protected function mapResourceNameToEntityClass($resourceName): string
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
     * Get the api resource name from the entity class.
     */
    protected function mapEntityClassToResourceName($entityClass): ?string
    {
        $entityResourceMap = [
            \Omeka\Entity\Resource::class => 'resources',
            \Omeka\Entity\Item::class => 'items',
            \Omeka\Entity\ItemSet::class => 'item_sets',
            \Omeka\Entity\Media::class => 'media',
        ];
        return $entityResourceMap[$entityClass] ?? null;
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
     * Get property terms by JSON-LD terms or by numeric ids.
     *
     * @return string[]
     */
    protected function getPropertyTerms(array $termsOrIds): array
    {
        if (is_null($this->propertiesByTermsAndIds)) {
            $this->prepareProperties();
        }
        return array_column(array_intersect_key($this->propertiesByTermsAndIds, array_flip($termsOrIds)), 'o:term');
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
            $results = $connection->executeQuery($qb)->fetchAllAssociative();
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
     * Get resource class terms by JSON-LD terms or by numeric ids.
     *
     * @return string[]
     */
    protected function getResourceClassTerms(array $termsOrIds): array
    {
        if (is_null($this->resourceClassesByTermsAndIds)) {
            $this->prepareResourceClasses();
        }
        return array_column(array_intersect_key($this->resourceClassesByTermsAndIds, array_flip($termsOrIds)), 'o:term');
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
            $results = $connection->executeQuery($qb)->fetchAllAssociative();
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
     * Get resource template labels by labels or by numeric ids.
     *
     * @return string[]
     */
    protected function getResourceTemplateLabels(array $labelsOrIds): array
    {
        if (is_null($this->resourceTemplatesByLabelsAndIds)) {
            $this->prepareResourceTemplates();
        }
        return array_column(array_intersect_key($this->resourceTemplatesByLabelsAndIds, array_flip($labelsOrIds)), 'o:label');
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
            $results = $connection->executeQuery($qb)->fetchAllAssociative();
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
     * Get item set titles by title or by numeric ids.
     *
     * Warning, titles are not unique.
     *
     * @return string[]
     */
    protected function getItemSetTitles(array $titlesOrIds): array
    {
        if (is_null($this->itemSetsByTitlesAndIds)) {
            $this->prepareItemSets();
        }
        return array_column(array_intersect_key($this->itemSetsByTitlesAndIds, array_flip($titlesOrIds)), 'o:label');
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
            $results = $connection->executeQuery($qb)->fetchAllAssociative();
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
            $results = $connection->executeQuery($qb)->fetchAllAssociative();
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
            $results = $connection->executeQuery($qb)->fetchAllAssociative();
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
