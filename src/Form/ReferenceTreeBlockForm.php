<?php
namespace Reference\Form;

use Omeka\Form\Element\PropertySelect;
use Zend\Form\Element;
use Zend\Form\Fieldset;
use Zend\Form\Form;
use Zend\I18n\Translator\TranslatorAwareInterface;
use Zend\I18n\Translator\TranslatorAwareTrait;

class ReferenceTreeBlockForm extends Form implements TranslatorAwareInterface
{
    use TranslatorAwareTrait;

    public function init()
    {
        $this->add([
            'name' => 'o:block[__blockIndex__][o:data][args]',
            'type' => Fieldset::class,
        ]);
        $argsFieldset = $this->get('o:block[__blockIndex__][o:data][args]');

        $argsFieldset->add([
            'name' => 'term',
            'type' => PropertySelect::class,
            'options' => [
                'label' => 'Property', // @translate
                'empty_option' => 'Select a property…', // @translate
                'term_as_value' => true,
            ],
            'attributes' => [
                'required' => true,
                'class' => 'chosen-select',
            ],
        ]);
        $argsFieldset->add([
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
                'required' => true,
            ],
        ]);
        $argsFieldset->add([
            'name' => 'query',
            'type' => Element\Text::class,
            'options' => [
                'label' => 'Query to limit resources', // @translate
                'info' => 'Limit the reference to a particular subset of resources, for example a site, via an advanced search query.', // @translate
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
        ]);
        $optionsFieldset->add([
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
            'name' => 'branch',
            'type' => Element\Checkbox::class,
            'options' => [
                'label' => 'Managed as branch', // @translate
                'info' => 'Check this box if the tree is managed as branch (the path is saved with " :: " between each branch).', // @translate
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

    protected function translate($args)
    {
        $translator = $this->getTranslator();
        return $translator->translate($args);
    }
}
