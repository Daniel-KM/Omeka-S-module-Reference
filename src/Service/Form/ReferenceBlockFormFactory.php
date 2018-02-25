<?php
namespace Reference\Service\Form;

use Interop\Container\ContainerInterface;
use Reference\Form\ReferenceBlockForm;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReferenceBlockFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $translator = $services->get('MvcTranslator');
        $form = new ReferenceBlockForm(null, $options);
        $form->setTranslator($translator);
        return $form;
    }
}
