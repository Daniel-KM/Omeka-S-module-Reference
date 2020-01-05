<?php
namespace Reference\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use Reference\Site\BlockLayout\Reference;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReferenceFactory implements FactoryInterface
{
    /**
     * Create the Reference block layout service.
     *
     * @return Reference
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new Reference(
            $plugins->get('api')
        );
    }
}
