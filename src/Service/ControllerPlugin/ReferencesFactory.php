<?php
namespace Reference\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Reference\Mvc\Controller\Plugin\References;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReferencesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        $api = $plugins->get('api');

        $properties = [];
        foreach ($api->search('properties')->getContent() as $property) {
            $properties[$property->term()] = $property;
        }

        $resourceClasses = [];
        foreach ($api->search('resource_classes')->getContent() as $resourceClass) {
            $resourceClasses[$resourceClass->term()] = $resourceClass;
        }

        return new References(
            $api,
            $plugins->get('reference'),
            $plugins->get('translate'),
            $properties,
            $resourceClasses
        );
    }
}
