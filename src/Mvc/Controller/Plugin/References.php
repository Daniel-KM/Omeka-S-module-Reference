<?php
namespace Reference\Mvc\Controller\Plugin;

use Omeka\Mvc\Controller\Plugin\Api;
use Omeka\Mvc\Controller\Plugin\Translate;
// use Reference\Mvc\Controller\Plugin\Reference;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;

class References extends AbstractPlugin
{
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
     * @param Api $api
     * @param Reference $reference
     * @param Translate $translate
     */
    public function __construct(
        Api $api,
        Reference $reference,
        Translate $translate
    ) {
        $this->api = $api;
        $this->reference = $reference;
        $this->translate = $translate;
    }

    /**
     * Get the references.
     *
     * @param array $metadata Classes, properties, or Omeka metadata names.
     * @param array $query An Omeka search query.
     * @param array $options Options for output.
     * @return self|array|null The result or null if called directly, else self.
     */
    public function __invoke(
        array $metadata = null,
        array $query = null,
        array $options = null
    ) {
        $this
            ->setMetadata($metadata)
            ->setQuery($query)
            ->setOptions($options);

        return is_null($metadata) && is_null($query)
            ? $this
            : $this->list();
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
            'per_page' => 25,
            'page' => 1,
            'sort_by' => 'count',
            'sort_order' => 'DESC',
        ];
        if ($options) {
            $defaults = [
                'resource_name' => in_array(@$options['resource_name'], ['items', 'item_sets', 'media', 'resources']) ? $options['resource_name'] : $defaults['resource_name'],
                'per_page' => @$options['per_page'] ?: $defaults['per_page'],
                'page' => @$options['page'] ?: $defaults['page'],
                'sort_by' => strtolower(@$options['sort_by']) === 'alphabetic' ? 'alphabetic' : 'count',
                'sort_order' => strtolower(@$options['sort_order']) === 'asc' ? 'ASC' : 'DESC',
            ];
            $this->options = array_filter($options) + $defaults;
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
        $query = $this->getQuery();

        // Either metadata or query is required.
        if (empty($fields) && empty($query)) {
            return [];
        }

        $options = $this->getOptions();

        $result = [];

        $api = $this->api;
        $reference = $this->reference;
        $translate = $this->translate;

        // All except properties.
        $omekaFieldsToTypes = [
            // Item sets is only for items.
            'o:item_set' => 'item_sets',
            'o:resource_class' => 'resource_classes',
            'o:resource_template' => 'resource_templates',
            'o:property' => 'properties',
        ];

        if (array_intersect($fields, array_keys($omekaFieldsToTypes))) {
            $labels = [
                'o:item_set' => $translate('Item sets'), // @translate
                'o:resource_class' => $translate('Classes'), // @translate
                'o:resource_template' => $translate('Templates'), // @translate
                'o:property' => $translate('Properties'), // @translate
            ];
        }

        // TODO Convert all queries into a single or two sql queries.

        foreach ($fields as $field) {
            // For metadata other than properties.
            if (isset($omekaFieldsToTypes[$field])) {
                $type = $omekaFieldsToTypes[$field];

                // Manage an exception for the resource "items" exception.
                if ($type === 'item_sets' && $options['resource_name'] !== 'items') {
                    $values = [];
                } else {
                    $values = $reference('', $type, $options['resource_name'], [$options['sort_by'] => $options['sort_order']], $query, $options['per_page'], $options['page']);
                }

                $result[$field] = [
                    'o:label' => @$labels[$field],
                    'o-module-reference:values' => [],
                ];
                switch ($type) {
                    // Only for items.
                    case 'item_sets':
                        foreach (array_filter($values) as $value => $count) {
                            $label = $api->read($type, ['id' => $value])->getContent()->displayTitle();
                            $result[$field]['o-module-reference:values'][] = [
                                'o:id' => $value,
                                'o:label' => $label,
                                '@language' => null,
                                'count' => $count,
                            ];
                        }
                        break;
                    case 'resource_classes':
                        foreach (array_filter($values) as $value => $count) {
                            $label = $api->searchOne($type, ['term' => $value])->getContent()->label();
                            $result[$field]['o-module-reference:values'][] = [
                                'o:term' => $value,
                                'o:label' => $label,
                                '@language' => null,
                                'count' => $count,
                            ];
                        }
                        break;
                    case 'resource_templates':
                        foreach (array_filter($values) as $value => $count) {
                            $id = $api->searchOne($type, ['label' => $value])->getContent()->id();
                            $result[$field]['o-module-reference:values'][] = [
                                'o:id' => $id,
                                'o:label' => $value,
                                '@language' => null,
                                'count' => $count,
                            ];
                        }
                        break;
                    case 'properties':
                        foreach (array_filter($values) as $value => $count) {
                            $property = $api->searchOne('properties', ['term' => $value])->getContent();
                            $result[$field]['o-module-reference:values'][] = [
                                'o:id' => $property->id(),
                                'o:term' => $value,
                                'o:label' => $property->label(),
                                '@language' => null,
                                'count' => $count,
                            ];
                        }
                    default:
                        break;
                }
            }
            // For any properties.
            else {
                /** @var \Omeka\Api\Representation\PropertyRepresentation $property */
                $property = $api->searchOne('properties', ['term' => $field])->getContent();
                // When field is unknown, Omeka may return dcterms:title.
                if (empty($property) || $property->term() !== $field) {
                    $result[$field] = [
                        'o:label' => $field, // @translate
                        'o-module-reference:values' => [],
                    ];
                } else {
                    $values = $reference($field, 'properties', $options['resource_name'], [$options['sort_by'] => $options['sort_order']], $query, $options['per_page'], $options['page']);
                    $result[$field] = [
                        'o:id' => $property->id(),
                        'o:term' => $field,
                        'o:label' => $property->label(),
                        'o-module-reference:values' => [],
                    ];
                    foreach (array_filter($values) as $value => $count) {
                        $result[$field]['o-module-reference:values'][] = [
                            'o:label' => $value,
                            '@language' => null,
                            'count' => $count,
                        ];
                    }
                }
            }
        }

        // Keep original order of fields.
        // TODO Add a key for field "".
        if (!array_filter($fields)) {
            $result = array_replace(array_fill_keys($fields, []), $result);
        }

        return $result;
    }
}
