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
     * @var \Omeka\Api\Representation\PropertyRepresentation[]
     */
    protected $properties;

    /**
     * @var \Omeka\Api\Representation\ResourceClassRepresentation[]
     */
    protected $resourceClasses;

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
     * @param \Omeka\Api\Representation\PropertyRepresentation[] $properties
     * @param \Omeka\Api\Representation\ResourceClassRepresentation[] $resourceClasses
     */
    public function __construct(
        Api $api,
        Reference $reference,
        Translate $translate,
        array $properties,
        array $resourceClasses
    ) {
        $this->api = $api;
        $this->reference = $reference;
        $this->translate = $translate;
        $this->properties = $properties;
        $this->resourceClasses = $resourceClasses;
    }

    /**
     * Get the references.
     *
     * @param array $metadata Classes, properties terms or Omeka metadata names.
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
        if (empty($fields)) {
            return [];
        }

        $query = $this->getQuery();
        $options = $this->getOptions();

        $api = $this->api;
        $reference = $this->reference;
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
                case 'resource_classes':
                    $values = $reference($field['term'], $field['type'], $options['resource_name'], [$options['sort_by'] => $options['sort_order']], $query, $options['per_page'], $options['page']);
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
                    $values = $reference('', $field['metatype'], $options['resource_name'], [$options['sort_by'] => $options['sort_order']], $query, $options['per_page'], $options['page']);
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
                    $values = $reference('', $field['metatype'], $options['resource_name'], [$options['sort_by'] => $options['sort_order']], $query, $options['per_page'], $options['page']);
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
                    $values = $reference('', $field['metatype'], $options['resource_name'], [$options['sort_by'] => $options['sort_order']], $query, $options['per_page'], $options['page']);
                    foreach (array_filter($values) as $value => $count) {
                        $meta = $api->searchOne('resource_templates', ['label' => $value])->getContent();
                        $result[$field['term']]['o-module-reference:values'][] = [
                            'o:id' => $meta->id(),
                            'o:label' => $meta->label(),
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
                        $values = $reference('', $field['metatype'], $options['resource_name'], [$options['sort_by'] => $options['sort_order']], $query, $options['per_page'], $options['page']);
                    }
                    foreach (array_filter($values) as $value => $count) {
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

    protected function prepareField($field)
    {
        static $labels;

        $metaToTypes = [
            // Item sets is only for items.
            'o:item_set' => 'item_sets',
            'o:resource_class' => 'resource_classes',
            'o:resource_template' => 'resource_templates',
            'o:property' => 'properties',
        ];

        if (isset($metaToTypes[$field])) {
            if (is_null($labels)) {
                $translate = $this->translate;
                $labels = [
                    'o:item_set' => $translate('Item sets'), // @translate
                    'o:resource_class' => $translate('Classes'), // @translate
                    'o:resource_template' => $translate('Templates'), // @translate
                    'o:property' => $translate('Properties'), // @translate
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

        return [
            'type' => null,
            'term' => $field,
            'label' => $field,
        ];
    }
}
