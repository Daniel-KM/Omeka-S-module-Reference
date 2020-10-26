<?php declare(strict_types=1);
namespace Reference\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use Reference\Site\BlockLayout\ReferenceIndex;
use Laminas\ServiceManager\Factory\FactoryInterface;

class ReferenceIndexFactory implements FactoryInterface
{
    /**
     * Create the Reference index block layout service.
     *
     * @return ReferenceIndex
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $plugins = $services->get('ControllerPluginManager');
        return new ReferenceIndex(
            $plugins->get('api')
        );
    }
}
