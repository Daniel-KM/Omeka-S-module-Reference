<?php
namespace Reference\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Reference\View\Helper\References;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReferencesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new References(
            $services->get('ControllerPluginManager')->get('references')
        );
    }
}
