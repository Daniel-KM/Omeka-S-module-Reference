<?php
namespace Reference;

use Omeka\Module\AbstractModule;
use Omeka\Stdlib\Message;
use Reference\Form\ConfigForm;
use Zend\EventManager\Event;
use Zend\EventManager\SharedEventManagerInterface;
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

    public function uninstall(ServiceLocatorInterface $serviceLocator)
    {
        $this->manageSettings($serviceLocator->get('Omeka\Settings'), 'uninstall');
    }

    public function upgrade($oldVersion, $newVersion,
        ServiceLocatorInterface $serviceLocator
    ) {
        require_once 'data/scripts/upgrade.php';
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

    public function attachListeners(SharedEventManagerInterface $sharedEventManager)
    {
        $sharedEventManager->attach(
            'Omeka\Controller\Site\Item',
            'view.advanced_search',
            [$this, 'handleViewAdvancedSearch']
        );
    }

    public function getConfigForm(PhpRenderer $renderer)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $controllerPluginManager = $services->get('ControllerPluginManager');
        $api = $services->get('Omeka\ApiManager');
        $referencePlugin = $controllerPluginManager->get('reference');

        // Because there may be more than 1000 input values, that is the default
        // "max_input_vars" limit in php.ini, a js merges all resource classes
        // and properties before submit.
        $renderer->headScript()->appendFile($renderer->assetUrl('js/reference-config.js', __NAMESPACE__));

        $data = [];
        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($defaultSettings as $name => $value) {
            //  TODO Manage the values of the config form via the config form.
            $currentValue = $settings->get($name, $value);
            switch ($name) {
                case 'reference_slugs':
                    $referenceSlugs = $currentValue;

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
                    $data['fieldset_reference_list_params'][$name] = $currentValue;
                    break;
                case 'reference_tree_hierarchy':
                    $currentValue = $referencePlugin
                        ->convertLevelsToTree($currentValue);
                    // No break.
                case strpos($name, 'reference_tree_') === 0:
                    $data['fieldset_reference_tree'][$name] = $currentValue;
                    break;
                default:
                    $data['fieldset_reference_general'][$name] = $currentValue;
                    break;
            }
        }

        $form->init();
        // TODO Fix the setData() with sub-subfieldset.
        $form->setData($data);
        $html = '<p>';
        $html .= $renderer->translate('It is recommended to create reference with the blocks of the site pages.'); // @translate
        $html .= ' ' . $renderer->translate('So these options are used only to create global pages, that are not provided by Omeka yet.'); // @translate
        $html .= '</p>';
        $html .= '<p>';
        $html .= $renderer->translate('This config allows to create routed pages for all sites.'); // @translate
        $html .= ' ' . $renderer->translate('References are limited by the pool of the site.'); // @translate
        $html .= '</p>';
        $html .= $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $form = $services->get('FormElementManager')->get(ConfigForm::class);
        $controllerPluginManager = $services->get('ControllerPluginManager');
        $referencePlugin = $controllerPluginManager->get('reference');

        $params = $controller->getRequest()->getPost();

        $form->init();
        $form->setData($params);
        if (!$form->isValid()) {
            $controller->messenger()->addErrors($form->getMessages());
            return false;
        }

        // TODO Manage the data filtered by the config form.
        // $params = $form->getData();
        $params = $params->toArray();

        // Fix the "max_input_vars" limit in php.ini via js.
        // Recreate the array that was json encoded via js.
        $fieldsData = [];
        $fields = json_decode($params['fieldsets'], true);
        foreach ($fields as $type => $typeFields) {
            foreach ($typeFields as $fieldData) {
                $type = strtok($fieldData['name'], '[]');
                $id = strtok('[]');
                $name = strtok('[]');
                $fieldsData[$type][$id][$name] = $fieldData['value'];
            }
        }

        // Normalize reference slugs by slug to simplify access to pages.
        $referenceSlugs = [];
        $duplicateSlugs = [];
        foreach ($fieldsData as $type => $typeData) {
            foreach ($typeData as $id => $field) {
                if (isset($referenceSlugs[$field['slug']])) {
                    $duplicateSlugs[] = $field['slug'];
                    continue;
                }
                $referenceSlug = [];
                $referenceSlug['type'] = $type;
                $referenceSlug['term'] = $id;
                $referenceSlug['label'] = $field['label'];
                $referenceSlug['active'] = $field['active'];
                $referenceSlugs[$field['slug']] = $referenceSlug;
            }
        }
        $params['reference_slugs'] = $referenceSlugs;

        if ($duplicateSlugs) {
            $controller->messenger()->addError(new Message(
                'The following slugs are duplicated: "%s".', // @translate
                implode('", "', $duplicateSlugs)
            ));
            $controller->messenger()->addWarning('Changes were not saved.'); // @translate
            return false;
        }

        // Normalize the tree.
        $params['reference_tree_hierarchy'] = $referencePlugin
            ->convertTreeToLevels($params['reference_tree_hierarchy']);

        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }
    }

    public function handleViewAdvancedSearch(Event $event)
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        if (!$settings->get('reference_search_list_values', false)) {
            return;
        }

        $view = $event->getTarget();
        $view->headLink()->appendStylesheet($view->assetUrl('vendor/chosen-js/chosen.css', 'Omeka'));
        $view->headLink()->appendStylesheet($view->assetUrl('css/reference.css', 'Reference'));
        $view->headScript()->appendFile($view->assetUrl('vendor/chosen-js/chosen.jquery.js', 'Omeka'));
        $view->headScript()->appendFile($view->assetUrl('js/reference-advanced-search.js', 'Reference'));
        $view->headScript()->appendScript('var basePath = ' . json_encode($view->basePath()) . ';' . PHP_EOL
            . 'var siteSlug = ' . json_encode($view->params()->fromRoute('site-slug')));
    }
}
