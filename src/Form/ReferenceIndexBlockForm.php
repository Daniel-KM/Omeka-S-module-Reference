<?php
namespace Reference\Form;

use Omeka\Form\Element\PropertySelect;
use Omeka\Form\Element\ResourceClassSelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;

class ReferenceIndexBlockForm extends Form
{
    public function init()
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][args]',
            'type' => Fieldset::class,
        ]);
        $argsFieldset = $this->get('o:block[__blockIndex__][o:data][args]');

        $argsFieldset->add([
            'name' => 'properties',
            'type' => PropertySelect::class,
            'options' => [
                'label' => 'Properties', // @translate
                'empty_option' => 'Select properties…', // @translate
                'term_as_value' => true,
            ],
            'attributes' => [
                'id' => 'properties',
                'required' => false,
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select properties…', // @translate
            ],
        ]);
        $argsFieldset->add([
            'name' => 'resource_classes',
            'type' => ResourceClassSelect::class,
            'options' => [
                'label' => 'Resource classes', // @translate
                'empty_option' => 'Select resource classes…', // @translate
                'term_as_value' => true,
            ],
            'attributes' => [
                'id' => 'resource_classes',
                'required' => false,
                'multiple' => true,
                'class' => 'chosen-select',
                'data-placeholder' => 'Select resource classes…', // @translate
            ],
        ]);
        $argsFieldset->add([
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
            ],
        ]);
        /* // TODO Manage order of the terms (via totals).
        $argsFieldset->add([
            'name' => 'order',
            'type' => Element\Select::class,
            'options' => [
                'label' => 'Select order', // @translate
                'value_options' => [
                    'alphabetic ASC' => 'Alphabetic ascendant',  // @translate
                    'alphabetic DESC' => 'Alphabetic descendant',  // @translate
                    'count ASC' => 'Count ascendant',  // @translate
                    'count DESC' => 'Count descendant',  // @translate
                ],
            ],
        ]);
        */
        $argsFieldset->add([
            'name' => 'query',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Query to limit resources', // @translate
                'info' => 'Limit the reference to a particular subset of resources, for example a site, via an advanced search query.', // @translate
            ],
            'attributes' => [
                'id' => 'query',
            ],
        ]);

        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][options]',
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Display', // @translate
            ],
        ]);
        $optionsFieldset = $this->get('o:block[__blockIndex__][o:data][options]');

        $optionsFieldset->add([
            'name' => 'heading',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Heading', // @translate
                'info' => 'Translatable title above references, if any.',
            ],
            'attributes' => [
                'id' => 'heading',
            ],
        ]);
        $optionsFieldset->add([
            'name' => 'total',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Add the total of resources for each reference', // @translate
            ],
            'attributes' => [
                'id' => 'total',
            ],
        ]);

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'o:block[__blockIndex__][o:data][args]',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'o:block[__blockIndex__][o:data][options]',
            'required' => false,
        ]);
    }
}
