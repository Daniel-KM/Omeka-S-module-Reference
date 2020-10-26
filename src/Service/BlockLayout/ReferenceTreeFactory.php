<?php declare(strict_types=1);

namespace Reference\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Reference\Site\BlockLayout\ReferenceTree;

class ReferenceTreeFactory implements FactoryInterface
{
    /**
     * Create the Reference tree block layout service.
     *
     * @return ReferenceTree
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $controllerPluginManager = $services->get('ControllerPluginManager');
        return new ReferenceTree(
            $controllerPluginManager->get('api'),
            $controllerPluginManager->get('referenceTree')
        );
    }
}
