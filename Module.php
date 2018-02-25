<?php
namespace Reference;

use Omeka\Module\AbstractModule;
use Reference\Form\ConfigForm;
use Zend\Mvc\Controller\AbstractController;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\View\Renderer\PhpRenderer;

/**
 * Reference
 *
 * Allows to serve an alphabetized and a hierarchical page of links to searches
 * for all resources classes and properties of all resources of Omeka S.
 *
 * @copyright Daniel Berthereau, 2017-2018
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function onBootstrap(MvcEvent $event)
    {
        parent::onBootstrap($event);
        $this->addAclRules();
    }

    public function install(ServiceLocatorInterface $serviceLocator)
    {
        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'install');
    }

    public function upgrade($oldVersion, $newVersion,
        ServiceLocatorInterface $serviceLocator
    ) {
        $settings = $serviceLocator->get('Omeka\Settings');
        if (version_compare($oldVersion, '3.4.5', '<')) {
            $referenceSlugs = $settings->get('reference_slugs');
            foreach ($referenceSlugs as $slug => &$slugData) {
                $slugData['term'] = $slugData['id'];
                unset($slugData['id']);
            }
            $settings->set('reference_slugs', $referenceSlugs);
        }
    }

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'uninstall');
    }

    protected function manageSettings($settings, $process, $key = 'config')
    {
        $config = require __DIR__ . '/config/module.config.php';
        $defaultSettings = $config[strtolower(__NAMESPACE__)][$key];
        foreach ($defaultSettings as $name => $value) {
            switch ($process) {
                case 'install':
                    $settings->set($name, $value);
                    break;
                case 'uninstall':
                    $settings->delete($name);
                    break;
            }
        }
    }

    /**
     * Add ACL rules for this module.
     */
    protected function addAclRules()
    {
        $services = $this->getServiceLocator();
        $acl = $services->get('Omeka\Acl');

        $controllerRights = ['browse', 'list', 'tree'];
        $acl->allow(null, Controller\Site\ReferenceController::class, $controllerRights);
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $formElementManager = $services->get('FormElementManager');
        $api = $services->get('Omeka\ApiManager');

        // Because there may be more than 1000 input values, that is the default
        // "max_input_vars" limit in php.ini, a js merges all resource classes
        // and properties before submit.
        $renderer->headScript()->appendFile($renderer->assetUrl('js/reference-config.js', __NAMESPACE__));

        $data = [];
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($defaultSettings as $name => $value) {
            //  TODO Manage the values of the config form via the config form.
            switch ($name) {
                case 'reference_slugs':
                    $referenceSlugs = $settings->get($name);

                    $fields = [];
                    $resourceClasses = $api->search('resource_classes')->getContent();
                    foreach ($resourceClasses as $resourceClass) {
                        $fields['resource_classes'][$resourceClass->id()] = $resourceClass;
                    }
                    $properties = $api->search('properties')->getContent();
                    foreach ($properties as $property) {
                        $fields['properties'][$property->id()] = $property;
                    }

                    // Set all default values to manage new properties.
                    foreach ($fields as $type => $typeData) {
                        foreach ($typeData as $id => $field) {
                            $id = $field->id();
                            $prefix = $field->vocabulary()->prefix();
                            $referenceSlug = [
                                $type . '[' . $id . ']' . '[active]' => false,
                                $type . '[' . $id . ']' . '[label]' => $field->label(),
                                $type . '[' . $id . ']' . '[slug]' => $field->term(),
                            ];
                            $data['fieldset_reference_list_indexes'][$type][$type . '[' . $prefix . ']'][$type . '[' . $id . ']'] = $referenceSlug;
                        }
                    }

                    // Set true values.
                    foreach ($referenceSlugs as $slug => $slugData) {
                        $type = $slugData['type'];
                        $id = $slugData['term'];
                        // Manage removed vocabularies.
                        if (empty($fields[$type][$id])) {
                            continue;
                        }
                        $prefix = $fields[$type][$id]->vocabulary()->prefix();
                        $referenceSlug = [
                            $type . '[' . $id . ']' . '[active]' => $slugData['active'],
                            $type . '[' . $id . ']' . '[label]' => $slugData['label'],
                            $type . '[' . $id . ']' . '[slug]' => $slug,
                        ];
                        $data['fieldset_reference_list_indexes'][$type][$type . '[' . $prefix . ']'][$type . '[' . $id . ']'] = $referenceSlug;
                    }
                    break;
                case strpos($name, 'reference_list_') === 0:
                    $data['fieldset_reference_list_params'][$name] = $settings->get($name);
                    break;
                case strpos($name, 'reference_tree_') === 0:
                    $data['fieldset_reference_tree'][$name] = $settings->get($name);
                    break;
                default:
                    $data['fieldset_reference_general'][$name] = $settings->get($name);
                    break;
            }
        }

        $form = $formElementManager->get(ConfigForm::class);
        $form->init();
        // TODO Fix the setData() with sub-subfieldset..
        $form->setData($data);
        $html = '<p>' . $renderer->translate('The references are available for all resources, but enabled only for items by default.') . '</p>';
        $html .= $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');

        $params = $controller->getRequest()->getPost();

        $form = $this->getServiceLocator()->get('FormElementManager')
            ->get(ConfigForm::class);
        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        // TODO Manage the data filtered by the config form.
        // $params = $form->getData();

        // Recreate the array that was json encoded via js.
        $fieldsData = [];
        foreach (['resource_classes', 'properties'] as $type) {
            $fields = json_decode($params[$type], true);
            foreach ($fields as $key => $fieldData) {
                $type = strtok($fieldData['name'], '[]');
                $id = strtok('[]');
                $name = strtok('[]');
                $fieldsData[$type][$id][$name] = $fieldData['value'];
            }
        }
        // Normalize reference slugs by slug to simplify access to pages.
        $referenceSlugs = [];
        foreach ($fieldsData as $type => $typeData) {
            foreach ($typeData as $id => $field) {
                $referenceSlug = [];
                $referenceSlug['type'] = $type;
                $referenceSlug['term'] = $id;
                $referenceSlug['label'] = $field['label'];
                $referenceSlug['active'] = $field['active'];
                $referenceSlugs[$field['slug']] = $referenceSlug;
            }
        }
        $params['reference_slugs'] = $referenceSlugs;

        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($params as $name => $value) {
            if (isset($defaultSettings[$name])) {
                $settings->set($name, $value);
            }
        }
    }
}
