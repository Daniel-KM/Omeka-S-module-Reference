<?php declare(strict_types=1);

namespace Reference\Form;

use Laminas\Form\Element;
use Laminas\Form\Fieldset;

class SettingsFieldset extends Fieldset
{
    /**
     * @var string
     */
    protected $label = 'Reference'; // @translate

    protected $elementGroups = [
        'jobs' => 'Jobs', // @translate
    ];

    public function init(): void
    {
        $this
            ->setAttribute('id', 'reference')
            ->setOption('element_groups', $this->elementGroups)
            ->add([
                'name' => 'reference_metadata_job',
                'type' => Element\Checkbox::class,
                'options' => [
                    'element_group' => 'jobs',
                    'label' => 'Index reference metadata', // @translate
                    'info' => 'To use some features of module Reference, an index is required.', // @translate
                ],
                'attributes' => [
                    'id' => 'reference_metadata_job',
                ],
            ])
        ;
    }
}
