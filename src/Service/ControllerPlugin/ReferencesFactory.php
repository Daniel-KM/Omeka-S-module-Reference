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

        $resourceTemplates = [];
        foreach ($api->search('resource_templates')->getContent() as $resourceTemplate) {
            $resourceTemplates[$resourceTemplate->label()] = $resourceTemplate;
        }

        return new References(
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\ApiAdapterManager'),
            $api,
            $plugins->get('translate'),
            $properties,
            $resourceClasses,
            $resourceTemplates,
            $this->supportAnyValue($services)
        );
    }

    protected function supportAnyValue(ContainerInterface $services)
    {
        $connection = $services->get('Omeka\Connection');

        $sql = 'SHOW VARIABLES LIKE "version";';
        $stmt = $connection->query($sql);
        $version = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
        $version = reset($version);

        return stripos($version, 'mysql') !== false
            && version_compare($version, '5.7.5', '>=');
    }
}
