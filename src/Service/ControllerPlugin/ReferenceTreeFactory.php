<?php declare(strict_types=1);

namespace Reference\Service\ControllerPlugin;

use Psr\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Reference\Mvc\Controller\Plugin\ReferenceTree;

class ReferenceTreeFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $name, ?array $options = null)
    {
        return new ReferenceTree(
            $services->get('Reference\ReferenceTree')
        );
    }
}
