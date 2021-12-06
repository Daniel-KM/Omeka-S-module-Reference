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

    public function init(): void
    {
        $this
            ->add([
                'name' => 'reference_metadata_job',
                'type' => Element\Checkbox::class,
                'options' => [
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
