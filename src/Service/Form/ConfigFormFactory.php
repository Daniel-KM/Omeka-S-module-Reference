<?php declare(strict_types=1);
namespace Reference\Service\Form;

use Interop\Container\ContainerInterface;
use Laminas\ServiceManager\Factory\FactoryInterface;
use Reference\Form\ConfigForm;

class ConfigFormFactory implements FactoryInterface
{
    public function __invoke(ContainerInterface $services, $requestedName, array $options = null)
    {
        $translator = $services->get('MvcTranslator');
        $api = $services->get('Omeka\ApiManager');
        $form = new ConfigForm(null, $options);
        $form->setTranslator($translator);
        $form->setApiManager($api);
        return $form;
    }
}
