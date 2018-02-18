<?php
namespace Reference\Service\Form;

use Interop\Container\ContainerInterface;
use Reference\Form\ConfigForm;
use Zend\ServiceManager\Factory\FactoryInterface;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $api = $services->get('Omeka\ApiManager');
        $configForm = new ConfigForm(null, $options);
        $configForm->setApiManager($api);
        return $configForm;
    }
}
