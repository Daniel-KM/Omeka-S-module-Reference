<?php
namespace Reference\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Reference\Mvc\Controller\Plugin\Reference;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReferenceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, array $options = null)
    {
        return new Reference(
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\ApiAdapterManager'),
            $services->get('ControllerPluginManager')->get('api')
        );
    }
}
