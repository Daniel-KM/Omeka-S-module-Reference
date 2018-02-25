<?php
namespace Reference\Form;

use Omeka\Form\Element\PropertySelect;
use Omeka\Form\Element\ResourceClassSelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\I18n\Translator\TranslatorAwareTrait;

class ReferenceBlockForm extends Form implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    public function init()
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][reference]',
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Reference', // @translate
            ],
        ]);
        $referenceFieldset = $this->get('o:block[__blockIndex__][o:data][reference]');

        $referenceFieldset->add([
            'name' => 'property',
            'type' => PropertySelect::class,
            'options' => [
                'label' => 'Property', // @translate
                'empty_option' => 'Select a property…', // @translate
                'term_as_value' => true,
            ],
            'attributes' => [
                'required' => false,
                'class' => 'chosen-select',
            ],
        ]);
        $referenceFieldset->add([
            'name' => 'resource_class',
            'type' => ResourceClassSelect::class,
            'options' => [
                'label' => 'Resource class', // @translate
                'empty_option' => 'Select a resource class…', // @translate
                'term_as_value' => true,
            ],
            'attributes' => [
                'required' => false,
                'class' => 'chosen-select',
            ],
        ]);
        $referenceFieldset->add([
            'name' => 'tree',
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
        $referenceFieldset->add([
            'name' => 'resource_name',
            'type' => Element\Radio::class,
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
        ]);

        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][options]',
            'type' => Fieldset::class,
            'options' => [
                'label' => 'Options', // @translate
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
        ]);
        $optionsFieldset->add([
            'name' => 'query_type',
            'type' => Element\Radio::class,
            'options' => [
                'label' => 'Query type', // @translate
                'info' => 'The type of query defines how elements are regrouped (see the advanced search).', // @translate
                'value_options' => [
                    'eq' => 'Is Exactly', // @translate
                    'in' => 'Contains', // @translate
                ],
            ],
        ]);
        $optionsFieldset->add([
            'name' => 'link_to_single',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Link to single records', // @translate
                'info' => 'When a reference has only one item, link to it directly instead of to the items/browse page.', // @translate
            ],
        ]);
        $optionsFieldset->add([
            'name' => 'skiplinks',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Add skiplinks above and below list', // @translate
            ],
        ]);
        $optionsFieldset->add([
            'name' => 'headings',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Add first letter as headings between references', // @translate
            ],
        ]);
        $optionsFieldset->add([
            'name' => 'total',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Add the total of resources for each reference', // @translate
            ],
        ]);
        $optionsFieldset->add([
            'name' => 'expanded',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Expand the tree', // @translate
            ],
        ]);
        $optionsFieldset->add([
            'name' => 'tree_term',
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

        $inputFilter = $this->getInputFilter();
        $inputFilter->add([
            'name' => 'o:block[__blockIndex__][o:data][reference]',
            'required' => false,
        ]);
        $inputFilter->add([
            'name' => 'o:block[__blockIndex__][o:data][options]',
            'required' => false,
        ]);
    }

    protected function translate($args)
    {
        $translator = $this->getTranslator();
        return $translator->translate($args);
    }
}
