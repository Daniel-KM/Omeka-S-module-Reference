<?php declare(strict_types=1);

namespace Reference\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;
use Reference\Form\Element\DoubleArrayTextarea;
use Reference\Form\Element\OptionalMultiCheckbox;

class SiteSettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Reference'; // @translate

    public function init(): void
    {
        $this
            ->add([
                'name' => 'reference_resource_name',
                'type' => Element\Radio::class,
                'options' => [
                    'label' => 'Resources to link', // @translate
                    'value_options' => [
                        // TODO Manage the list of reference separately.
                        // '' => 'All resources (separately)', // @translate
                        // 'resources' => 'All resources (together)',  // @translate
                        'items' => 'Items',  // @translate
                        'item_sets' => 'Item sets',  // @translate
                        // 'media' => 'Media',  // @translate
                    ],
                ],
                'attributes' => [
                    'id' => 'reference_resource_name',
                    'value' => 'items',
                ],
            ])
            ->add([
                'name' => 'reference_options',
                'type' => OptionalMultiCheckbox::class,
                'options' => [
                    'label' => 'Display', // @translate
                    'value_options' => [
                        'headings' => 'Headings', // @translate
                        'skiplinks' => 'Skip links', // @translate
                        'total' => 'Individual total', // @translate
                        'link_to_single' => 'Link to single records', // @translate
                        'custom_url' => 'Custom url for single records', // @translate
                    ],
                    'documentation' => 'https://gitlab.com/Daniel-KM/Omeka-S-module-Reference#automatic-site-pages',
                ],
                'attributes' => [
                    'id' => 'reference_options',
                    'required' => false,
                ],
            ])
            ->add([
                'name' => 'reference_slugs',
                'type' => DoubleArrayTextarea::class,
                'options' => [
                    'label' => 'Reference pages for selected classes and properties', // @translate
                    'as_key_value' => true,
                    'second_level_keys' => [
                        'term',
                        'label',
                    ],
                ],
                'attributes' => [
                    'id' => 'reference_slugs',
                    'rows' => 12,
                    'placeholder' => 'slug = term = label
dctype:Image = dctype:Image = Image
dcterms:subject = dcterms:subject = Subjects
',
                ],
            ])
        ;
    }
}
