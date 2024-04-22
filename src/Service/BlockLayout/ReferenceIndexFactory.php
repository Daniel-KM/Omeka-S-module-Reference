<?php declare(strict_types=1);

namespace Reference\Service\BlockLayout;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Reference\Site\BlockLayout\ReferenceIndex;

class ReferenceIndexFactory implements FactoryInterface
{
    /**
     * Create the Reference index block layout service.
     *
     * @return \Reference\Site\BlockLayout\ReferenceIndex
     */
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        return new ReferenceIndex(
            $services->get('EasyMeta')
        );
    }
}
