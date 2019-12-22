<?php
namespace Reference\Form;

use Omeka\Api\Manager as ApiManager;
use Omeka\Form\Element\PropertySelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\I18n\Translator\TranslatorAwareTrait;

class ConfigForm extends Form implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    /**
     * @var ApiManager
     */
    protected $api;

    public function init()
    {
        // TODO Move most of these options to site level (and admin settings).

        $this->add([
            'type' => Fieldset::class,
            'name' => 'fieldset_reference_general',
            'options' => [
                'label' => 'General options', // @translate
            ],
        ]);
        $generalFieldset = $this->get('fieldset_reference_general');

        $generalFieldset->add([
            'name' => 'reference_resource_name',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Resources to link', // @translate
                'info' => 'Currently, only item sets and items are managed in public front-end.', // @translate
                'value_options' => [
                    // TODO Manage the list of reference separately.
                    // '' => 'All resources (separately)', // @translate
                    // 'resources' => 'All resources (together)',  // @translate
                    'item_sets' => 'Item sets',  // @translate
                    'items' => 'Items',  // @translate
                    // 'media' => 'Media',  // @translate
                ],
            ],
            'attributes' => [
                'required' => true,
            ],
        ]);

        $generalFieldset->add([
            'name' => 'reference_link_to_single',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Link to single records', // @translate
                'info' => 'When a reference has only one item, link to it directly instead of to the items/browse page.', // @translate
            ],
        ]);

        $generalFieldset->add([
            'name' => 'reference_custom_url',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Custom url for single', // @translate
                'info' => 'May be set with modules such Clean Url or Ark. May slow the display when there are many single references.', // @translate
            ],
        ]);

        $generalFieldset->add([
            'name' => 'reference_total',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Print total', // @translate
                'info' => 'Print the total of resources for each reference.', // @translate
            ],
        ]);

        $generalFieldset->add([
            'name' => 'reference_search_list_values',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'List values in advanced search', // @translate
                'info' => 'Dynamically list all available properties in the advanced search public form.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'fieldset_reference_list_params',
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Reference indexes options', // @translate
            ],
        ]);
        $referenceParamsFieldset = $this->get('fieldset_reference_list_params');

        $referenceParamsFieldset->add([
            'name' => 'reference_list_skiplinks',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Print skip links', // @translate
                'info' => $this->translate('Print skip links at the top and bottom of each page, which link to the alphabetical headers.') // @translate
                    . ' ' . $this->translate('Note that if headers are turned off, skiplinks do not work.'), // @translate
            ],
        ]);

        $referenceParamsFieldset->add([
            'name' => 'reference_list_headings',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Print headings', // @translate
                'info' => 'Print headers for each section (#0-9 and symbols, A, B, etc.).', // @translate
            ],
        ]);

        $this->add([
            'type' => Fieldset::class,
            'name' => 'fieldset_reference_tree',
            'options' => [
                'label' => 'Reference tree', // @translate
            ],
        ]);
        $referenceTreeFieldset = $this->get('fieldset_reference_tree');

        $referenceTreeFieldset->add([
            'name' => 'reference_tree_enabled',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Enable tree view', // @translate
                'info' => 'Enable the page and display the link "/subject/tree" to the hierarchical view in the navigation bar.', // @translate
            ],
        ]);

        $referenceTreeFieldset->add([
            'name' => 'reference_tree_term',
            'type' => PropertySelect::class,
            'options' => [
                'label' => 'Property for the tree', // @translate
                'info' => 'The references will use this property to create links.', // @translate
                'empty_option' => 'Select a property…', // @translate
                'term_as_value' => true,
            ],
            'attributes' => [
                'required' => false,
                'class' => 'chosen-select',
            ],
        ]);

        $referenceTreeFieldset->add([
            'name' => 'reference_tree_hierarchy',
            'type' => Element\Textarea::class,
            'options' => [
                'label' => 'Static tree of references', // @translate
                'info' => $this->translate('If any, write the hierarchy of all your references in order to display them in the "Tree of references" page.') // @translate
                    . ' ' . $this->translate('Format is: one reference by line, preceded by zero, one or more "-" to indicate the hierarchy level.') // @translate
                    . ' ' . $this->translate('Separate the "-" and the reference with a space. Empty lines are not considered.') // @translate
                    . ' ' . $this->translate('Note: sql does case insensitive searches, so all references should be case-insensitively unique.'), // @translate
            ],
            'attributes' => [
                'rows' => 20,
                'cols' => 60,
                // The place holder may not use end of line on some browsers, so
                // a symbol is used for it.
                'placeholder' => 'Europe ↵
- France ↵
-- Paris ↵
- United Kingdom ↵
-- England ↵
--- London ↵
Asia ↵
- Japan ↵
',
            ],
        ]);

        $referenceTreeFieldset->add([
            'name' => 'reference_tree_branch',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Managed as branch', // @translate
                'info' => 'Check this box if the tree is managed as branch (the path is saved with " :: " between each branch).', // @translate
            ],
        ]);

        $referenceTreeFieldset->add([
            'name' => 'reference_tree_query_type',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Query type', // @translate
                'info' => 'The type of query defines how elements are searched.', // @translate
                'value_options' => [
                    'eq' => 'Is Exactly', // @translate
                    'in' => 'Contains', // @translate
                ],
            ],
        ]);

        $referenceTreeFieldset->add([
            'name' => 'reference_tree_expanded',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Expand tree', // @translate
                'info' => 'Check this box to display the tree expanded. This option can be overridden by the theme.', // @translate
            ],
        ]);

        $this->add([
            'name' => 'fieldset_reference_list_indexes',
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Reference indexes', // @translate
            ],
        ]);
        $referenceIndexesFieldset = $this->get('fieldset_reference_list_indexes');

        $types = [
            'resource_classes' => [
                'label' => 'Reference indexes: Resource classes', // @translate
                'dataId' => 'data-resource-class-id',
            ],
            'properties' => [
                'label' => 'Reference indexes: Properties', // @translate
                'dataId' => 'data-property-id',
            ],
        ];
        $typeFieldset = [];
        $typeVocabularyFieldset = [];
        $typeVocabularyMemberFieldset = [];
        foreach ($types as $type => $typeData) {
            $referenceIndexesFieldset->add([
                'name' => $type,
                'type' => Fieldset::class,
                'options' => [
                    'label' => $typeData['label'], // @translate
                ],
            ]);
            $typeFieldset[$type] = $referenceIndexesFieldset->get($type);

            $dataId = $typeData['dataId'];
            $list = $this->prepareList($type);
            foreach ($list as $vocabulary => $vocabularyData) {
                $typeFieldset[$type]->add([
                    'name' => $type . '[' . $vocabulary .']',
                    'type' => Fieldset::class,
                    'options' => [
                        'label' => $vocabularyData['label'], // @translate
                    ],
                ]);
                $typeVocabularyFieldset[$type][$vocabulary] = $typeFieldset[$type]->get($type . '[' . $vocabulary . ']');

                foreach ($vocabularyData['options'] as $member) {
                    $id = $member['attributes'][$dataId];
                    $typeVocabularyFieldset[$type][$vocabulary]->add([
                        'name' => $type . '[' . $id . ']',
                        'type' => Fieldset::class,
                        'options' => [
                            'label' => $member['attributes']['data-term'], // @translate
                        ],
                    ]);
                    $typeVocabularyMemberFieldset[$type][$vocabulary][$id] = $typeVocabularyFieldset[$type][$vocabulary]->get($type . '[' . $id . ']');
                    $typeVocabularyMemberFieldset[$type][$vocabulary][$id]->add([
                        'name' => $type . '[' . $id . '][active]',
                        'type' => Element\Checkbox::class,
                        'options' => [
                            'label' => 'Active', // @translate
                        ],
                    ]);
                    $typeVocabularyMemberFieldset[$type][$vocabulary][$id]->add([
                        'name' => $type . '[' . $id . '][slug]',
                        'type' => Element\Text::class,
                        'options' => [
                            'label' => 'Slug', // @translate
                        ],
                    ]);
                    $typeVocabularyMemberFieldset[$type][$vocabulary][$id]->add([
                        'name' => $type . '[' . $id . '][label]',
                        'type' => Element\Text::class,
                        'options' => [
                            'label' => 'Label', // @translate
                        ],
                    ]);
                }
            }
        }

        // Nothing is required.
        $inputFilter = $this->getInputFilter();
        foreach ($this as $element) {
            $inputFilter->add([
                'name' => $element->getName(),
                'required' => false,
            ]);
        }
    }

    /**
     * Prepare a list of entities.
     *
     * @see \Omeka\Form\Element\AbstractVocabularyMemberSelect::getValueOptions()
     *
     * @param string $resourceName
     */
    protected function prepareList($resourceName)
    {
        $termAsValue = true;

        $query = [];
        $query['sort_by'] = 'label';

        $valueOptions = [];
        $response = $this->getApiManager()->search($resourceName, $query);
        foreach ($response->getContent() as $member) {
            $attributes = ['data-term' => $member->term()];
            if ('properties' === $resourceName) {
                $attributes['data-property-id'] = $member->id();
            } elseif ('resource_classes' === $resourceName) {
                $attributes['data-resource-class-id'] = $member->id();
            }
            $option = [
                'label' => $member->label(),
                'value' => $termAsValue ? $member->term() : $member->id(),
                'attributes' => $attributes,
            ];
            $vocabulary = $member->vocabulary();
            if (!isset($valueOptions[$vocabulary->prefix()])) {
                $valueOptions[$vocabulary->prefix()] = [
                    'label' => $vocabulary->label(),
                    'options' => [],
                ];
            }
            $valueOptions[$vocabulary->prefix()]['options'][] = $option;
        }

        // Move Dublin Core vocabularies (dcterms & dctype) to the beginning.
        if (isset($valueOptions['dcterms'])) {
            $valueOptions = ['dcterms' => $valueOptions['dcterms']] + $valueOptions;
        }
        if (isset($valueOptions['dctype'])) {
            $valueOptions = ['dctype' => $valueOptions['dctype']] + $valueOptions;
        }

        return $valueOptions;
    }

    protected function translate($args)
    {
        $translator = $this->getTranslator();
        return $translator->translate($args);
    }

    public function setApiManager(ApiManager $api)
    {
        $this->api = $api;
    }

    public function getApiManager()
    {
        return $this->api;
    }
}
