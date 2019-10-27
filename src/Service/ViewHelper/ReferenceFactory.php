<?php
namespace Reference\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Reference\View\Helper\Reference;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReferenceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Reference(
            $services->get('ControllerPluginManager')->get('reference')
        );
    }
}
