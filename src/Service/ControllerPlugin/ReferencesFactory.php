<?php declare(strict_types=1);

namespace Reference\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Reference\Mvc\Controller\Plugin\References;

class ReferencesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        $api = $plugins->get('api');

        return new References(
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\ApiAdapterManager'),
            $api,
            $plugins->get('translate'),
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
            $connection->executeQuery($sql)->fetchColumn();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
