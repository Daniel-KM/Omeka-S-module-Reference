<?php
namespace Reference\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use Reference\Site\BlockLayout\ReferenceTree;
use Zend\ServiceManager\Factory\FactoryInterface;

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
            $controllerPluginManager->get('reference')
        );
    }
}
