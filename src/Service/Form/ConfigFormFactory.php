<?php
namespace Reference\Service\Form;

use Interop\Container\ContainerInterface;
use Reference\Form\ConfigForm;
use Zend\ServiceManager\Factory\FactoryInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $translator = $services->get('MvcTranslator');
        $api = $services->get('Omeka\ApiManager');
        $form = new ConfigForm(null, $options);
        $form->setTranslator($translator);
        $form->setApiManager($api);
        return $form;
    }
}
