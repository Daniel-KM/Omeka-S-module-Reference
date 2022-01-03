<?php declare(strict_types=1);

namespace Reference\Service\ControllerPlugin;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Reference\Mvc\Controller\Plugin\CurrentReferenceMetadata;

class CurrentReferenceMetadataFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, array $options = null)
    {
        return new CurrentReferenceMetadata(
            $services->get('Omeka\Logger')
        );
    }
}
