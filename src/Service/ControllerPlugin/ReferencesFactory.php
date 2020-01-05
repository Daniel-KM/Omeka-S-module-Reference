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
        /** @var \Doctrine\DBAL\Connection $connection */
        $connection = $services->get('Omeka\Connection');

        // To do a request is the simpler way to check if the flag ONLY_FULL_GROUP_BY
        // is set in any databases, systems and versions and that it can be
        // bypassed by Any_value().
        $sql = 'SELECT ANY_VALUE(id) FROM user LIMIT 1;';
        try {
            $connection->query($sql)->fetchColumn();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
