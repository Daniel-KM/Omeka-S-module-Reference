<?php declare(strict_types=1);

namespace Reference\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Reference\View\Helper\References;

class ReferencesFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new References(
            $plugins->get('references'),
            $plugins->get('referenceTree')
        );
    }
}
