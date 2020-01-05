<?php
namespace Reference\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Reference\Mvc\Controller\Plugin\References;
use Zend\ServiceManager\Factory\FactoryInterface;

class ReferencesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new References(
            $plugins->get('api'),
            $plugins->get('reference'),
            $plugins->get('translate')
        );
    }
}
