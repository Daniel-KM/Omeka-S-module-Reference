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
            $services->get('ControllerPluginManager')->get('api'),
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
