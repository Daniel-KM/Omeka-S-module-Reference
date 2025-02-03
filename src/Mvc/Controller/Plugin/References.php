<?php declare(strict_types=1);

namespace Reference\Mvc\Controller\Plugin;

use Common\Stdlib\EasyMeta;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\ORM\EntityManager;
use Laminas\Log\Logger;
use Laminas\Mvc\Controller\Plugin\AbstractPlugin;
use Omeka\Api\Adapter\Manager as AdapterManager;
use Omeka\Api\Manager as ApiManager;
use Omeka\Entity\User;
use Omeka\Mvc\Controller\Plugin\Translate;
use Omeka\Permissions\Acl;

class References extends AbstractPlugin
{
    /**
     * @var \Omeka\Permissions\Acl
     */
    protected $acl;

    /**
     * @param \Omeka\Api\Adapter\Manager
     */
    protected $adapterManager;

    /**
     * @var \Omeka\Api\Manager
     */
    protected $api;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    /**
     * @var \Common\Stdlib\EasyMeta
     */
    protected $easyMeta;

    /**
     * @var \Doctrine\ORM\EntityManager
     */
    protected $entityManager;

    /**
     * @var \Laminas\Log\Logger
     */
    protected $logger;

    /**
     * @var \Omeka\Mvc\Controller\Plugin\Translate
     */
    protected $translate;

    /**
     * @var ?\Omeka\Entity\User
     */
    protected $user;

    /**
     * @var bool
     */
    protected $hasAccess;

    /**
     * @var bool
     */
    protected $supportAnyValue;

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
     * Normalized options.
     * Specific options for metadata are set with key "meta_options".
     *
     * @var array
     */
    protected $options = [];

    /**
     * Normalized options by metadata key. Default ones use key "__default".
     *
     * @var array
     */
    protected $optionsByMeta = [];

    /**
     * Normalized options for the metadata that are currently processing.
     *
     * @var array
     */
    protected $optionsCurrent = [];

    /**
     * Options by default.
     *
     * @var array
     */
    protected $optionsDefaults = [
        // Options to filter, limit and sort results (used for sql).
        'resource_name' => 'items',
        'sort_by' => 'alphabetic',
        'sort_order' => 'ASC',
        'page' => 1,
        'per_page' => 0,
        'filters' => [
            'languages' => [],
            'main_types' => [],
            'data_types' => [],
            'values' => [],
            'begin' => [],
            'end' => [],
        ],
        // Output options.
        'first' => false,
        'list_by_max' => 0,
        'fields' => [],
        'initial' => false,
        'distinct' => false,
        'data_type' => false,
        'lang' => false,
        'include_without_meta' => false,
        'single_reference_format' => false,
        'locale' => [],
        'output' => 'list',
        'meta_options' => [],
    ];

    /**
     * The current process: "list", "count" or "initials".
     *
     * Only the process "initials" is set for now.
     *
     * @var string
     */
    protected $process;

    public function __construct(
        Acl $acl,
        AdapterManager $adapterManager,
        ApiManager $api,
        Connection $connection,
        EasyMeta $easyMeta,
        EntityManager $entityManager,
        Logger $logger,
        Translate $translate,
        ?User $user,
        bool $hasAccess,
        bool $supportAnyValue
    ) {
        $this->acl = $acl;
        $this->adapterManager = $adapterManager;
        $this->api = $api;
        $this->connection = $connection;
        $this->easyMeta = $easyMeta;
        $this->entityManager = $entityManager;
        $this->logger = $logger;
        $this->translate = $translate;
        $this->user = $user;
        $this->hasAccess = $hasAccess;
        $this->supportAnyValue = $supportAnyValue;
    }

    /**
     * Get the references.
     *
     * @param array|string $metadata List of metadata to get references for.
     * Classes, properties terms, template names, or other Omeka metadata names.
     * Similar types of metadata may be grouped to get aggregated references,
     * for example ['Dates' => ['dcterms:date', 'dcterms:issued']], with the key
     * used as key and label in the result. Each value may be a comma-separated
     * list of metadata, that will be exploded to an array of metadata to group.
     * @param array $query An Omeka search query to limit the base pool.
     * @param array $options Options for output.
     * - Options to specify, filter, limit and sort references:
     *   - resource_name (string): items (default), "item_sets", "media",
     *     "resources".
     *   - page (int): the page to output, the first one in most of the cases.
     *   - per_page (int): the number of references to output.
     *   - sort_by (string): "alphabetic" (default), "total", "values", or any
     *     available column in the table of the database. For values, they
     *     should be set as filters values.
     *   - sort_order (string): "asc" (default) or "desc".
     *   - filters (array): Limit values to the specified data. The passed
     *     settings may be a string separated by "|" (recommended) or ",", that
     *     will be exploded with the separator "|" if present, else ",".
     *     - languages (array): list of languages. Values without language are
     *       defined with a null or the string "null" (the empty string "" is
     *       deprecated). It is recommended to append the empty language when a
     *       language is set. This option is used only for properties.
     *     - main_types (array): array with "literal", "resource", or "uri".
     *       Filter property values according to the main data type. Default is
     *       to search all data types.
     *     - data_types (array): Filter property values according to the data
     *       types. Default data types are "literal", "resource", "resource:item",
     *       "resource:itemset", "resource:media" and "uri". Data types from
     *       other modules are managed too.
     *       Warning: "resource" is not the same than specific resources.
     *       Use module Bulk Edit or Easy Admin to specify all resources
     *       automatically.
     *     - values (array): Allow to limit the answer to the specified values,
     *       for example a short list of keywords.
     *     - begin (array): Filter property values that begin with these
     *       strings, generally one or more initials.
     *     - end (array): Filter property values that end with these strings.
     * - Options for output:
     *   - first (bool): Append the id of the first resource (default false).
     *   - list_by_max (int): 0 (default), or the max number of resources for
     *     each reference. The max number should be below 1024, that is the hard
     *     coded database limit of mysql and mariadb for function group_concat.
     *   - fields (array): the fields to use for the list of resources, if any.
     *     If not set, the output is an associative array with id as key and
     *     title as value. If set, value is an array of the specified fields.
     *   - initial (int): If set and not 0 or false, append the specified first
     *     letters of each result. It is useful for example to extract years
     *     from iso 8601 dates.
     *   - distinct (bool): Distinct values by type (default false).
     *   - data_type (bool): Include the data type of values (default false).
     *   - lang (bool): Include the language of value (default false).
     *   - locale (string|array): Allow to get the returned values in the
     *     specified languages when a property has translated values. Use "null"
     *     to get a value without language.
     *     Unlike Omeka core, it get the translated title of linked resources.
     *   - include_without_meta (bool): Include the total of resources with no
     *     metadata (default false).
     *   - single_reference_format (bool): Use the old output format without the
     *     deprecated warning for single references without named key.
     *   - output (string): "list" (default), "associative" or "values". When
     *     options "first", "list_by_max", "initial", "distinct", "data_type", or
     *     "lang" are used, the output is forced to "list".
     * Some options and some combinations are not managed for some metadata.
     *
     * @todo Option locale: Use locale first or any locale (so all results preferably in the specified locale).
     * @todo Option locale: Check if the option include_without_meta is still needed with data types.
     * @todo Replace "resource_name" by "resource_type"?
     */
    public function __invoke($metadata = [], ?array $query = [], ?array $options = []): self
    {
        return $this
            ->setMetadata($metadata)
            ->setQuery($query)
            ->setOptions($options);
    }

    /**
     * @see self::__invoke()
     */
    public function setMetadata($metadata = []): self
    {
        if (!$metadata) {
            $this->metadata = [];
        } elseif (is_array($metadata)) {
            $this->metadata = array_filter($metadata);
        } elseif (is_string($metadata)) {
            $this->metadata = ['fields' => $metadata];
        } else {
            $this->metadata = [];
        }

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

    /**
     * @see self::__invoke()
     */
    public function getMetadata(): array
    {
        return $this->metadata;
    }

    /**
     * @see self::__invoke()
     */
    public function setQuery(?array $query = []): self
    {
        // Remove useless keys.
        if ($query) {
            unset($query['sort_by']);
            unset($query['sort_order']);
            unset($query['per_page']);
            unset($query['page']);
            unset($query['offset']);
            unset($query['limit']);
            $filterEmpty = fn ($v) => $v !== '' && $v !== [] && $v !== null;
            $this->query = array_filter($query, $filterEmpty);
        } else {
            $this->query = [];
        }
        return $this;
    }

    /**
     * @see self::__invoke()
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * @see self::__invoke()
     */
    public function setOptions(?array $options): self
    {
        $this->options = $this->prepareOptions($options);
        $metaOptions = $options['meta_options'] ?? [];
        foreach ($metaOptions as $key => $subMetaOptions) {
            $metaOptions[$key] = $this->prepareOptions($subMetaOptions, true);
        }
        $this->optionsByMeta = ['__default' => $this->options] + $metaOptions;
        // Don't set default options as meta options.
        $this->options['meta_options'] = $metaOptions;
        $this->optionsCurrent = [];
        return $this;
    }

    /**
     * @param null|string|int $key Allow to get option for a specific metadata.
     * When the key does not exist, return the default options.
     */
    public function getOptions($key = null): array
    {
        return $key === null
            ? $this->options
            : ($this->optionsByMeta[$key] ?? $this->optionsByMeta['__default']);
    }

    /**
     * @return array Associative array with total and first record ids.
     */
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

        // TODO Convert all queries into a single or two sql queries (at least for properties and classes).
        // TODO Return all needed columns.

        $result = [];
        foreach ($fields as $keyOrLabel => $inputField) {
            $this->optionsCurrent = $this->getOptions($keyOrLabel);
            $isOutputAssociative = $this->optionsCurrent['output'] === 'associative';
            $isOutputValues = $this->optionsCurrent['output'] === 'values';
            $isOutputSimple = $isOutputAssociative || $isOutputValues;
            $isOutputList = !$isOutputSimple;

            $dataFields = $this->prepareFields($inputField, $keyOrLabel);

            $keyResult = $dataFields['key_result'];

            if ($dataFields['is_single']
                && (empty($keyOrLabel) || is_numeric($keyOrLabel))
                && $isOutputList
            ) {
                $result[$keyResult] = reset($dataFields['output']['o:request']['o:field']);
                if (!$this->optionsCurrent['single_reference_format']) {
                    $result[$keyResult] = [
                        'deprecated' => 'This output format is deprecated. Set a string key to metadata to use the new format or append option "single_reference_format" to remove this warning.', // @translate
                        ]
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
                    if ($isOutputSimple) {
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
                    if ($isOutputSimple) {
                        $result[$keyResult]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $valueData) {
                            $resourceClass = $this->getResourceClass($valueData['val']);
                            $resourceClass['o:label'] = $this->translate->__invoke($resourceClass['o:label']);
                            $result[$keyResult]['o:references'][] = $resourceClass + $valueData;
                        }
                    }
                    break;

                case 'o:resource_template':
                    $values = $this->listResourceTemplates();
                    if ($isOutputSimple) {
                        $result[$keyResult]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $valueData) {
                            $resourceTemplate = $this->getResourceTemplate($valueData['val']);
                            $result[$keyResult]['o:references'][] = $resourceTemplate + $valueData;
                        }
                    }
                    break;

                case 'o:item_set':
                    // Quick check for resource: only "items" have item sets.
                    $values = in_array($this->optionsCurrent['resource_name'], ['items', 'resources'])
                        ? $this->listItemSets()
                        : [];
                    if ($isOutputSimple) {
                        $result[$keyResult]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $valueData) {
                            $meta = $this->getItemSet($valueData['val']);
                            unset($meta['@type']);
                            $result[$keyResult]['o:references'][] = $meta + $valueData;
                        }
                    }
                    break;

                case 'o:owner':
                    $values = $this->listOwners();
                    if ($isOutputSimple) {
                        $result[$keyResult]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $valueData) {
                            $meta = $this->getOwner($valueData['val']);
                            unset($meta['@type']);
                            $result[$keyResult]['o:references'][] = $meta + $valueData;
                        }
                    }
                    break;

                case 'o:site':
                    $values = $this->listSites();
                    if ($isOutputSimple) {
                        $result[$keyResult]['o:references'] = $values;
                    } else {
                        foreach (array_filter($values) as $valueData) {
                            $meta = $this->getSite($valueData['val']);
                            unset($meta['@type']);
                            $result[$keyResult]['o:references'][] = $meta + $valueData;
                        }
                    }
                    break;

                // Module Access.
                case 'access':
                    if ($this->hasAccess) {
                        $values = $this->listAccesses();
                        if ($isOutputSimple) {
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
            $this->optionsCurrent = $this->getOptions($keyOrLabel);
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

        $result = [];
        foreach ($fields as $keyOrLabel => $inputField) {
            $this->optionsCurrent = $this->getOptions($keyOrLabel);
            $this->optionsCurrent['first'] = false;
            $this->optionsCurrent['list_by_max'] = 0;
            $this->optionsCurrent['initial'] = false;
            // TODO Check options for initials.
            $this->optionsCurrent['distinct'] = false;
            $this->optionsCurrent['data_type'] = false;
            $this->optionsCurrent['lang'] = false;
            $this->optionsCurrent['fields'] = [];
            // TODO Use option "values" instead of "associative"?
            $this->optionsCurrent['output'] = 'associative';

            // Internal option to specify the number of characters of each "initial".
            $this->optionsCurrent['_initials'] = (int) $this->optionsCurrent['initial'] ?: 1;

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
                    // Quick check for resource: only "items" have item sets.
                    $values = in_array($this->optionsCurrent['resource_name'], ['items', 'resources'])
                        ? $this->listItemSets()
                        : [];
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
        return $result;
    }

    protected function prepareOptions(?array $options, bool $isMetaOptions = false): array
    {
        $defaultOptions = $isMetaOptions ? $this->options : $this->optionsDefaults;

        if (!$options) {
            return $defaultOptions;
        }

        if (isset($options['values'])) {
            $options['filters']['values'] ??= $options['values'];
            $this->logger->err(
                'To get references, to pass the option "values" as main key is deprecated. it should be set as a sub-key of filters.' // @ŧranslate
            );
        }

        if (isset($options['datatype'])) {
            $options['data_type'] ??= $options['datatype'];
            $this->logger->err(
                'To get references, to pass the option "data" as main key is deprecated in favor of "data_type".' // @ŧranslate
            );
        }

        if (isset($options['filters']['datatypes'])) {
            $options['filters']['data_types'] ??= $options['filters']['datatypes'];
            $this->logger->err(
                'To get references, to pass the option "datatypes" in filters is deprecated in favor of "data_types".' // @ŧranslate
            );
        }

        // Explode with separator "|" if present, else ",".
        // For complex cases, an array should be used.
        $explode = fn ($string): array => explode(strpos((string) $string, '|') === false ? ',' : '|', (string) $string);

        // Clean options filters in place.
        $clean = fn (array $array): array => array_unique(array_filter(array_map('trim', $array)));
        $cleanAllow0 = fn (array $array): array => array_unique(array_filter(array_map('trim', $array), fn ($v) => $v || $v === '0'));
        $cleanNoTrim = fn (array $array): array => array_unique(array_filter($array));

        // Set keys for all keys and only them, keeping order.
        $options += array_intersect_key(array_replace($defaultOptions, $options), $defaultOptions);
        $options['filters'] = array_intersect_key(array_replace($defaultOptions['filters'], $options['filters'] ?? []), $defaultOptions['filters']);

        // The option "meta_options" is managed separately.
        if ($isMetaOptions) {
            unset($options['meta_options']);
        } else {
            $options['meta_options'] = [];
        }

        $resourceName = in_array($options['resource_name'], ['items', 'item_sets', 'media', 'resources', 'annotations'])
            ? $options['resource_name']
            : $defaultOptions['resource_name'];
        $first = !empty($options['first']);
        $listByMax = empty($options['list_by_max']) ? 0 : (int) $options['list_by_max'];
        $fields = empty($options['fields']) ? [] : $options['fields'];
        $initial = empty($options['initial']) ? false : (int) $options['initial'];
        $distinct = !empty($options['distinct']);
        $dataType = !empty($options['data_type']);
        $lang = !empty($options['lang']);
        // Currently, only one locale can be used, but it is managed as
        // array internally.
        if (empty($options['locale'])) {
            $locales = [];
        } else {
            $locales = is_array($options['locale']) ? $options['locale'] : [$options['locale']];
            $locales = array_filter(array_unique(array_map('trim', array_map('strval', $locales))), fn ($v) => ctype_alnum(str_replace(['-', '_'], ['', ''], $v)));
            if (($pos = array_search('null', $locales)) !== false) {
                $locales[$pos] = '';
            }
        }

        $options = [
            // Options to filter, limit and sort results (used for sql).
            'resource_name' => $resourceName,
            'sort_by' => $options['sort_by'] ?? 'alphabetic',
            'sort_order' => strcasecmp((string) $options['sort_order'], 'desc') === 0 ? 'DESC' : 'ASC',
            'page' => !is_numeric($options['page']) || !(int) $options['page'] ? $defaultOptions['page'] : (int) $options['page'],
            'per_page' => !is_numeric($options['per_page']) || !(int) $options['per_page'] ? $defaultOptions['per_page'] : (int) $options['per_page'],
            'filters' => $options['filters'],
            // Output options.
            'first' => $first,
            'list_by_max' => $listByMax,
            'fields' => $fields,
            'initial' => $initial,
            'distinct' => $distinct,
            'data_type' => $dataType,
            'lang' => $lang,
            'include_without_meta' => !empty($options['include_without_meta']),
            'single_reference_format' => !empty($options['single_reference_format']),
            'locale' => $locales,
            'output' => in_array($options['output'], ['associative', 'values']) && !$first && !$listByMax && !$initial && !$distinct && !$dataType && !$lang
                ? $options['output']
                : 'list',
        ];

        if (!is_array($options['fields'])) {
            $options['fields'] = $explode($options['fields']);
        }
        $options['fields'] = $clean($options['fields']);

        // The check for length avoids to add a filter on values without any
        // language. When set as string, it should be defined as string "null".
        // The empty string to append values without languages ("||" or
        // leading/trailing "|" when the list of languages is set as string) is
        // deprecated.
        if (!is_array($options['filters']['languages'])) {
            $options['filters']['languages'] = $explode($options['filters']['languages']);
        }
        // Clean the empty language as empty string.
        if (in_array('', $options['filters']['languages'], true)) {
            $this->logger->warn(
                'To get references, the empty string as option for languages is deprecated in favor of null or the string "null".' // @ŧranslate
            );
        }
        $noEmptyLanguages = array_diff($options['filters']['languages'], ['null', null, '', 0, '0']);
        if (count($noEmptyLanguages) !== count($options['filters']['languages'])) {
            $options['filters']['languages'] = $noEmptyLanguages;
            $options['filters']['languages'][] = '';
        }
        // No filter in order to manage the empty language.
        $options['filters']['languages'] = array_unique(array_map('trim', $options['filters']['languages']));

        // May be an array or a string (literal, uri or resource, in this order).
        if (!is_array($options['filters']['main_types'])) {
            $options['filters']['main_types'] = $explode($options['filters']['main_types']);
        }
        $options['filters']['main_types'] = $clean($options['filters']['main_types']);
        $options['filters']['main_types'] = array_values(array_intersect(['value', 'resource', 'uri'], $options['filters']['main_types']));
        $options['filters']['main_types'] = array_combine($options['filters']['main_types'], $options['filters']['main_types']);

        if (!is_array($options['filters']['data_types'])) {
            $options['filters']['data_types'] = $explode($options['filters']['data_types']);
        }
        $options['filters']['data_types'] = $clean($options['filters']['data_types']);

        if (!is_array($options['filters']['values'])) {
            $options['filters']['values'] = $explode($options['filters']['values']);
        }
        $options['filters']['values'] = $cleanAllow0($options['filters']['values']);

        // No trim for begin/end.
        if (!is_array($options['filters']['begin'])) {
            $options['filters']['begin'] = $explode($options['filters']['begin']);
        }
        $options['filters']['begin'] = $cleanNoTrim($options['filters']['begin']);

        if (!is_array($options['filters']['end'])) {
            $options['filters']['end'] = $explode($options['filters']['end']);
        }
        $options['filters']['end'] = $cleanNoTrim($options['filters']['end']);

        // Check for sort by values when no values are defined.
        if ($options['sort_by'] === 'values' && empty($options['filters']['values'])) {
            $this->logger->err(
                'The order for facets is "by a list of values", but no values are defined.' // @translate
            );
            $options['sort_by'] = 'alphabetic';
        }

        return $options;
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
            ->distinct()
            ->from('value', 'value')
            ->where($expr->in('value.property_id', ':properties'))
            ->setParameter('properties', array_map('intval', $propertyIds), Connection::PARAM_INT_ARRAY)
            ->groupBy('val')
        ;

        // The values should be distinct for each type.
        $table = $this->getTableDerivateResource($this->optionsCurrent['resource_name']);
        if (empty($table)) {
            $qb
                ->innerJoin('value', 'resource', 'resource', $expr->eq('resource.id', 'value.resource_id'))
                ->leftJoin('value', 'resource', 'value_resource', $expr->eq('value_resource.id', 'value.value_resource_id'));
        } else {
            $entityClass = $this->easyMeta->entityClass($this->optionsCurrent['resource_name']);
            $qb
                ->innerJoin('value', 'resource', 'resource', $expr->eq('resource.id', 'value.resource_id'))
                ->innerJoin('value', $table, 'vrs', $expr->eq('vrs.id', 'value.resource_id'))
                // It is not possible to use two left joins here.
                ->leftJoin('value', 'resource', 'value_resource', $expr->and(
                    $expr->eq('value_resource.id', 'value.value_resource_id'),
                    $expr->eq('value_resource.resource_type', ':entity_class')
                ))
                ->setParameter('entity_class', $entityClass, ParameterType::STRING);
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
        if ($this->optionsCurrent['filters']['main_types']) {
            $mainTypes = array_intersect_key($mainTypes, $this->optionsCurrent['filters']['main_types']);
        }
        $mainTypesString = count($mainTypes) === 1
            ? reset($mainTypes)
            : 'COALESCE(' . implode(', ', $mainTypes) . ')';

        if ($this->process === 'initials') {
            if ($this->optionsCurrent['locale']) {
                $qb
                    ->select(
                        // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert. Anyway convert should be only for diacritics.
                        // 'CONVERT(UPPER(LEFT(refmeta.text, 1)) USING latin1) AS val',
                        $val = "UPPER(LEFT(refmeta.text, {$this->optionsCurrent['_initials']})) AS val"
                    )
                    ->innerJoin('value', 'reference_metadata', 'refmeta', $expr->eq('refmeta.value_id', 'value.id'))
                    ->andWhere($expr->in('refmeta.lang', ':locales'))
                    ->setParameter('locales', $this->optionsCurrent['locale'], Connection::PARAM_STR_ARRAY)
                ;
            } else {
                // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
                $qb
                    ->select(
                        // 'CONVERT(UPPER(LEFT($mainTypesString, $this->optionsCurrent['_initials'])) USING latin1) AS val',
                        $val = $this->supportAnyValue
                            ? "ANY_VALUE(UPPER(LEFT($mainTypesString, {$this->optionsCurrent['_initials']}))) AS val"
                            : "UPPER(LEFT($mainTypesString, {$this->optionsCurrent['_initials']})) AS val"
                    )
                ;
            }
        } else {
            if ($this->optionsCurrent['locale']) {
                $qb
                    ->select(
                        $val = 'refmeta.text AS val'
                    )
                    ->innerJoin('value', 'reference_metadata', 'refmeta', $expr->eq('refmeta.value_id', 'value.id'))
                    ->andWhere($expr->in('refmeta.lang', ':locales'))
                    ->setParameter('locales', $this->optionsCurrent['locale'], Connection::PARAM_STR_ARRAY)
                ;
            } else {
                $qb
                    ->select(
                        $val = $this->supportAnyValue
                            ? "ANY_VALUE($mainTypesString) AS val"
                            : "$mainTypesString AS val"
                    )
                ;
            }
        }

        if ($this->optionsCurrent['output'] !== 'values') {
            $qb
                ->addSelect('COUNT(resource.id) AS total');
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
                    "UPPER(LEFT(resource.title, {$this->optionsCurrent['_initials']})) AS val"
                );
        } else {
            $qb
                ->select(
                    'resource.title AS val'
                );
        }

        if ($this->optionsCurrent['output'] !== 'values') {
            $qb
                ->addSelect('COUNT(resource.id) AS total');
        }

        $qb
            ->distinct()
            ->from('resource', 'resource')
            ->where($expr->in('resource.resource_class_id', ':resource_classes'))
            ->setParameter('resource_classes', array_map('intval', $resourceClassIds), Connection::PARAM_INT_ARRAY)
            ->groupBy('val');

        return $this
            ->filterByResourceType($qb)
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
    protected function listDataForResourceTemplates(array $resourceTemplateIds): array
    {
        if (empty($resourceTemplateIds)) {
            return [];
        }

        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        if ($this->process === 'initials') {
            $qb
                ->select(
                    "UPPER(LEFT(resource.title, {$this->optionsCurrent['_initials']})) AS val"
                );
        } else {
            $qb
                ->select(
                    'resource.title AS val'
                );
        }

        if ($this->optionsCurrent['output'] !== 'values') {
            $qb
                ->addSelect('COUNT(resource.id) AS total');
        }

        $qb
            ->distinct()
            ->from('resource', 'resource')
            ->where($expr->in('resource.resource_template_id', ':resource_templates'))
            ->setParameter('resource_templates', array_map('intval', $resourceTemplateIds), Connection::PARAM_INT_ARRAY)
            ->groupBy('val');

        return $this
            ->filterByResourceType($qb)
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

        if (!in_array($this->optionsCurrent['resource_name'], ['items', 'resources'])) {
            return [];
        }

        $this->storeOptionCurrentResourceName();
        $this->optionsCurrent['resource_name'] = 'items';

        if ($this->process === 'initials') {
            $qb
                ->select(
                    "UPPER(LEFT(resource.title, {$this->optionsCurrent['_initials']})) AS val"
                );
        } else {
            $qb
                ->select(
                    'resource.title AS val'
                );
        }

        if ($this->optionsCurrent['output'] !== 'values') {
            $qb
                ->addSelect('COUNT(resource.id) AS total');
        }

        $qb
            ->distinct()
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
            ->storeOptionCurrentResourceName(true)
            ->outputMetadata($qb, 'item_sets');
    }

    /**
     * Get the list of used values for the title, the total for each one and the
     * first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listDataForResourceTitle(): array
    {
        $qb = $this->connection->createQueryBuilder();

        // Note: Doctrine ORM requires simple label, without quote or double quote:
        // "o:label" is not possible, neither "count". Use of Doctrine DBAL now.

        if ($this->process === 'initials') {
            $qb
                ->select(
                    $this->supportAnyValue
                        ? "ANY_VALUE(UPPER(LEFT(resource.title, {$this->optionsCurrent['_initials']}))) AS val"
                        : "UPPER(LEFT(resource.title, {$this->optionsCurrent['_initials']})) AS val"
                );
        } else {
            $qb
                ->select(
                    $this->supportAnyValue
                        ? 'ANY_VALUE(resource.title) AS val'
                        : 'resource.title AS val'
                );
        }

        if ($this->optionsCurrent['output'] !== 'values') {
            $qb
                ->addSelect('COUNT(resource.id) AS total');
        }

        $qb
            // "Distinct" avoids to count duplicate values in properties in a
            // resource: we count resources, not properties.
            ->distinct()
            ->from('resource', 'resource')
            ->groupBy('val')
        ;

        return $this
            // TODO Improve filter for "o:title".
            // ->filterByMainType($qb)
            // ->filterByDataType($qb)
            // ->filterByLanguage($qb)
            ->filterByResourceType($qb)
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
                    "UPPER(LEFT(CONCAT(vocabulary.prefix, ':', property.local_name), {$this->optionsCurrent['_initials']})) AS val"
                );
        } else {
            $qb
                ->select(
                    // 'property.label AS val',
                    // Important: use single quote for string ":", else doctrine fails in ORM.
                    "CONCAT(vocabulary.prefix, ':', property.local_name) AS val"
                );
        }

        if ($this->optionsCurrent['output'] !== 'values') {
            $qb
                ->addSelect('COUNT(value.resource_id) AS total');
        }

        $qb
            // "Distinct" avoids to count resources with multiple values
            // multiple times for the same property: we count resources, not
            // properties.
            ->distinct()
            ->from('resource', 'resource')
            ->innerJoin('resource', 'value', 'value', $expr->eq('value.resource_id', 'resource.id'))
            // The left join allows to get the total of items without property.
            ->leftJoin('value', 'property', 'property', $expr->eq('property.id', 'value.property_id'))
            ->innerJoin('property', 'vocabulary', 'vocabulary', $expr->eq('vocabulary.id', 'property.vocabulary_id'))
            ->groupBy('val')
        ;

        return $this
            ->filterByResourceType($qb)
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
    protected function listResourceClasses(): array
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
                    "UPPER(LEFT(CONCAT(vocabulary.prefix, ':', property.local_name), {$this->optionsCurrent['_initials']})) AS val"
                );
        } else {
            $qb
                ->select(
                    // 'resource_class.label AS val',
                    // Important: use single quote for string ":", else doctrine orm fails.
                    "CONCAT(vocabulary.prefix, ':', resource_class.local_name) AS val"
                );
        }

        if ($this->optionsCurrent['output'] !== 'values') {
            $qb
                ->addSelect('COUNT(resource.id) AS total');
        }

        $qb
            ->distinct()
            ->from('resource', 'resource')
            // The left join allows to get the total of items without resource
            // class.
            ->leftJoin('resource', 'resource_class', 'resource_class', $expr->eq('resource_class.id', 'resource.resource_class_id'))
            ->innerJoin('resource_class', 'vocabulary', 'vocabulary', $expr->eq('vocabulary.id', 'resource_class.vocabulary_id'))
            ->groupBy('val')
        ;

        return $this
            ->filterByResourceType($qb)
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
                    "UPPER(LEFT(resource_template.label, {$this->optionsCurrent['_initials']})) AS val"
                );
        } else {
            $qb
                ->select(
                    'resource_template.label AS val'
               );
        }

        if ($this->optionsCurrent['output'] !== 'values') {
            $qb
                ->addSelect('COUNT(resource.id) AS total');
        }

        $qb
            ->distinct()
            ->from('resource', 'resource')
            // The left join allows to get the total of items without resource
            // template.
            ->leftJoin('resource', 'resource_template', 'resource_template', $expr->eq('resource_template.id', 'resource.resource_template_id'))
            ->groupBy('val')
        ;

        return $this
            ->filterByResourceType($qb)
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
        $this->storeOptionCurrentResourceName();
        $this->optionsCurrent['resource_name'] = 'items';

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
                    "UPPER(LEFT(resource_item_set.title, {$this->optionsCurrent['_initials']})) AS val"
                );
        } else {
            $qb
                ->select(
                    'item_set.item_set_id AS val'
                );
        }

        if ($this->optionsCurrent['output'] !== 'values') {
            $qb
                ->addSelect('COUNT(resource.id) AS total');
        }

        $qb
            ->distinct()
            ->from('resource', 'resource')
            ->innerJoin('resource', 'item', 'item', $expr->eq('item.id', 'resource.id'))
            // The left join allows to get the total of items without item set.
            ->leftJoin('item', 'item_item_set', 'item_set', $expr->and($expr->eq('item_set.item_id', 'item.id'), $expr->neq('item_set.item_set_id', 0)))
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
            ->storeOptionCurrentResourceName(true)
            ->outputMetadata($qb, 'o:item_set');
    }

    /**
     * Get the list of owners, the total for each one and the first item.
     *
     * @return array Associative list of references, with the total, the first
     * record, and the first character, according to the parameters.
     */
    protected function listOwners(): array
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
                    "UPPER(LEFT(user.name, {$this->optionsCurrent['_initials']})) AS val"
                );
        } else {
            $qb
                ->select(
                    'user.name AS val'
                );
        }

        if ($this->optionsCurrent['output'] !== 'values') {
            $qb
                ->addSelect('COUNT(resource.id) AS total');
        }

        $qb
            ->distinct()
            ->from('resource', 'resource')
            ->leftJoin('resource', 'user', 'user', $expr->eq('user.id', 'resource.owner_id'))
            ->groupBy('val')
        ;

        return $this
            ->filterByResourceType($qb)
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
    protected function listSites(): array
    {
        $qb = $this->connection->createQueryBuilder();
        $expr = $qb->expr();

        // Count the number of items by site.

        // TODO Get all sites, even without items (or private items).

        if ($this->process === 'initials') {
            $qb
                ->select(
                    "UPPER(LEFT(site.title, {$this->optionsCurrent['_initials']})) AS val"
                );
        } else {
            $qb
                ->select(
                    'site.slug AS val'
                );
        }

        if ($this->optionsCurrent['output'] !== 'values') {
            $qb
                ->addSelect('COUNT(resource.id) AS total');
        }

        $qb
            ->distinct()
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
    protected function listAccesses(): array
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
                    "UPPER(LEFT(access_status.level, {$this->optionsCurrent['_initials']})) AS val"
                );
        } else {
            $qb
                ->select(
                    'access_status.level AS val'
                );
        }

        if ($this->optionsCurrent['output'] !== 'values') {
            $qb
                ->addSelect('COUNT(resource.id) AS total');
        }

        $qb
            ->distinct()
            ->from('resource', 'resource')
            ->innerJoin('resource', 'access_status', 'access_status', $expr->eq('access_status.id', 'resource.id'))
            ->groupBy('val')
        ;

        return $this
            ->filterByResourceType($qb)
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

    protected function filterByResourceType(QueryBuilder $qb): self
    {
        $table = $this->getTableDerivateResource($this->optionsCurrent['resource_name']);
        if ($table) {
            /*
            // A join is quicker, because column resource.resource_type is not
            // indexed by default until module Common version 3.4.62.
            // Anyway, even if implementations are the same, join is more readable.
            $qb
                ->andWhere($qb->expr()->eq('resource.resource_type', ':entity_class'))
                ->setParameter('entity_class', $this->easyMeta->entityClass($this->optionsCurrent['resource_name']), ParameterType::STRING);
            }
            */
            $qb
                ->innerJoin('resource', $table, 'rs', $qb->expr()->eq('rs.id', 'resource.id'));
        }
        return $this;
    }

    protected function filterByVisibility(QueryBuilder $qb, ?string $type): self
    {
        if ($this->acl->userIsAllowed(\Omeka\Entity\Resource::class, 'view-all')) {
            return $this;
        }
        return $this->user
            ? $this->filterByVisibilityForUser($qb, $type)
            : $this->filterByVisibilityForAnonymous($qb, $type);
    }

    protected function filterByVisibilityForAnonymous(QueryBuilder $qb, ?string $type): self
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

    protected function filterByVisibilityForUser(QueryBuilder $qb, ?string $type): self
    {
        /**
         * @see \Omeka\Db\Filter\ResourceVisibilityFilter
         * @see \Omeka\Db\Filter\ValueVisibilityFilter
         */
        $expr = $qb->expr();
        switch ($type) {
            case 'o:item_set':
                $qb
                    ->andWhere($expr->or(
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
                    ->andWhere($expr->or(
                        'resource.is_public = 1',
                        'resource.owner_id = :user_id'
                    ))
                    ->setParameter('user_id', (int) $this->user->getId(), ParameterType::INTEGER)
                ;
                break;
            case 'properties':
            case 'o:property':
                $qb
                    ->andWhere($expr->or(
                        'resource.is_public = 1',
                        'resource.owner_id = :user_id'
                    ))
                    ->andWhere($expr->or(
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
        if ($this->optionsCurrent['filters']['main_types'] && $this->optionsCurrent['filters']['main_types'] !== ['value', 'resource', 'uri']) {
            $expr = $qb->expr();
            if ($this->optionsCurrent['filters']['main_types'] === ['value']) {
                $qb->andWhere($expr->isNotNull('value.value'));
            } elseif ($this->optionsCurrent['filters']['main_types'] === ['uri']) {
                $qb->andWhere($expr->isNotNull('value.uri'));
            } elseif ($this->optionsCurrent['filters']['main_types'] === ['resource']) {
                $qb->andWhere($expr->isNotNull('value.value_resource_id'));
            } elseif ($this->optionsCurrent['filters']['main_types'] === ['value', 'uri']) {
                $qb->andWhere($expr->or($expr->isNotNull('value.value'), $expr->isNotNull('value.uri')));
            } elseif ($this->optionsCurrent['filters']['main_types'] === ['value', 'resource']) {
                $qb->andWhere($expr->or($expr->isNotNull('value.value'), $expr->isNotNull('value.value_resource_id')));
            } elseif ($this->optionsCurrent['filters']['main_types'] === ['uri', 'resource']) {
                $qb->andWhere($expr->or($expr->isNotNull('value.uri'), $expr->isNotNull('value.value_resource_id')));
            }
        }
        return $this;
    }

    protected function filterByDataType(QueryBuilder $qb): self
    {
        if ($this->optionsCurrent['filters']['data_types']) {
            $expr = $qb->expr();
            $qb
                ->andWhere($expr->in('value.type', ':data_types'))
                ->setParameter('data_types', $this->optionsCurrent['filters']['data_types'], Connection::PARAM_STR_ARRAY);
        }
        return $this;
    }

    protected function filterByLanguage(QueryBuilder $qb): self
    {
        if ($this->optionsCurrent['filters']['languages']) {
            $expr = $qb->expr();
            // Note: For an unknown reason, doctrine may crash with "IS NULL" in
            // some non-reproductible cases. Db version related?
            $hasEmptyLanguage = in_array('', $this->optionsCurrent['filters']['languages']);
            $in = $expr->in('value.lang', ':languages');
            $filter = $hasEmptyLanguage ? $expr->or($in, $expr->isNull('value.lang')) : $in;
            $qb
                ->andWhere($filter)
                ->setParameter('languages', $this->optionsCurrent['filters']['languages'], Connection::PARAM_STR_ARRAY);
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
            if ($this->optionsCurrent['filters'][$filter]) {
                if ($filter === 'begin') {
                    $filterB = '';
                    $filterE = '%';
                } else {
                    $filterB = '%';
                    $filterE = '';
                }

                // Use "or like" in most of the cases, else a regex (slower).
                // TODO Add more checks and a php unit.
                if (count($this->optionsCurrent['filters'][$filter]) === 1) {
                    $firstFilter = reset($this->optionsCurrent['filters'][$filter]);
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
                } elseif (count($this->optionsCurrent['filters'][$filter]) <= 20) {
                    $orX = [];
                    foreach (array_values($this->optionsCurrent['filters'][$filter]) as $key => $string) {
                        $orX[] = $expr->like($column, sprintf(':filter_%s_%d)', $filter, ++$key));
                        $qb
                            ->setParameter(
                                "filter_{$filter}_$key",
                                $filterB . str_replace(['%', '_'], ['\%', '\_'], $string) . $filterE,
                                ParameterType::STRING
                            );
                    }
                    $qb
                        ->andWhere($expr->or(...$orX));
                } else {
                    $regexp = implode('|', array_map('preg_quote', $this->optionsCurrent['filters'][$filter]));
                    $qb
                        ->andWhere("REGEXP($column, :filter_filter) = true")
                        ->setParameter("filter_$filter", $regexp, ParameterType::STRING);
                }
            }
        }
        return $this;
    }

    protected function manageOptions(QueryBuilder $qb, ?string $type, array $args = []): self
    {
        $expr = $qb->expr();
        if (in_array($type, ['resource_classes', 'resource_templates', 'item_sets', 'resource_titles'])
            && $this->optionsCurrent['initial']
        ) {
            // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
            // "initial" is a reserved word from the version 8.0.27 of Mysql,
            // but doctrine renames all aliases before and after querying.
            $qb
                ->addSelect(
                    // 'CONVERT(UPPER(LEFT(value.value, 1)) USING latin1) AS initial',
                    $this->supportAnyValue
                        ? "ANY_VALUE(UPPER(LEFT(resource.title, {$this->optionsCurrent['initial']}))) AS initial"
                        : "UPPER(LEFT(resource.title, {$this->optionsCurrent['initial']})) AS initial"
                );
        }

        if ($type === 'access' && $this->optionsCurrent['initial']) {
            // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
            $qb
                ->addSelect(
                    // 'CONVERT(UPPER(LEFT(COALESCE(access_status.level, {$this->optionsCurrent['initial']}), 1)) USING latin1) AS initial',
                    $this->supportAnyValue
                        ? "ANY_VALUE(UPPER(LEFT(access_status.level, {$this->optionsCurrent['initial']}))) AS initial"
                        : "UPPER(LEFT(access_status.level, {$this->optionsCurrent['initial']})) AS initial"
                );
        }

        if ($type === 'properties' && $this->optionsCurrent['initial']) {
            // TODO Doctrine doesn't manage left() and convert(), but we may not need to convert.
            $qb
                ->addSelect(
                    // 'CONVERT(UPPER(LEFT(COALESCE(value.value, value.uri, value_resource.title), 1)) USING latin1) AS initial',
                    $this->supportAnyValue
                        ? "ANY_VALUE(UPPER(LEFT({$args['mainTypesString']}, {$this->optionsCurrent['initial']}))) AS initial"
                        : "UPPER(LEFT({$args['mainTypesString']}, {$this->optionsCurrent['initial']})) AS initial"
                );
        }

        if ($type === 'properties' && $this->optionsCurrent['distinct']) {
            $qb
                ->addSelect(
                    // TODO Warning with type "resource", that may be the same than "resource:item", etc.
                    'value_resource.id AS res',
                    'value.uri AS uri'
                )
                ->addGroupBy('res')
                ->addGroupBy('uri');
        }

        if ($type === 'properties' && $this->optionsCurrent['data_type']) {
            $qb
                ->addSelect(
                    $this->supportAnyValue
                        ? 'ANY_VALUE(value.type) AS type'
                        : 'value.type AS type'
                );
            // No need to group by type: it is already managed with group by distinct "val,res,uri".
        }

        if ($type === 'properties' && $this->optionsCurrent['lang']) {
            $qb
                ->addSelect(
                    $this->supportAnyValue
                        ? 'ANY_VALUE(value.lang) AS lang'
                        : 'value.lang AS lang'
                );
            if ($this->optionsCurrent['distinct']) {
                $qb
                    ->addGroupBy('lang');
            }
        }

        // Add the first resource id.
        if ($this->optionsCurrent['first']) {
            $qb
                ->addSelect(
                    'MIN(resource.id) AS first'
                );
        }

        if ($this->optionsCurrent['list_by_max']
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
            if ($this->optionsCurrent['locale'] && $type !== 'resource_titles') {
                $coalesce = [];
                foreach ($this->optionsCurrent['locale'] as $locale) {
                    $strLocale = str_replace('-', '_', $locale);
                    $coalesce[] = "ress_$strLocale.text";
                    $qb
                        // The join is different than in listDataForProperties().
                        ->leftJoin('value', 'reference_metadata', "ress_$strLocale", $expr->and(
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
                    ->leftJoin('value', 'reference_metadata', 'ress', $expr->and(
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

        if ($this->optionsCurrent['filters']['values']) {
            switch ($type) {
                case 'properties':
                case 'resource_classes':
                case 'resource_templates':
                    $qb
                        ->andWhere($expr->in('value.value', ':values'))
                        ->setParameter('values', $this->optionsCurrent['filters']['values'], Connection::PARAM_STR_ARRAY);
                    break;
                case 'resource_titles':
                    // TODO Nothing to filter for resource titles?
                    break;
                case 'o:property':
                    $values = $this->easyMeta->propertyIds($this->optionsCurrent['filters']['values']) ?: [0];
                    $qb
                        ->andWhere($expr->in('property.id', ':ids'))
                        ->setParameter('ids', $values, Connection::PARAM_INT_ARRAY);
                    break;
                case 'o:resource_class':
                    $values = $this->easyMeta->resourceClassIds($this->optionsCurrent['filters']['values']) ?: [0];
                    $qb
                        ->andWhere($expr->in('resource_class.id', ':ids'))
                        ->setParameter('ids', $values, Connection::PARAM_INT_ARRAY);
                    break;
                case 'o:resource_template':
                    $values = $this->easyMeta->resourceTemplateIds($this->optionsCurrent['filters']['values']) ?: [0];
                    $qb
                        ->andWhere($expr->in('resource_template.id', ':ids'))
                        ->setParameter('ids', $values, Connection::PARAM_INT_ARRAY);
                    break;
                case 'o:item_set':
                    $qb
                        ->andWhere($expr->in('item_set.id', ':ids'))
                        ->setParameter('ids', array_map('intval', $this->optionsCurrent['filters']['values']), Connection::PARAM_INT_ARRAY);
                    break;
                case 'o:owner':
                    $qb
                        ->andWhere($expr->in('user.id', ':ids'))
                        ->setParameter('ids', array_map('intval', $this->optionsCurrent['filters']['values']), Connection::PARAM_INT_ARRAY);
                    break;
                case 'o:site':
                    $qb
                        ->andWhere($expr->in('site.id', ':ids'))
                        ->setParameter('ids', array_map('intval', $this->optionsCurrent['filters']['values']), Connection::PARAM_INT_ARRAY);
                    break;
                case 'access':
                    $qb
                        ->andWhere($expr->in('access_status.level', ':values'))
                        ->setParameter('values', $this->optionsCurrent['filters']['values'], Connection::PARAM_STR_ARRAY);
                    break;
                default:
                    break;
            }
        }

        $this->searchQuery($qb, $type);

        // Don't add useless order by resource id, since value are unique.
        // Furthermore, it may break mySql 5.7.5 and later, where ONLY_FULL_GROUP_BY
        // is set by default and requires to be grouped.

        // Add alphabetic order (val asc) for ergonomy when total is the same.

        $sortBy = $this->optionsCurrent['sort_by'];
        $sortOrder = $this->optionsCurrent['sort_order'];

        switch ($sortBy) {
            case 'alphabetic':
                // Item sets are output by id, so the title is required.
                if ($type === 'o:item_set') {
                    $qb
                        ->orderBy('resource_item_set.title', $sortOrder);
                } else {
                    $qb
                        ->orderBy('val', $sortOrder);
                }
                break;
            case 'total':
                $qb
                    ->orderBy('total', $sortOrder)
                    ->addOrderBy('val', 'ASC');
                break;
            case 'values':
                // Values are already checked in options.
                // To order by field requires the package beberlei/doctrineextensions,
                // that is provided by omeka, but that may not be available by
                // other databases (sqlite for test).
                $qb
                    ->orderBy('FIELD(val, :order_values)', $sortOrder)
                    ->setParameter(':order_values', $this->optionsCurrent['filters']['values'], Connection::PARAM_STR_ARRAY)
                    ->addOrderBy('val', 'ASC');
                break;
            default:
                // Any available column.
                $qb
                    ->orderBy($sortBy, $sortOrder)
                    ->orderBy('val', 'ASC');
                break;
        }

        if ($this->optionsCurrent['per_page']) {
            $qb->setMaxResults($this->optionsCurrent['per_page']);
            if ($this->optionsCurrent['page'] > 1) {
                $offset = ($this->optionsCurrent['page'] - 1) * $this->optionsCurrent['per_page'];
                $qb->setFirstResult($offset);
            }
        }

        return $this;
    }

    protected function storeOptionCurrentResourceName(bool $restore = false): self
    {
        static $rn;

        if ($restore) {
            $this->optionsCurrent['resource_name'] = $rn;
        } else {
            $rn = $this->optionsCurrent['resource_name'];
        }

        return $this;
    }

    protected function outputMetadata(QueryBuilder $qb, ?string $type): array
    {
        if ($this->optionsCurrent['output'] === 'values') {
            return $qb->execute()->fetchFirstColumn() ?: [];
        }

        $result = $qb->execute()->fetchAllAssociative();

        if (!count($result)) {
            return [];
        }

        if ($this->optionsCurrent['output'] === 'associative') {
            // Array column cannot be used in one step, because the null value
            // (no title) should be converted to "", not to "0".
            // $result = array_column($result, 'total', 'val');
            $result = array_combine(
                array_column($result, 'val'),
                array_column($result, 'total')
            );

            if (!$this->optionsCurrent['include_without_meta']) {
                unset($result['']);
            }

            return array_map('intval', $result);
        }

        $first = reset($result);
        if ($this->optionsCurrent['initial']) {
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
            $listByMax = $this->optionsCurrent['list_by_max'];
            $explodeResources = function (array $result) use ($listByMax): array {
                return array_map(function ($v) use ($listByMax) {
                    $list = explode(chr(0x1D), (string) $v['resources']);
                    $list = array_map(fn ($vv) => explode(chr(0x1F), $vv, 2), $listByMax ? array_slice($list, 0, $listByMax) : $list);
                    $v['resources'] = array_column($list, 1, 0);
                    return $v;
                }, $result);
            };
            $result = $explodeResources($result);

            if ($this->optionsCurrent['fields']) {
                $fields = array_fill_keys($this->optionsCurrent['fields'], true);
                // FIXME Fix the api call inside a loop. Use the new table reference_metadata.
                $result = array_map(function ($v) use ($fields) {
                    // Check required when a locale is used or for debug.
                    if (empty($v['resources'])) {
                        return $v;
                    }
                    // Search resources is not available.
                    if ($this->optionsCurrent['resource_name'] === 'resource') {
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
                        $resources = $this->api->search($this->optionsCurrent['resource_name'], ['id' => array_keys($v['resources']), 'sort_by' => 'title', 'sort_order' => 'asc'])->getContent();
                        $v['resources'] = array_map(fn ($r) => array_intersect_key($r->jsonSerialize(), $fields), $resources);
                    }
                    return $v;
                }, $result);
            }
        }

        if ($this->optionsCurrent['include_without_meta']) {
            return $result;
        }

        // Remove all empty values ("val").
        // But do not remove a uri or a resource without label.
        if (count($first) <= 2 || !array_key_exists('type', $first)) {
            $result = array_combine(array_column($result, 'val'), $result);
            unset($result['']);
            return array_values($result);
        }

        return array_filter($result, function ($v): array {
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
            ->distinct()
            ->from('reference_metadata', 'refmeta')
            ->innerJoin('refmeta', 'resource', 'resource', $expr->eq('resource.id', 'refmeta.resource_id'))
            ->andWhere($expr->in('refmeta.field', ':properties'))
            ->setParameter('properties', $this->easyMeta->propertyTerms($propertyIds), Connection::PARAM_STR_ARRAY)
        ;

        $this
            ->filterByResourceType($qb)
            ->searchQuery($qb);

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
            ->distinct()
            ->from('resource', 'resource')
            ->andWhere($expr->in('resource.resource_class_id', ':resource_classes'))
            ->setParameter('resource_classes', array_map('intval', $resourceClassIds), Connection::PARAM_INT_ARRAY);

        $this
            ->filterByResourceType($qb)
            ->searchQuery($qb);

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
            ->distinct()
            ->from('resource', 'resource')
            ->andWhere($expr->in('resource.resource_template_id', ':resource_templates'))
            ->setParameter('resource_templates', array_map('intval', $resourceTemplateIds), Connection::PARAM_INT_ARRAY);

        $this
            ->filterByResourceType($qb)
            ->searchQuery($qb);

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

        if (!in_array($this->optionsCurrent['resource_name'], ['items', 'resources'])) {
            return 0;
        }

        $qb
            ->select(
                'COUNT(resource.id)'
            )
            ->distinct()
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
     *
     * @fixme The method searchQuery() is working fine, but has a performance issue with big database to get the list of all ids. But in that case, Solr is recommended, so no facet to process.
     */
    protected function searchQuery(QueryBuilder $qb, ?string $type = null): self
    {
        // When facets are searched, the same query can be used multiple times,
        // so store results. It may improve performance with big bases when
        // using a query with "contains" (in), so sql "like". Nevertheless, it
        // is useful only when parameters are the same, that is not frequent.
        // The dql is not available with dbal connection query builder.
        static $sqlToIds = [];

        if (!count($this->query)) {
            return $this;
        }

        $sql = $qb->getSQL();
        $params = $qb->getParameters();
        $key = serialize([$sql, $params]);
        if (!isset($sqlToIds[$key])) {
            $mainQuery = $this->query;

            // When searching by item set or site, remove the matching query
            // filter, else there won't be any results.
            // TODO Check if item sets and sites are still an exception for references.
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

            $ids = $this->api->search($this->optionsCurrent['resource_name'], $mainQuery, ['returnScalar' => 'id'])->getContent();

            $sqlToIds[$key] = array_keys($ids);
        }

        // There is no collision: the adapter query uses alias "omeka_" + index.
        $qb
            ->andWhere($qb->expr()->in('resource.id', ':resource_ids'))
            ->setParameter('resource_ids', $sqlToIds[$key], Connection::PARAM_INT_ARRAY);

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

        $labelRequested = empty($keyOrLabelRequest) || is_numeric($keyOrLabelRequest)
            ? null
            : $keyOrLabelRequest;

        // Special fields.
        if (isset($metaToTypes[$field])) {
            $labels = [
                'o:property' => $this->translate->__invoke('Properties'), // @translate
                'o:resource_class' => $this->translate->__invoke('Classes'), // @translate
                'o:resource_template' => $this->translate->__invoke('Templates'), // @translate
                'o:item_set' => $this->translate->__invoke('Item sets'), // @translate
                'o:owner' => $this->translate->__invoke('Owners'), // @translate
                'o:site' => $this->translate->__invoke('Sites'), // @translate
                'access' => $this->translate->__invoke('Access'), // @translate
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
                    'o:label' => $labelRequested ?? $this->translate->__invoke('[Unknown]'), // @translate
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
            $label = $this->translate->__invoke('Title'); // @translate
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
            $labelFirst = $this->translate->__invoke(reset($meta)['o:label']);
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
            $labelFirst = $this->translate->__invoke(reset($meta)['o:label']);
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
            $labelFirst = $this->translate->__invoke(reset($meta)['o:label']);
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
            }
            unset($metaElement);
            $labelFirst = $this->translate->__invoke(reset($meta)['o:label']);
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
            'annotations' => 'annotation',
        ];
        return $resourceTableMap[$resourceName] ?? 'resource';
    }

    /**
     * Get properties by JSON-LD terms or by numeric ids.
     *
     * The property contains the type, the term, the label and the id.
     */
    protected function getProperties(array $termsOrIds): array
    {
        $propertyIds = $this->easyMeta->propertyIds($termsOrIds);
        $propertyTerms = $this->easyMeta->propertyTerms($termsOrIds);
        $propertyLabels = $this->easyMeta->propertyLabels($termsOrIds);
        $result = [];
        foreach ($propertyIds as $termOrId => $id) {
            $result[] = [
                '@type' => 'o:Property',
                'o:term' => $propertyTerms[$termOrId],
                'o:label' => $propertyLabels[$termOrId],
                'o:id' => $id,
            ];
        }
        return $result;
    }

    /**
     * Get resource class by JSON-LD term or by numeric id.
     *
     * The property contains the term, the label, the id and an empty language.
     */
    protected function getProperty($termOrId): ?array
    {
        $propertyId = $this->easyMeta->propertyId($termOrId);
        return $propertyId
            ? [
                'o:term' => $this->easyMeta->propertyTerm($propertyId),
                'o:label' => $this->easyMeta->propertyLabel($propertyId),
                'o:id' => $propertyId,
                '@language' => null,
            ]
            : null;
    }

    /**
     * Get resource classes by JSON-LD terms or by numeric ids.
     *
     * The class contains the type, the term, the label and the id.
     */
    protected function getResourceClasses(array $termsOrIds): array
    {
        $classIds = $this->easyMeta->resourceClassIds($termsOrIds);
        $classTerms = $this->easyMeta->resourceClassTerms($termsOrIds);
        $classLabels = $this->easyMeta->resourceClassLabels($termsOrIds);
        $result = [];
        foreach ($classIds as $termOrId => $id) {
            $result[] = [
                '@type' => 'o:ResourceClass',
                'o:term' => $classTerms[$termOrId],
                'o:label' => $classLabels[$termOrId],
                'o:id' => $id,
            ];
        }
        return $result;
    }

    /**
     * Get resource class by JSON-LD term or by numeric id.
     *
     * The class contains the term, the label, the id and an empty language.
     */
    protected function getResourceClass($termOrId): ?array
    {
        $classId = $this->easyMeta->resourceClassId($termOrId);
        return $classId
            ? [
                'o:term' => $this->easyMeta->resourceClassTerm($classId),
                'o:label' => $this->easyMeta->resourceClassLabel($classId),
                'o:id' => $classId,
                '@language' => null,
            ]
            : null;
    }

    /**
     * Get resource template ids by labels or by numeric ids.
     *
     * The template contains the type, the label and the id.
     */
    protected function getResourceTemplates(array $labelsOrIds): array
    {
        $templateIds = $this->easyMeta->resourceTemplateIds($labelsOrIds);
        $templateLabels = $this->easyMeta->resourceTemplateLabels($labelsOrIds);
        $result = [];
        foreach ($templateIds as $labelOrId => $id) {
            $result[] = [
                '@type' => 'o:ResourceTemplate',
                'o:label' => $templateLabels[$labelOrId],
                'o:id' => $id,
            ];
        }
        return $result;
    }

    /**
     * Get resource template by label or by numeric id.
     *
     * The template contains the label, the id and an empty language.
     */
    protected function getResourceTemplate($labelOrId): ?array
    {
        $templateId = $this->easyMeta->resourceTemplateId($labelOrId);
        return $templateId
            ? [
                'o:label' => $this->easyMeta->resourceTemplateLabel($templateId),
                'o:id' => $templateId,
                '@language' => null,
            ]
            : null;
    }

    /**
     * Get item set ids by titles or by numeric ids.
     *
     * Warning, titles are not unique.
     *
     * The item set contains the type, the label, the id and an empty language.
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
     *
     * The item set contains the type, the id, the label, and an empty language.
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
            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select(
                    '"o:ItemSet" AS "@type"',
                    'resource.title AS "o:label"',
                    'resource.id AS "o:id"',
                    'NULL AS "@language"',
                    // Only the two first selects are needed, but some databases
                    // require "order by" or "group by" value to be in the select.
                    'resource.id'
                )
                ->distinct()
                ->from('resource', 'resource')
                ->innerJoin('resource', 'item_set', 'item_set', 'resource.id = item_set.id')
                // TODO Improve return of private item sets.
                ->where('resource.is_public', '1')
                ->orderBy('resource.id', 'asc')
                ->addGroupBy('resource.id')
            ;
            $results = $this->connection->executeQuery($qb)->fetchAllAssociative();
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
     *
     * The user contains the type, the label, the id and an empty language.
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
     *
     * Warning, user names are not unique.
     */
    protected function prepareOwners(): self
    {
        if (is_null($this->ownersByNameAndIds)) {
            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select(
                    '"o:User" AS "@type"',
                    'user.name AS "o:label"',
                    'user.id AS "o:id"',
                    'NULL AS "@language"'
                )
                ->distinct()
                ->from('user', 'user')
                ->innerJoin('user', 'resource', 'resource', 'resource.user_id = user.id')
                // TODO Improve return of private resource for owners.
                ->where('resource.is_public', '1')
                ->orderBy('user.id', 'asc')
                ->addGroupBy('user.id')
            ;
            $results = $this->connection->executeQuery($qb)->fetchAllAssociative();
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
     *
     * Warning, site title are not unique.
     *
     * The site contains the typethe label, the id, the slug and an empty language.
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
            $qb = $this->connection->createQueryBuilder();
            $qb
                ->select(
                    // Labels are not unique.
                    '"o:Site" AS "@type"',
                    'site.title AS "o:label"',
                    'site.id AS "o:id"',
                    'site.slug AS "o:slug"',
                    'NULL AS "@language"'
                )
                ->distinct()
                ->from('site', 'site')
                // TODO Improve return of private sites.
                ->where('site.is_public', '1')
                ->orderBy('site.id', 'asc')
                ->addGroupBy('site.id')
            ;
            $results = $this->connection->executeQuery($qb)->fetchAllAssociative();
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
     * Get the derivated table for a resource.
     */
    protected function getTableDerivateResource(?string $resourceName): ?string
    {
        // Other omeka api resources are not resources.
        $resourceNamesToTables = [
            'resources' => null,
            'items' => 'item',
            'media' => 'media',
            'item_sets' => 'item_set',
            'resource_classes' => 'resource_class',
            'resource_templates' => 'resource_template',
            'annotations' => 'annotation',
        ];
        return $resourceNamesToTables[$resourceName] ?? null;
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
        $patternIso8601 = '^(?<date>(?<year>-?\d{1,})(-(?<month>\d{1,2}))?(-(?<day>\d{1,2}))?)(?<time>((?:T| )(?<hour>\d{1,2}))?(:(?<minute>\d{1,2}))?(:(?<second>\d{1,2}))?)(?<offset>((?<offset_hour>[+-]\d{1,2})?(:?(?<offset_minute>\d{1,2}))?)|Z?)$';
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
     */
    protected function getLastDay($year, $month): int
    {
        $month = (int) $month;
        if (in_array($month, [4, 6, 9, 11], true)) {
            return 30;
        } elseif ($month === 2) {
            return date('L', mktime(0, 0, 0, 1, 1, $year)) ? 29 : 28;
        } else {
            return 31;
        }
    }
}
