<?php
namespace Reference\Form;

use Omeka\Form\Element\PropertySelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;

// FIXME Use a fieldset, not a form.
class ReferenceTreeFieldset extends Form
{
    public function init()
    {
        $this
            ->add([
                'name' => 'o:block[__blockIndex__][o:data][args]',
                'type' => Fieldset::class,
            ]);

        $argsFieldset = $this->get('o:block[__blockIndex__][o:data][args]');
        $argsFieldset
            ->add([
                'name' => 'term',
                'type' => PropertySelect::class,
                'options' => [
                    'label' => 'Property', // @translate
                    'term_as_value' => true,
                    'empty_option' => '',
                ],
                'attributes' => [
                    'required' => true,
                    'class' => 'chosen-select',
                    'data-placeholder' => 'Select a property…', // @translate
                ],
            ])
            ->add([
                'name' => 'tree',
                'type' => Element\Textarea::class,
                'options' => [
                    'label' => 'Static tree of references', // @translate
                    'info' => 'If any, write the hierarchy of all your references in order to display them in the "Tree of references" page.
    Format is: one reference by line, preceded by zero, one or more "-" to indicate the hierarchy level.
    Separate the "-" and the reference with a space. Empty lines are not considered.
    Note: sql does case insensitive searches, so all references should be case-insensitively unique.', // @translate
                ],
                'attributes' => [
                    'required' => true,
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
                    'required' => true,
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
                'name' => 'query_type',
                // TODO Radio doesn't work when there are multiple blocks.
                // 'type' => Element\Radio::class,
                'type' => Element\Select::class,
                'options' => [
                    'label' => 'Query type', // @translate
                    'info' => 'The type of query defines how elements are regrouped (see the advanced search).', // @translate
                    'value_options' => [
                        'eq' => 'Is Exactly', // @translate
                        'in' => 'Contains', // @translate
                    ],
                ],
                'attributes' => [
                    'class' => 'chosen-select',
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
                'name' => 'total',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Add the total of resources for each reference', // @translate
                ],
            ])
            ->add([
                'name' => 'expanded',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Expand the tree', // @translate
                ],
            ])
            ->add([
                'name' => 'branch',
                'type' => Element\Checkbox::class,
                'options' => [
                    'label' => 'Managed as branch', // @translate
                    'info' => 'Check this box if the tree is managed as branch (the path is saved with " :: " between each branch).', // @translate
                ],
            ]);

        if (class_exists('BlockPlus\Form\Element\TemplateSelect')) {
            $optionsFieldset
                ->add([
                    'name' => 'template',
                    'type' => \BlockPlus\Form\Element\TemplateSelect::class,
                    'options' => [
                        'label' => 'Template to display', // @translate
                        'info' => 'Templates are in folder "common/block-layout" of the theme and should start with "reference-tree".', // @translate
                        'template' => 'common/block-layout/reference-tree',
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
