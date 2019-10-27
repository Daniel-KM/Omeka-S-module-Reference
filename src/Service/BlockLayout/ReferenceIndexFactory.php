<?php
namespace Reference\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use Reference\Site\BlockLayout\ReferenceIndex;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReferenceIndexFactory implements FactoryInterface
{
    /**
     * Create the Reference index block layout service.
     *
     * @return ReferenceIndex
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $controllerPluginManager = $services->get('ControllerPluginManager');
        return new ReferenceIndex(
            $controllerPluginManager->get('api'),
            $controllerPluginManager->get('reference')
        );
    }
}
