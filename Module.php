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
        $config = require __DIR__ . '/config/module.config.php';

        // The reference plugin is not available during upgrade.
        include_once __DIR__ . '/src/Mvc/Controller/Plugin/Reference.php';
        $entityManager = $serviceLocator->get('Omeka\EntityManager');
        $controllerPluginManager = $serviceLocator->get('ControllerPluginManager');
        $api = $controllerPluginManager->get('api');
        $referencePlugin = new Mvc\Controller\Plugin\Reference($entityManager, $api);

        if (version_compare($oldVersion, '3.4.5', '<')) {
            $referenceSlugs = $settings->get('reference_slugs');
            foreach ($referenceSlugs as $slug => &$slugData) {
                $slugData['term'] = $slugData['id'];
                unset($slugData['id']);
            }
            $settings->set('reference_slugs', $referenceSlugs);

            $tree = $settings->get('reference_tree_hierarchy', '');
            $settings->set(
                'reference_tree_hierarchy',
                $referencePlugin->convertTreeToLevels($tree)
            );

            $defaultConfig = $config[strtolower(__NAMESPACE__)]['config'];
            $settings->set(
                'reference_resource_name',
                $defaultConfig['reference_resource_name']
            );
            $settings->set(
                'reference_total',
                $defaultConfig['reference_total']
            );
        }

        if (version_compare($oldVersion, '3.4.7', '<')) {
            $tree = $settings->get('reference_tree_hierarchy', '');
            $treeString = $referencePlugin->convertFlatLevelsToTree($tree);
            $settings->set(
                'reference_tree_hierarchy',
                $referencePlugin->convertTreeToLevels($treeString)
            );

            $entityManager = $serviceLocator->get('Omeka\EntityManager');
            $repository = $entityManager->getRepository(\Omeka\Entity\SitePageBlock::class);
            $blocks = $repository->findBy(['layout' => 'reference']);
            foreach ($blocks as $block) {
                $data = $block->getData();
                if (empty($data['reference']['tree']) || $data['reference']['mode'] !== 'tree') {
                    continue;
                }
                $treeString = $referencePlugin->convertFlatLevelsToTree($data['reference']['tree']);
                $data['reference']['tree'] = $referencePlugin->convertTreeToLevels($treeString);
                $block->setData($data);
                $entityManager->persist($block);
            }
            $entityManager->flush();
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
            $currentValue = $settings->get($name);
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

        $form = $formElementManager->get(ConfigForm::class);
        $form->init();
        // TODO Fix the setData() with sub-subfieldset..
        $form->setData($data);
        $html = '<p>';
        $html .= $renderer->translate('This config allows to create routed pages for all sites.'); // @translate
        $html .= ' ' . $renderer->translate('References can be created inside pages via blocks too.'); // @translate
        $html .= '</p>';
        $html .= $renderer->formCollection($form);
        return $html;
    }

    public function handleConfigForm(AbstractController $controller)
    {
        $services = $this->getServiceLocator();
        $config = $services->get('Config');
        $settings = $services->get('Omeka\Settings');
        $controllerPluginManager = $services->get('ControllerPluginManager');
        $referencePlugin = $controllerPluginManager->get('reference');

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

        // Normalize the tree.
        $params['reference_tree_hierarchy'] = $referencePlugin
            ->convertTreeToLevels($params['reference_tree_hierarchy']);

        $defaultSettings = $config[strtolower(__NAMESPACE__)]['config'];
        foreach ($params as $name => $value) {
            if (isset($defaultSettings[$name])) {
                $settings->set($name, $value);
            }
        }
    }
}
