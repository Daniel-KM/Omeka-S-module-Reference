<?php declare(strict_types=1);
namespace Reference\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Reference\Site\BlockLayout\Reference;

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
