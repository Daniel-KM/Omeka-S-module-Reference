<?php declare(strict_types=1);

namespace Reference\Mvc\Controller\Plugin;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Adapter\Manager as AdapterManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Entity\User;
use Omeka\Mvc\Controller\Plugin\Translate;
use Omeka\Permissions\Acl;

class References extends AbstractPlugin
{
    /**
     * @param EntityManager
     */
    protected $entityManager;

    /**
     * @param Connection
     */
    protected $connection;

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
     * @var ApiManager
     */
    protected $api;

    /**
     * @var Translate
     */
    protected $translate;

    /**
     * @param bool
     */
    protected $hasAccess;

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
        Connection $connection,
        AdapterManager $adapterManager,
        Acl $acl,
        ?User $user,
        ApiManager $api,
        Translate $translate,
        bool $hasAccess,
        $supportAnyValue
    ) {
        $this->entityManager = $entityManager;
        $this->connection = $connection;
        $this->adapterManager = $adapterManager;
        $this->acl = $acl;
        $this->user = $user;
        $this->api = $api;
        $this->translate = $translate;
        $this->hasAccess = $hasAccess;
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
     *     with a null, the string "null", or an empty string "" (deprecated).
     *     It is recommended to append it when a language is set. This option is
     *     used only for properties.
     *   - "main_types": array with "literal", "resource", or "uri".
     *     Filter property values according to the main data type. Default is to
     *     search all data types.
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
            'resource_table' => 'item',
            'is_base_resource' => false,
            // Options sql.
            'per_page' => 0,
            'page' => 1,
            'sort_by' => 'alphabetic',
            'sort_order' => 'ASC',
            'filters' => [
                'languages' => [],
                'main_types' => [],
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
            $resourceName = isset($options['resource_name'])
                && in_array($options['resource_name'], ['items', 'item_sets', 'media', 'resources'])
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
            $entityClass = $this->mapResourceNameToEntityClass($resourceName);
            $this->options = [
                'resource_name' => $resourceName,
                'entity_class' => $entityClass,
                'resource_table' => $this->mapResourceNameToTable($resourceName),
                'is_base_resource' => empty($entityClass) || $entityClass === \Omeka\Entity\Resource::class,
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
                'output' => isset($options['output']) && $options['output'] === 'associative' && !$first && !$listByMax && !$initial && !$distinct && !$datatype && !$lang
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

            // May be an array or a string (literal, uri or resource, in this order).
            if (!is_array($this->options['filters']['main_types'])) {
                $this->options['filters']['main_types'] = explode('|', str_replace(',', '|', $this->options['filters']['main_types'] ?: ''));
            }
            $this->options['filters']['main_types'] = array_unique(array_filter(array_map('trim', $this->options['filters']['main_types'])));
            $this->options['filters']['main_types'] = array_values(array_intersect(['value', 'resource', 'uri'], $this->options['filters']['main_types']));
            $this->options['filters']['main_types'] = array_combine($this->options['filters']['main_types'], $this->options['filters']['main_types']);
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

        /**
         * Note: The process doesn't use the orm query builder anymore but the
         * dbal query builder in order to largely speed process: 4 to 10 times
         * on a small base, but this is exponential, so more than 100 or 1000 on
         * big bases, in particular with the old version of the module Annotate
         * that created four sub-resources to manage annotations, parts, bodies
         * and targets.
         *
         * The issue with "resource" is that many classes are joined to it, even
         * when it is useless. Here, this is always useless, because only the id
         * and title are needed. So each time a resource is queried, multiple
         * left joins are appended (item, item set, media, value annotation,
         * annotation value, etc.).
         *
         * @see https://github.com/doctrine/orm/issues/5961
         * @see https://github.com/doctrine/orm/issues/5980
         * @see https://github.com/doctrine/orm/pull/8704
         *
         * Nevertheless, this solution requires to check visibility manually for
         * resource and value, but user and sites too.
         *
         * @todo Remove coalesce: use reference_metadata.
         */

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

                // Module Access.
                case 'access':
                    if ($this->hasAccess) {
                        $values = $this->listAccesses();
                        if ($isAssociative) {
                            $result[$keyResult]['o:references'] = $values;
                        } else {
                            foreach (array_filter($values) as $valueData) {
                                $meta = [
                                    '@type' => 'o:AccessStatus',
                                    'o-status:level' => $valueData['val'],
                                ];
                                $result[$keyResult]['o:references'][] = $meta + $valueData;
                            }
                        }
                    } else {
                        $result[$keyResult]['o:references'] = [];
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
     * Count the total of distinct values for a property, a class, a template or
     * an item set.
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

                // Module Access.
                case 'access':
                    $values = $this->listAccesses();
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

        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        // TODO This is no more the case.
        // TODO Check if ANY_VALUE can be replaced by MIN in order to remove it.
        // Note: Doctrine ORM requires simple label, without quote or double quote:
        // "o:label" is not possible, neither "count". Use of Doctrine DBAL now.

        // Dbal expr() and orm expr() don't have the same methods, for example
        // count(), substring(), upper(), etc. It has only the common comparison
        // operators.

        $qb
            // "Distinct" avoids to count duplicate values in properties in a
            // resource: we count resources, not properties.
            ->distinct(true)
            ->from('value', 'value')
            ->where($expr->in('value.property_id', ':properties'))
            ->setParameter('properties', array_map('intval', $propertyIds), Connection::PARAM_INT_ARRAY)
            ->groupBy('val')
        ;

        // The values should be distinct for each type.
        if ($this->options['is_base_resource']) {
            $qb
                ->innerJoin('value', 'resource', 'resource', $expr->eq('resource.id', 'value.resource_id'))
                ->leftJoin('value', 'resource', 'value_resource', $expr->eq('value_resource.id', 'value.value_resource_id'));
        } else {
            $qb
                ->innerJoin('value', 'resource', 'resource', $expr->andX($expr->eq('resource.id', 'value.resource_id'), $expr->eq('resource.resource_type', ':entity_class')))
                ->leftJoin('value', 'resource', 'value_resource', $expr->andX($expr->eq('value_resource.id', 'value.value_resource_id'), $expr->eq('value_resource.resource_type', ':entity_class')))
                ->setParameter('entity_class', $this->options['entity_class'], ParameterType::STRING);
        }

        // This filter is used by properties only and normally already included
        // in the select, but it allows to simplify it.
        $mainTypes = [
            'value' => 'value.value',
            // Output the linked resource title, not the linked resource id.
            'resource' => 'value_resource.title',
            // 'resource' => 'value.value_resource_id',
            'uri' => 'value.uri',
        ];
        if ($this->options['filters']['main_types']) {
            $mainTypes = array_intersect_key($mainTypes, $this->options['filters']['main_types']);
        }
        $mainTypesString = count($mainTypes) === 1
            ? reset($mainTypes)
            : 'COALESCE(' . implode(', ', $mainTypes) . ')';

        if ($this->process === 'initials') {
            if ($this->options['locale']) {
                $qb
                    ->select(
                        // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert. Anyway convert should be only for diacritics.
                        // 'CONVERT(UPPER(LEFT(refmeta.text, 1)) USING latin1) AS val',
                        $val = "UPPER(LEFT(refmeta.text, {$this->options['_initials']})) AS val",
                        'COUNT(esource.id) AS total'
                    )
                    ->innerJoin('value', 'reference_metadata', 'refmeta', $expr->eq('refmeta.value_id', 'value.id'))
                    ->andWhere($expr->in('refmeta.lang', ':locales'))
                    ->setParameter('locales', $this->options['locale'], Connection::PARAM_STR_ARRAY)
                ;
            } else {
                // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
                $qb
                    ->select(
                        // 'CONVERT(UPPER(LEFT($mainTypesString, $this->options['_initials'])) USING latin1) AS val',
                        $val = $this->supportAnyValue
                            ? "ANY_VALUE(UPPER(LEFT($mainTypesString, {$this->options['_initials']}))) AS val"
                            : "UPPER(LEFT($mainTypesString, {$this->options['_initials']})) AS val",
                        'COUNT(resource.id) AS total'
                    )
                ;
            }
        } else {
            if ($this->options['locale']) {
                $qb
                    ->select(
                        $val = 'refmeta.text AS val',
                        'COUNT(resource.id) AS total'
                    )
                    ->innerJoin('value', 'reference_metadata', 'refmeta', $expr->eq('refmeta.value_id', 'value.id'))
                    ->andWhere($expr->in('refmeta.lang', ':locales'))
                    ->setParameter('locales', $this->options['locale'], Connection::PARAM_STR_ARRAY)
                ;
            } else {
                $qb
                    ->select(
                        $val = $this->supportAnyValue
                            ? "ANY_VALUE($mainTypesString) AS val"
                            : "$mainTypesString AS val",
                        'COUNT(resource.id) AS total'
                    )
                ;
            }
        }

        return $this
            ->filterByVisibility($qb, 'properties')
            ->filterByMainType($qb)
            ->filterByDataType($qb)
            ->filterByLanguage($qb)
            ->filterByBeginOrEnd($qb, substr($val, 0, -7))
            ->manageOptions($qb, 'properties', ['mainTypesString' => $mainTypesString])
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

        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        if ($this->process === 'initials') {
            $qb
                ->select(
                    "UPPER(LEFT(resource.title, {$this->options['_initials']})) AS val",
                    'COUNT(resource.id) AS total'
                );
        } else {
            $qb
                ->select(
                    'resource.title AS val',
                    'COUNT(resource.id) AS total'
                );
        }
        $qb
            ->distinct(true)
            ->from('resource', 'resource')
            ->where($expr->in('resource.resource_class_id', ':resource_classes'))
            ->setParameter('resource_classes', array_map('intval', $resourceClassIds), Connection::PARAM_INT_ARRAY)
            ->groupBy('val');

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':entity_class'))
                ->setParameter('entity_class', $this->options['entity_class'], ParameterType::STRING);
        }

        return $this
            ->filterByVisibility($qb, 'resource_classes')
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

        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        if ($this->process === 'initials') {
            $qb
                ->select(
                    "UPPER(LEFT(resource.title, {$this->options['_initials']})) AS val",
                    'COUNT(resource.id) AS total'
                );
        } else {
            $qb
                ->select(
                    'resource.title AS val',
                    'COUNT(resource.id) AS total'
                );
        }
        $qb
            ->distinct(true)
            ->from('resource', 'resource')
            ->where($expr->in('resource.resource_template_id', ':resource_templates'))
            ->setParameter('resource_templates', array_map('intval', $resourceTemplateIds), Connection::PARAM_INT_ARRAY)
            ->groupBy('val');

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':entity_class'))
                ->setParameter('entity_class', $this->options['entity_class'], ParameterType::STRING);
        }

        return $this
            ->filterByVisibility($qb, 'resource_templates')
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

        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        if ($this->options['entity_class'] !== \Omeka\Entity\Item::class) {
            return [];
        }

        if ($this->process === 'initials') {
            $qb
                ->select(
                    "UPPER(LEFT(resource.title, {$this->options['_initials']})) AS val",
                    'COUNT(resource.id) AS total'
                );
        } else {
            $qb
                ->select(
                    'resource.title AS val',
                    'COUNT(resource.id) AS total'
                );
        }
        $qb
            ->distinct(true)
            ->from('resource', 'resource')
            // Always an item.
            ->innerJoin('resource', 'item', 'res', 'res.id = resource.id')
            ->innerJoin('res', 'item_item_set', 'item_set', $expr->in('item_set.item_set_id', ':item_sets'))
            ->setParameter('item_sets', array_map('intval', $itemSetIds), Connection::PARAM_INT_ARRAY)
            ->groupBy('val')
        ;

        return $this
            ->filterByVisibility($qb, 'item_sets')
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
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        // Note: Doctrine ORM requires simple label, without quote or double quote:
        // "o:label" is not possible, neither "count". Use of Doctrine DBAL now.

        if ($this->process === 'initials') {
            $qb
                ->select(
                    $this->supportAnyValue
                        ? "ANY_VALUE(UPPER(LEFT(resource.title, {$this->options['_initials']}))) AS val"
                        : "UPPER(LEFT(resource.title, {$this->options['_initials']})) AS val",
                    'COUNT(resource.id) AS total'
                );
        } else {
            $qb
                ->select(
                    $this->supportAnyValue
                        ? 'ANY_VALUE(resource.title) AS val'
                        : 'resource.title AS val',
                    'COUNT(resource.id) AS total'
                );
        }
        $qb
            // "Distinct" avoids to count duplicate values in properties in a
            // resource: we count resources, not properties.
            ->distinct(true)
            ->from('resource', 'resource')
            ->where($expr->eq('resource.resource_type', ':entity_class'))
            ->setParameter('entity_class', $this->options['entity_class'], ParameterType::STRING)
            ->groupBy('val')
        ;

        return $this
            // TODO Improve filter for "o:title".
            // ->filterByMainType($qb)
            // ->filterByDataType($qb)
            // ->filterByLanguage($qb)
            ->filterByVisibility($qb, 'resource_titles')
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
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        // Initials don't have real meaning for a list of properties.
        if ($this->process === 'initials') {
            $qb
                ->select(
                    // 'property.label AS val',
                    // Important: use single quote for string ":", else doctrine fails in ORM.
                    "UPPER(LEFT(CONCAT(vocabulary.prefix, ':', property.local_name), {$this->options['_initials']})) AS val",
                    'COUNT(value.resource_id) AS total'
                );
        } else {
            $qb
                ->select(
                    // 'property.label AS val',
                    // Important: use single quote for string ":", else doctrine fails in ORM.
                    "CONCAT(vocabulary.prefix, ':', property.local_name) AS val",
                    'COUNT(value.resource_id) AS total'
                );
        }
        $qb
            // "Distinct" avoids to count resources with multiple values
            // multiple times for the same property: we count resources, not
            // properties.
            ->distinct(true)
            ->from('resource', 'resource')
            ->innerJoin('resource', 'value', 'value', $expr->eq('value.resource_id', 'resource.id'))
            // The left join allows to get the total of items without property.
            ->leftJoin('value', 'property', 'property', $expr->eq('property.id', 'value.property_id'))
            ->innerJoin('property', 'vocabulary', 'vocabulary', $expr->eq('vocabulary.id', 'property.vocabulary_id'))
            ->groupBy('val')
        ;
        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':entity_class'))
                ->setParameter('entity_class', $this->options['entity_class'], ParameterType::STRING);
        }

        return $this
            ->filterByVisibility($qb, 'o:property')
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
        $qb = $this->connection->createQueryBuilder();
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
                    // Important: use single quote for string ":", else doctrine orm fails.
                    "UPPER(LEFT(CONCAT(vocabulary.prefix, ':', property.local_name), {$this->options['_initials']})) AS val",
                    'COUNT(resource.id) AS total'
                );
        } else {
            $qb
                ->select(
                    // 'resource_class.label AS val',
                    // Important: use single quote for string ":", else doctrine orm fails.
                    "CONCAT(vocabulary.prefix, ':', resource_class.local_name) AS val",
                    'COUNT(resource.id) AS total'
                );
        }
        $qb
            ->distinct(true)
            ->from('resource', 'resource')
            // The left join allows to get the total of items without resource
            // class.
            ->leftJoin('resource', 'resource_class', 'resource_class', $expr->eq('resource_class.id', 'resource.resource_class_id'))
            ->innerJoin('resource_class', 'vocabulary', 'vocabulary', $expr->eq('vocabulary.id', 'resource_class.vocabulary_id'))
            ->groupBy('val')
        ;
        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':entity_class'))
                ->setParameter('entity_class', $this->options['entity_class'], ParameterType::STRING);
        }

        return $this
            ->filterByVisibility($qb, 'o:resource_class')
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
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        if ($this->process === 'initials') {
            $qb
                ->select(
                    "UPPER(LEFT(resource_template.label, {$this->options['_initials']})) AS val",
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
            ->distinct(true)
            ->from('resource', 'resource')
            // The left join allows to get the total of items without resource
            // template.
            ->leftJoin('resource', 'resource_template', 'resource_template', $expr->eq('resource_template.id', 'resource.resource_template_id'))
            ->groupBy('val')
        ;
        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':entity_class'))
                ->setParameter('entity_class', $this->options['entity_class'], ParameterType::STRING);
        }

        return $this
            ->filterByVisibility($qb, 'o:resource_template')
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
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        // Count the number of items by item set.

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
                    // TODO Doctrine orm doesn't manage left() and convert(), but we may not need to convert: only for diacritics.
                    "UPPER(LEFT(resource_item_set.title, {$this->options['_initials']})) AS val",
                    'COUNT(resource.id) AS total'
                );
        } else {
            $qb
                ->select(
                    'item_set.item_set_id AS val',
                    'COUNT(resource.id) AS total'
                );
        }
        $qb
            ->distinct(true)
            ->from('resource', 'resource')
            ->innerJoin('resource', 'item', 'item', $expr->eq('item.id', 'resource.id'))
            // The left join allows to get the total of items without item set.
            ->leftJoin('item', 'item_item_set', 'item_set', $expr->andX($expr->eq('item_set.item_id', 'item.id'), $expr->neq('item_set.item_set_id', 0)))
            ->leftJoin('item_set', 'resource', 'resource_item_set', $expr->eq('resource_item_set.id', 'item_set.item_set_id'))
            ->groupBy('val')
        ;

        return $this
            ->filterByVisibility($qb, 'o:item_set')
            // TODO Check if items are limited by sites.
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
        $qb = $this->connection->createQueryBuilder();
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
                    "UPPER(LEFT(user.name, {$this->options['_initials']})) AS val",
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
            ->distinct(true)
            ->from('resource', 'resource')
            ->leftJoin('resource', 'user', 'user', $expr->eq('user.id', 'resource.owner_id'))
            ->groupBy('val')
        ;

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':entity_class'))
                ->setParameter('entity_class', $this->options['entity_class'], ParameterType::STRING);
        }

        return $this
            ->filterByVisibility($qb, 'o:owner')
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
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        // Count the number of items by site.

        // TODO Get all sites, even without items (or private items).

        if ($this->process === 'initials') {
            $qb
                ->select(
                    "UPPER(LEFT(site.title, {$this->options['_initials']})) AS val",
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
            ->distinct(true)
            ->from('resource', 'resource')
            ->innerJoin('resource', 'item', 'res', $expr->eq('res.id', 'resource.id'))
            ->leftJoin('res', 'item_site', 'res_site', $expr->eq('res_site.item_id', 'resource.id'))
            ->leftJoin('res_site', 'site', 'site', $expr->eq('site.id', 'res_site.site_id'))
            ->groupBy('val')
        ;

        // TODO Count item sets and media by site.

        return $this
            ->filterByVisibility($qb, 'o:site')
            ->manageOptions($qb, 'o:site')
            ->outputMetadata($qb, 'o:site');
    }

    /**
     * Get the list of accesses, the total for each one and the first resource.
     *
     * The module Access should be already checked.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listAccesses()
    {
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        /*
        SELECT access_status.level AS val, MIN(resource.id) AS val2, COUNT(resource.id) AS total
        FROM resource resource
        INNER JOIN item item ON item.id = resource.id
        LEFT JOIN access_status ON access_status.id = resource.id
        GROUP BY val;
         */

        // This is useless, but possible nevertheless.
        if ($this->process === 'initials') {
            $qb
                ->select(
                    "UPPER(LEFT(access_status.level, {$this->options['_initials']})) AS val",
                    'COUNT(resource.id) AS total'
                );
        } else {
            $qb
                ->select(
                    'access_status.level AS val',
                    'COUNT(resource.id) AS total'
                );
        }
        $qb
            ->distinct(true)
            ->from('resource', 'resource')
            ->innerJoin('resource', 'access_status', 'access_status', $expr->eq('access_status.id', 'resource.id'))
            ->groupBy('val')
        ;

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':entity_class'))
                ->setParameter('entity_class', $this->options['entity_class'], ParameterType::STRING);
        }

        return $this
            ->filterByVisibility($qb, 'access')
            ->manageOptions($qb, 'access')
            ->outputMetadata($qb, 'access');
    }

    protected function limitItemSetsToSite(QueryBuilder $qb): self
    {
        // @see \Omeka\Api\Adapter\ItemSetAdapter::buildQuery()
        if (isset($this->query['site_id']) && is_numeric($this->query['site_id'])) {
            $siteId = (int) $this->query['site_id'];
            $expr = $qb->expr();

            // TODO Check if this useful here.
            // Though $site isn't used here, this is intended to ensure that the
            // user cannot perform a query against a private site he doesn't
            // have access to.
            try {
                $this->adapterManager->get('sites')->findEntity($siteId);
            } catch (\Omeka\Api\Exception\NotFoundException$e) {
                $siteId = 0;
            }

            $qb
                ->innerJoin('resource_item_set', 'site_item_set', 'ref_site_item_set', $expr->eq('ref_site_item_set.item_set_id', 'resource_item_set.id'))
                ->andWhere($expr->eq('ref_site_item_set.site_id', ':ref_site_item_set_site'))
                ->setParameter(':ref_site_item_set_site', $siteId, ParameterType::INTEGER);
        }
        return $this;
    }

    protected function filterByVisibility(QueryBuilder $qb, $type): self
    {
        if ($this->acl->userIsAllowed(\Omeka\Entity\Resource::class, 'view-all')) {
            return $this;
        }
        return $this->user
            ? $this->filterByVisibilityForUser($qb, $type)
            : $this->filterByVisibilityForAnonymous($qb, $type);
    }

    protected function filterByVisibilityForAnonymous(QueryBuilder $qb, $type): self
    {
        /**
         * @see \Omeka\Db\Filter\ResourceVisibilityFilter
         * @see \Omeka\Db\Filter\ValueVisibilityFilter
         */
        switch ($type) {
            case 'o:item_set':
                $qb
                    ->andWhere('resource_item_set.is_public = 1');
                // No break.
            case 'resource_classes':
            case 'resource_templates':
            case 'item_sets':
            case 'resource_titles':
            case 'o:resource_class':
            case 'o:resource_template':
            case 'o:owner':
            case 'o:site':
            case 'access':
                $qb
                    ->andWhere('resource.is_public = 1');
                break;
            case 'properties':
            case 'o:property':
                $qb
                    ->andWhere('resource.is_public = 1')
                    ->andWhere('value.is_public = 1');
            default:
                break;
        }
        return $this;
    }

    protected function filterByVisibilityForUser(QueryBuilder $qb, $type): self
    {
        /**
         * @see \Omeka\Db\Filter\ResourceVisibilityFilter
         * @see \Omeka\Db\Filter\ValueVisibilityFilter
         */
        $expr = $qb->expr();
        switch ($type) {
            case 'o:item_set':
                $qb
                    ->andWhere($expr->orX(
                        'resource_item_set.is_public = 1',
                        'resource_item_set.owner_id = :user_id'
                    ))
                ;
                // No break.
            case 'resource_classes':
            case 'resource_templates':
            case 'item_sets':
            case 'resource_titles':
            case 'o:resource_class':
            case 'o:resource_template':
            case 'o:owner':
            case 'o:site':
            case 'access':
                $qb
                    ->andWhere($expr->orX(
                        'resource.is_public = 1',
                        'resource.owner_id = :user_id'
                    ))
                    ->setParameter('user_id', (int) $this->user->getId(), ParameterType::INTEGER)
                ;
                break;
            case 'properties':
            case 'o:property':
                $qb
                    ->andWhere($expr->orX(
                        'resource.is_public = 1',
                        'resource.owner_id = :user_id'
                    ))
                    ->andWhere($expr->orX(
                        'value.is_public = 1',
                        'value.resource_id = (SELECT r.id FROM resource r WHERE r.owner_id = :user_id AND r.id = value.resource_id)'
                    ))
                    ->setParameter('user_id', (int) $this->user->getId(), ParameterType::INTEGER)
                ;
                break;
            default:
                break;
        }
        return $this;
    }

    protected function filterByMainType(QueryBuilder $qb): self
    {
        // This filter is used by properties only and normally already included
        // in the select, but it allows to simplify it.
        if ($this->options['filters']['main_types'] && $this->options['filters']['main_types'] !== ['value', 'resource', 'uri']) {
            $expr = $qb->expr();
            if ($this->options['filters']['main_types'] === ['value']) {
                $qb->andWhere($expr->isNotNull('value.value'));
            } elseif ($this->options['filters']['main_types'] === ['uri']) {
                $qb->andWhere($expr->isNotNull('value.uri'));
            } elseif ($this->options['filters']['main_types'] === ['resource']) {
                $qb->andWhere($expr->isNotNull('value.value_resource_id'));
            } elseif ($this->options['filters']['main_types'] === ['value', 'uri']) {
                $qb->andWhere($expr->or($expr->isNotNull('value.value'), $expr->isNotNull('value.uri')));
            } elseif ($this->options['filters']['main_types'] === ['value', 'resource']) {
                $qb->andWhere($expr->or($expr->isNotNull('value.value'), $expr->isNotNull('value.value_resource_id')));
            } elseif ($this->options['filters']['main_types'] === ['uri', 'resource']) {
                $qb->andWhere($expr->or($expr->isNotNull('value.uri'), $expr->isNotNull('value.value_resource_id')));
            }
        }
        return $this;
    }

    protected function filterByDataType(QueryBuilder $qb): self
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
                            ->setParameter('filter_09', $filter === 'begin' ? '^[[:alpha:]]' : '[[:alpha:]]$', ParameterType::STRING);
                    } else {
                        $qb
                            ->andWhere($expr->like($column, ":filter_$filter"))
                            ->setParameter(
                                "filter_$filter",
                                $filterB . str_replace(['%', '_'], ['\%', '\_'], $firstFilter) . $filterE,
                                ParameterType::STRING
                            );
                    }
                } elseif (count($this->options['filters'][$filter]) <= 20) {
                    $orX = [];
                    foreach (array_values($this->options['filters'][$filter]) as $key => $string) {
                        $orX[] = $expr->like($column, sprintf(':filter_%s_%d)', $filter, ++$key));
                        $qb
                            ->setParameter(
                                "filter_{$filter}_$key",
                                $filterB . str_replace(['%', '_'], ['\%', '\_'], $string) . $filterE,
                                ParameterType::STRING
                            );
                    }
                    $qb
                        ->andWhere($expr->orX(...$orX));
                } else {
                    $regexp = implode('|', array_map('preg_quote', $this->options['filters'][$filter]));
                    $qb
                        ->andWhere("REGEXP($column, :filter_filter) = true")
                        ->setParameter("filter_$filter", $regexp, ParameterType::STRING);
                }
            }
        }
        return $this;
    }

    protected function manageOptions(QueryBuilder $qb, $type, array $args = []): self
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
                        ? "ANY_VALUE(UPPER(LEFT(resource.title, {$this->options['initial']}))) AS initial"
                        : "UPPER(LEFT(resource.title, {$this->options['initial']})) AS initial"
                );
        }

        if ($type === 'access' && $this->options['initial']) {
            // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
            $qb
                ->addSelect(
                    // 'CONVERT(UPPER(LEFT(COALESCE(access_status.level, {$this->options['initial']}), 1)) USING latin1) AS initial',
                    $this->supportAnyValue
                        ? "ANY_VALUE(UPPER(LEFT(access_status.level, {$this->options['initial']}))) AS initial"
                        : "UPPER(LEFT(access_status.level, {$this->options['initial']})) AS initial"
                );
        }

        if ($type === 'properties' && $this->options['initial']) {
            // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
            $qb
                ->addSelect(
                    // 'CONVERT(UPPER(LEFT(COALESCE(value.value, value.uri, value_resource.title), 1)) USING latin1) AS initial',
                    $this->supportAnyValue
                        ? "ANY_VALUE(UPPER(LEFT({$args['mainTypesString']}, {$this->options['initial']}))) AS initial"
                        : "UPPER(LEFT({$args['mainTypesString']}, {$this->options['initial']})) AS initial"
                );
        }

        if ($type === 'properties' && $this->options['distinct']) {
            $qb
                ->addSelect(
                    // TODO Warning with type "resource", that may be the same than "resource:item", etc.
                    'value_resource.id AS res',
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
                        // The join is different than in listDataForProperties().
                        ->leftJoin('value', 'reference_metadata', "ress_$strLocale", $expr->andX(
                            $expr->eq("ress_$strLocale.resource_id", 'value.resource_id'),
                            $expr->eq("ress_$strLocale.field", ':display_title'),
                            $expr->eq("ress_$strLocale.lang", ':locale_' . $strLocale)
                        ))
                        ->setParameter('locale_' . $strLocale, $locale, ParameterType::STRING);
                }
                $coalesce[] = 'ress.text';
                $ressText = $this->supportAnyValue
                    ? 'ANY_VALUE(COALESCE(' . implode(', ', $coalesce) . '))'
                    : 'COALESCE(' . implode(', ', $coalesce) . ')';
                $qb
                    // The join is different than in listDataForProperties().
                    ->leftJoin('value', 'reference_metadata', 'ress', $expr->andX(
                        $expr->eq('ress.resource_id', 'value.resource_id'),
                        $expr->eq('ress.field', ':display_title')
                    ))
                    ->setParameter('display_title', 'display_title', ParameterType::STRING)
                    ->addSelect(
                        // Note: for doctrine orm, separators must be set as parameters.
                        "GROUP_CONCAT(ress.resource_id, :unit_separator, $ressText SEPARATOR :group_separator) AS resources"
                    )
                    ->setParameter('unit_separator', chr(0x1F), ParameterType::STRING)
                    ->setParameter('group_separator', chr(0x1D), ParameterType::STRING)
                ;
            } else {
                $qb
                    ->leftJoin(
                        $type === 'resource_titles' ? 'resource' : 'value',
                        'resource',
                        'ress',
                        $expr->eq('ress.id', $type === 'resource_titles' ? 'resource.id' : 'value.resource_id')
                    )
                    ->addSelect(
                        // Note: for doctrine orm, separators must be set as parameters.
                        'GROUP_CONCAT(ress.id, :unit_separator, ress.title SEPARATOR :group_separator) AS resources'
                    )
                    ->setParameter('unit_separator', chr(0x1F), ParameterType::STRING)
                    ->setParameter('group_separator', chr(0x1D), ParameterType::STRING)
                ;
            }
        }

        if ($this->options['values']) {
            switch ($type) {
                case 'properties':
                case 'resource_classes':
                case 'resource_templates':
                    $qb
                        ->andWhere($expr->in('value.value', ':values'))
                        ->setParameter('values', $this->options['values'], Connection::PARAM_STR_ARRAY);
                    break;
                case 'resource_titles':
                    // TODO Nothing to filter for resource titles?
                    break;
                case 'o:property':
                    $values = $this->getPropertyIds($this->options['values']) ?: [0];
                    $qb
                        ->andWhere($expr->in('property.id', ':ids'))
                        ->setParameter('ids', $values, Connection::PARAM_INT_ARRAY);
                    break;
                case 'o:resource_class':
                    $values = $this->getResourceClassIds($this->options['values']) ?: [0];
                    $qb
                        ->andWhere($expr->in('resource_class.id', ':ids'))
                        ->setParameter('ids', $values, Connection::PARAM_INT_ARRAY);
                    break;
                case 'o:resource_template':
                    $values = $this->getResourceTemplateIds($this->options['values']) ?: [0];
                    $qb
                        ->andWhere($expr->in('resource_template.id', ':ids'))
                        ->setParameter('ids', $values, Connection::PARAM_INT_ARRAY);
                    break;
                case 'o:item_set':
                    $qb
                        ->andWhere($expr->in('item_set.id', ':ids'))
                        ->setParameter('ids', array_map('intval', $this->options['values']), Connection::PARAM_INT_ARRAY);
                    break;
                case 'o:owner':
                    $qb
                        ->andWhere($expr->in('user.id', ':ids'))
                        ->setParameter('ids', array_map('intval', $this->options['values']), Connection::PARAM_INT_ARRAY);
                    break;
                case 'o:site':
                    $qb
                        ->andWhere($expr->in('site.id', ':ids'))
                        ->setParameter('ids', array_map('intval', $this->options['values']), Connection::PARAM_INT_ARRAY);
                    break;
                case 'access':
                    $qb
                        ->andWhere($expr->in('access_status.level', ':values'))
                        ->setParameter('values', $this->options['values'], Connection::PARAM_STR_ARRAY);
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
        $result = $qb->execute()->fetchAllAssociative();
        if (!count($result)) {
            return [];
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
                // FIXME Fix the api call inside a loop. Use the new table reference_metadata.
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

        /** @var \Doctrine\DBAL\Query\QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select(
                // Here, this is the count of references, not resources.
                'COUNT(refmeta.text)'
            )
            ->distinct(true)
            ->from('reference_metadata', 'refmeta')
            ->innerJoin('refmeta', 'resource', 'resource', $expr->eq('resource.id', 'refmeta.resource_id'))
            ->andWhere($expr->in('refmeta.field', ':properties'))
            ->setParameter('properties', $this->getPropertyTerms($propertyIds), Connection::PARAM_STR_ARRAY)
        ;

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':entity_class'))
                ->setParameter('entity_class', $this->options['entity_class'], ParameterType::STRING);
        }

        $this->searchQuery($qb);

        return (int) $qb->execute()->fetchOne();
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

        /** @var \Doctrine\DBAL\Query\QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select(
                'COUNT(resource.id)'
            )
            ->distinct(true)
            ->from('resource', 'resource')
            ->andWhere($expr->in('resource.resource_class_id', ':resource_classes'))
            ->setParameter('resource_classes', array_map('intval', $resourceClassIds), Connection::PARAM_INT_ARRAY);

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':entity_class'))
                ->setParameter('entity_class', $this->options['entity_class'], ParameterType::STRING);
        }

        $this->searchQuery($qb);

        return (int) $qb->execute()->fetchOne();
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

        /** @var \Doctrine\DBAL\Query\QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        $qb
            ->select(
                'COUNT(resource.id)'
            )
            ->distinct(true)
            ->from('resource', 'resource')
            ->andWhere($expr->in('resource.resource_template_id', ':resource_templates'))
            ->setParameter('resource_templates', array_map('intval', $resourceTemplateIds), Connection::PARAM_INT_ARRAY);

        if ($this->options['entity_class'] !== \Omeka\Entity\Resource::class) {
            $qb
                ->andWhere($expr->eq('resource.resource_type', ':entity_class'))
                ->setParameter('entity_class', $this->options['entity_class'], ParameterType::STRING);
        }

        $this->searchQuery($qb);

        return (int) $qb->execute()->fetchOne();
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

        /** @var \Doctrine\DBAL\Query\QueryBuilder $qb */
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        if ($this->options['entity_class'] !== \Omeka\Entity\Item::class) {
            return 0;
        }

        $qb
            ->select(
                'COUNT(resource.id)'
            )
            ->distinct(true)
            ->from('resource', 'resource')
            ->innerJoin('resource', 'item', 'res', $expr->eq('res.id', 'resource.id'))
            // See \Omeka\Api\Adapter\ItemAdapter::buildQuery()
            ->innerJoin('res', 'item_item_set', 'item_set', $expr->in('item_set.item_set_id', ':item_sets'))
            ->setParameter('item_sets', array_map('intval', $itemSetIds), Connection::PARAM_INT_ARRAY);

        $this->searchQuery($qb);

        return (int) $qb->execute()->fetchOne();
    }

    /**
     * Limit the results with a query.
     *
     * The query is generally the site query, but may be complex with advanced
     * search, in particular for facets in module AdvancedSearch.
     */
    protected function searchQuery(QueryBuilder $qb, ?string $type = null): self
    {
        if (empty($this->query)) {
            return $this;
        }

        // TODO Search in any resources in Omeka S v4.1.
        if (empty($this->options['entity_class']) || $this->options['entity_class'] === \Omeka\Entity\Resource::class) {
            return $this;
        }

        $resourceName = $this->mapEntityClassToResourceName($this->options['entity_class']);
        if (empty($resourceName)) {
            return $this;
        }

        $mainQuery = $this->query;

        // When searching by item set or site, remove the matching query filter.
        // TODO Is it still needed?
        if ($type === 'o:item_set') {
            unset($mainQuery['item_set_id']);
        }
        if ($type === 'o:site') {
            unset($mainQuery['site_id']);
        }

        // In the previous version, the query builder was the orm qb. It is now
        // the dbal qb, so it doesn't manage entities.
        // Anyway, the output is only scalar ids.
        // To avoid issues with parameters of the sub-qb, get ids from here.
        // TODO Do a real subquery as before instead of a double query. Most of the time (facets), it should be cached by doctrine.
        // Use an api request.
        $ids = $this->api->search($resourceName, $mainQuery, ['returnScalar' => 'id'])->getContent();

        // There is no colision: the adapter query uses alias "omeka_" + index.
        $qb
            ->andWhere($qb->expr()->in('resource.id', ':resource_ids'))
            ->setParameter('resource_ids', array_keys($ids), Connection::PARAM_INT_ARRAY);

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
            // Module Access.
            'access' => 'access',
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
                'access' => $translate('Access'), // @translate
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
     * Normalize the resource name as a database table.
     */
    protected function mapResourceNameToTable($resourceName): string
    {
        $resourceTableMap = [
            null => 'resource',
            'items' => 'item',
            'item_sets' => 'item_set',
            'media' => 'media',
            'resources' => 'resource',
        ];
        return $resourceTableMap[$resourceName] ?? 'resource';
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
