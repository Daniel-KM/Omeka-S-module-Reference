<?php declare(strict_types=1);
namespace Reference\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Reference\Mvc\Controller\Plugin\Reference;

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
