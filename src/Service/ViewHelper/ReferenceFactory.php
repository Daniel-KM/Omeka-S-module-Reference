<?php declare(strict_types=1);
namespace Reference\Service\ViewHelper;

use Interop\Container\ContainerInterface;
use Reference\View\Helper\Reference;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ReferenceFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new Reference(
            $services->get('ControllerPluginManager')->get('reference')
        );
    }
}
