<?php declare(strict_types=1);
namespace Reference\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Reference\Mvc\Controller\Plugin\Reference;
use Laminas\ServiceManager\Factory\FactoryInterface;

/**
 * @deprecated Use References instead.
 */
class ReferenceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new Reference(
            $plugins->get('api'),
            $plugins->get('references')
        );
    }
}
