<?php
namespace Reference\Form;

use Omeka\Form\Element\PropertySelect;
use Omeka\Form\Element\ResourceClassSelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;

// FIXME Use a fieldset, not a form.
class ReferenceFieldset extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][args]',
            'type' => Fieldset::class,
        ]);

        $argsFieldset = $this->get('o:block[__blockIndex__][o:data][args]');
        $argsFieldset
            ->add([
                'name' => 'property',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])
            ->add([
                'name' => 'resource_class',
                'type' => ResourceClassSelect::class,
                'options' => [
                    'label' => 'Resource class', // @translate
                    'empty_option' => '',
                    'term_as_value' => true,
                ],
                'attributes' => [
                    'required' => false,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a resource class…', // @translate
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
                    'class' => 'chosen-select',
                ],
            ])
            ->add([
                'name' => 'query',
                'type' => Element\Text::class,
                'options' => [
                    'label' => 'Query to limit resources', // @translate
                    'info' => 'Limit the reference to a particular subset of resources, for example a site, via an advanced search query.', // @translate
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
                    'info' => 'Translatable title above references, if any. The placeholder {total} can be used.', // @translate
                ],
            ])
            ->add([
                'name' => 'link_to_single',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Link to single records', // @translate
                    'info' => 'When a reference has only one item, link to it directly instead of to the items/browse page.', // @translate
                ],
            ])
            ->add([
                'name' => 'custom_url',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Custom url for single', // @translate
                    'info' => 'May be set with modules such Clean Url or Ark. May slow the display when there are many single references.', // @translate
                ],
            ])
            ->add([
                'name' => 'skiplinks',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add skiplinks above and below list', // @translate
                ],
            ])
            ->add([
                'name' => 'headings',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add first letter as headings between references', // @translate
                ],
            ])
            ->add([
                'name' => 'total',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add the total of resources for each reference', // @translate
                ],
            ]);

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
