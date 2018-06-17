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
        $controllerPluginManager = $services->get('ControllerPluginManager');
        $api = $controllerPluginManager->get('api');
        $formElementManager = $services->get('FormElementManager');
        $config = $services->get('Config');
        $plugin = $controllerPluginManager->get('reference');
        return new Reference(
            $api,
            $formElementManager,
            $config['reference']['block_settings'],
            $plugin
        );
    }
}
