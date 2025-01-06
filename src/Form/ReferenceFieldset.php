<?php declare(strict_types=1);

namespace Reference\Form;

use Common\Form\Element as CommonElement;
use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Omeka\Form\Element as OmekaElement;

class ReferenceFieldset extends Fieldset
{
    /**
     * List of search configs  when module Advanced Search is used.
     *
     * @var array
     */
    protected $searchConfigs = [];

    public function init(): void
    {
        // Args and options cannot use sub-fieldsets for compatibility with
        // group block plus.

        // Args.

        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][properties]',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'label' => 'Properties', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'reference-args-properties',
                    'required' => false,
                    'class' => 'chosen-select',
                    'multiple' => 'multiple',
                    'data-placeholder' => 'Select properties…', // @translate
                    'data-fieldset' => 'args',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][resource_classes]',
                'type' => OmekaElement\ResourceClassSelect::class,
                'options' => [
                    'label' => 'Resource classes', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'reference-args-resource-classes',
                    'required' => false,
                    'class' => 'chosen-select',
                    'multiple' => 'multiple',
                    'data-placeholder' => 'Select resource classes…', // @translate
                    'data-fieldset' => 'args',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][resource_name]',
                // TODO Radio doesn't work when there are multiple blocks.
                // 'type' => Element\Radio::class,
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Select resource', // @translate
                    'info' => 'Browse links are available only for item sets and items.',
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
                    'id' => 'reference-args-resource-name',
                    'class' => 'chosen-select',
                    'data-fieldset' => 'args',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][query]',
                'type' => OmekaElement\Query::class,
                'options' => [
                    'label' => 'Search pool query', // @translate
                    'info' => 'Restrict references to a particular subset of resources, for example a site.', // @translate
                    'query_resource_type' => null,
                    'query_partial_excludelist' => ['common/advanced-search/site'],
                ],
                'attributes' => [
                    'id' => 'reference-args-query',
                    'data-fieldset' => 'args',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][languages]',
                'type' => CommonElement\ArrayText::class,
                'options' => [
                    'label' => 'Filter by language', // @translate
                    'info' => 'Limit the results to the specified languages. Use "|" to separate multiple languages. Use "||" for values without language.', // @translate
                    'value_separator' => '|',
                ],
                'attributes' => [
                    'id' => 'reference-args-languages',
                    'placeholder' => 'fra|way|apy||',
                    'data-fieldset' => 'args',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][sort_by]',
                // 'type' => CommonElement\OptionalRadio::class,
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Select order', // @translate
                    'value_options' => [
                        'alphabetic' => 'Alphabetic',  // @translate
                        'total' => 'Total',  // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'reference-args-sort-by',
                    'class' => 'chosen-select',
                    'data-fieldset' => 'args',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][sort_order]',
                // 'type' => CommonElement\OptionalRadio::class,
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Select order', // @translate
                    'value_options' => [
                        'asc' => 'Ascendant',  // @translate
                        'desc' => 'Descendant',  // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'reference-args-sort-order',
                    'class' => 'chosen-select',
                    'data-fieldset' => 'args',
                ],
            ])
        ;

        // Options for display.

        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][by_initial]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'One page by initial', // @translate
                    'info' => 'This option is recommended for big lists.', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-by-initial',
                    'data-fieldset' => 'options',
                ],
                'filters' => [
                    ['name' => \Laminas\Filter\Boolean::class],
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][search_config]',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Link to browse or search engine', // @translate
                    'info' => 'This option is useful when the module Advanced Search is used.', // @translate
                    'value_options' => [
                        'default' => 'Search config of the site', // @translate
                    ] + $this->searchConfigs,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'reference-options-search-config',
                    'data-fieldset' => 'options',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][link_to_single]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Direct link for single records', // @translate
                    'info' => 'When a reference has only one item, link to it directly instead of to the items/browse page.', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-link-to-single',
                    'data-fieldset' => 'options',
                ],
                'filters' => [
                    ['name' => \Laminas\Filter\Boolean::class],
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][custom_url]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Custom url for single records', // @translate
                    'info' => 'May be set with modules such Clean Url or Ark. May slow the display when there are many single references.', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-custom-url',
                    'data-fieldset' => 'options',
                ],
                'filters' => [
                    ['name' => \Laminas\Filter\Boolean::class],
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][skiplinks]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add skiplinks above and below list', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-skiplinks',
                    'data-fieldset' => 'options',
                ],
                'filters' => [
                    ['name' => \Laminas\Filter\Boolean::class],
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][headings]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add first letter as headings between references', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-headings',
                    'data-fieldset' => 'options',
                ],
                'filters' => [
                    ['name' => \Laminas\Filter\Boolean::class],
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][total]',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add the total of resources for each reference', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-total',
                    'data-fieldset' => 'options',
                ],
                'filters' => [
                    ['name' => \Laminas\Filter\Boolean::class],
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][thumbnail]',
                'type' => CommonElement\ThumbnailTypeSelect::class,
                'options' => [
                    'label' => 'Add the thumbnail of the first resource', // @translate
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'reference-options-thumbnail',
                    'data-fieldset' => 'options',
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][list_by_max]',
                'type' => Element\Number::class,
                'options' => [
                    'label' => 'Maximum resources to display by reference', // @translate
                    'info' => 'For example, display the items by subject. Let 0 to display a simple list. Maximum is 1024.', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-list-by-max',
                    'required' => false,
                    'min' => 0,
                    'max' => 1024,
                    'data-fieldset' => 'options',
                ],
                'filters' => [
                    ['name' => \Laminas\Filter\ToInt::class],
                ],
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][subject_property]',
                'type' => OmekaElement\PropertySelect::class,
                'options' => [
                    'label' => 'Subject values', // @translate
                    'info' => 'Allow to list related resources. For example, in a library where there are items of types "Authors" and "Documents", and if the creator of the documents are linked resources, then select "Creator" to see the list of documents by author. This option is skipped when option "max by reference" is used.', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'id' => 'reference-options-subject-property',
                    'required' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                    'data-fieldset' => 'options',
                ],
            ])
        ;
    }

    public function setSearchConfigs(array $searchConfigs): self
    {
        $this->searchConfigs = $searchConfigs;
        return $this;
    }
}
