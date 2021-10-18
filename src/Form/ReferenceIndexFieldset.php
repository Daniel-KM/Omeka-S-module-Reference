<?php declare(strict_types=1);

namespace Reference\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Laminas\Form\Form;
use Omeka\Form\Element as OmekaElement;

// FIXME Use a fieldset, not a form.
class ReferenceIndexFieldset extends Form
{
    public function init(): void
    {
        $this
            ->add([
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
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'id' => 'properties',
                    'required' => false,
                    'multiple' => true,
                    'class' => 'chosen-select',
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
                    'id' => 'resource_classes',
                    'required' => false,
                    'multiple' => true,
                    'class' => 'chosen-select',
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
                    'id' => 'resource_name',
                    'class' => 'chosen-select',
                ],
            ])
            /* // TODO Manage order of the terms (via totals).
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
            ])
            */
            ->add([
                'name' => 'query',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Query to limit resources', // @translate
                    'info' => 'Limit the reference to a particular subset of resources, for example a site, via an advanced search query.', // @translate
                    'documentation' => 'https://omeka.org/s/docs/user-manual/sites/site_pages/#browse-preview',
                ],
                'attributes' => [
                    'id' => 'query',
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
                    'id' => 'languages',
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
                    'info' => 'Translatable title above references, if any.',
                ],
                'attributes' => [
                    'id' => 'heading',
                ],
            ])
            ->add([
                'name' => 'total',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add the total of resources for each reference', // @translate
                ],
                'attributes' => [
                    'id' => 'total',
                ],
            ]);

        if (class_exists('BlockPlus\Form\Element\TemplateSelect')) {
            $optionsFieldset
                ->add([
                    'name' => 'template',
                    'type' => \BlockPlus\Form\Element\TemplateSelect::class,
                    'options' => [
                        'label' => 'Template to display', // @translate
                        'info' => 'Templates are in folder "common/block-layout" of the theme and should start with "reference-index".', // @translate
                        'template' => 'common/block-layout/reference-index',
                    ],
                    'attributes' => [
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
