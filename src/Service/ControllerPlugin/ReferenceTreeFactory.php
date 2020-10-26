<?php declare(strict_types=1);

namespace Reference\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Reference\Mvc\Controller\Plugin\ReferenceTree;

class ReferenceTreeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new ReferenceTree(
            $plugins->get('api'),
            $plugins->get('references')
        );
    }
}
