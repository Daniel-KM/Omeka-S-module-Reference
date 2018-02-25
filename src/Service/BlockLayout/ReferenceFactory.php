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
     * @param ContainerInterface $services
     * @return Reference
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $controllerPluginManager = $services->get('ControllerPluginManager');
        $api = $controllerPluginManager->get('api');
        $formElementManager = $services->get('FormElementManager');
        $referencePlugin = $controllerPluginManager->get('reference');
        $config = $services->get('Config');
        return new Reference(
            $api,
            $formElementManager,
            $referencePlugin,
           $config['reference']['block_settings']
        );
    }
}
