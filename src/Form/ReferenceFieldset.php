<?php declare(strict_types=1);

namespace Reference\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;

// FIXME Use a fieldset, not a form.
class ReferenceFieldset extends Form
{
    public function init(): void
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][args]',
            'type' => Fieldset::class,
        ]);

        $argsFieldset = $this->get('o:block[__blockIndex__][o:data][args]');
        $argsFieldset
            ->add([
                'name' => 'properties',
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
                ],
            ])
            ->add([
                'name' => 'resource_classes',
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
                ],
            ])
            ->add([
                'name' => 'resource_name',
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
                ],
            ])
            ->add([
                'name' => 'order',
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Select order', // @translate
                    'value_options' => [
                        'alphabetic ASC' => 'Alphabetic ascendant',  // @translate
                        'alphabetic DESC' => 'Alphabetic descendant',  // @translate
                        'total ASC' => 'Total ascendant',  // @translate
                        'total DESC' => 'Total descendant',  // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'reference-args-order',
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'query',
                'type' => OmekaElement\Query::class,
                'options' => [
                    'label' => 'Search pool query', // @translate
                    'info' => 'Restrict references to a particular subset of resources, for example a site.', // @translate
                    'query_resource_type' => null,
                    'query_partial_excludelist' => ['common/advanced-search/site'],
                ],
                'attributes' => [
                    'id' => 'reference-args-query',
                ],
            ])
            ->add([
                'name' => 'languages',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Filter by language', // @translate
                    'info' => 'Limit the results to the specified languages. Use "|" to separate multiple languages. Use "||" for values without language.', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-args-languages',
                    'placeholder' => 'fra|way|apy||',
                ],
            ]);

        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][options]',
                'type' => Fieldset::class,
                'options' => [
                    'label' => 'Display', // @translate
                ],
            ]);

        $optionsFieldset = $this->get('o:block[__blockIndex__][o:data][options]');
        $optionsFieldset
            ->add([
                'name' => 'heading',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Heading', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-heading',
                ],
            ])
            ->add([
                'name' => 'by_initial',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'One page by initial', // @translate
                    'info' => 'This option is recommended for big lists.', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-by-initial',
                ],
            ])
            ->add([
                'name' => 'link_to_single',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Link to single records', // @translate
                    'info' => 'When a reference has only one item, link to it directly instead of to the items/browse page.', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-link-to-single',
                ],
            ])
            ->add([
                'name' => 'custom_url',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Custom url for single', // @translate
                    'info' => 'May be set with modules such Clean Url or Ark. May slow the display when there are many single references.', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-custom-url',
                ],
            ])
            ->add([
                'name' => 'skiplinks',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add skiplinks above and below list', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-skiplinks',
                ],
            ])
            ->add([
                'name' => 'headings',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add first letter as headings between references', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-headings',
                ],
            ])
            ->add([
                'name' => 'total',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add the total of resources for each reference', // @translate
                ],
                'attributes' => [
                    'id' => 'reference-options-total',
                ],
            ])
            ->add([
                'name' => 'list_by_max',
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
                ],
            ])
            ->add([
                'name' => 'subject_property',
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
                ],
            ]);

        if (class_exists('BlockPlus\Form\Element\TemplateSelect')) {
            $optionsFieldset
                ->add([
                    'name' => 'template',
                    'type' => \BlockPlus\Form\Element\TemplateSelect::class,
                    'options' => [
                        'label' => 'Template to display', // @translate
                        'info' => 'Templates are in folder "common/block-layout" of the theme and should start with "reference".', // @translate
                        'template' => 'common/block-layout/reference',
                    ],
                    'attributes' => [
                        'id' => 'reference-options-template',
                        'class' => 'chosen-select',
                    ],
                ]);
        }

        $inputFilter = $this->getInputFilter();
        $inputFilter
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][args]',
                'required' => false,
            ])
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][options]',
                'required' => false,
            ]);
    }
}
