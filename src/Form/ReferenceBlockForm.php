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
            'name' => 'o:block[__blockIndex__][o:data][args]',
            'type' => Fieldset::class,
        ]);
        $argsFieldset = $this->get('o:block[__blockIndex__][o:data][args]');

        $argsFieldset->add([
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
        $argsFieldset->add([
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
        ]);
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
