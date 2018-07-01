<?php
namespace Reference\Service\Form;

use Interop\Container\ContainerInterface;
use Reference\Form\ReferenceTreeBlockForm;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReferenceTreeBlockFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $translator = $services->get('MvcTranslator');
        $form = new ReferenceTreeBlockForm(null, $options);
        $form->setTranslator($translator);
        return $form;
    }
}
