<?php
namespace Reference\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Reference\Mvc\Controller\Plugin\Reference;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReferenceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, array $options = null)
    {
        $entityManager = $services->get('Omeka\EntityManager');
        $controllerPluginManager = $services->get('ControllerPluginManager');
        $api = $controllerPluginManager->get('api');
        return new Reference(
            $entityManager,
            $api
        );
    }
}
