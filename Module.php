<?php declare(strict_types=1);

namespace Reference;

if (!class_exists(\Generic\AbstractModule::class)) {
    require file_exists(dirname(__DIR__) . '/Generic/AbstractModule.php')
        ? dirname(__DIR__) . '/Generic/AbstractModule.php'
        : __DIR__ . '/src/Generic/AbstractModule.php';
}

use Generic\AbstractModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\ModuleManager\ModuleManager;
use Laminas\Mvc\Controller\AbstractController;
use Laminas\Mvc\MvcEvent;
use Laminas\View\Renderer\PhpRenderer;
use Omeka\Stdlib\Message;
use Reference\Form\ConfigForm;

/**
 * Reference
 *
 * Allows to serve an alphabetized and a hierarchical page of links to searches
 * for all resources classes and properties of all resources of Omeka S.
 *
 * @copyright Daniel Berthereau, 2017-2020
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

    public function init(ModuleManager $moduleManager): void
    {
        require_once __DIR__ . '/vendor/autoload.php';
    }

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $this->getServiceLocator()->get('Omeka\Acl')
            ->allow(
                null,
                [\Reference\Controller\Site\ReferenceController::class],
                ['browse', 'list']
            )
            ->allow(
                null,
                [\Reference\Controller\ApiController::class]
            );
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
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
        $api = $services->get('Omeka\ApiManager');

        // Because there may be more than 1000 input values, that is the default
        // "max_input_vars" limit in php.ini, a js merges all resource classes
        // and properties before submit.
        // TODO Simplify process (see simple form in module Search).
        $renderer->headScript()->appendFile($renderer->assetUrl('js/reference-config.js', 'Reference'));

        $data = [];
        $defaultSettings = $config['reference']['config'];
        foreach ($defaultSettings as $name => $value) {
            //  TODO Manage the values of the config form via the config form.
            $currentValue = $settings->get($name, $value);
            switch ($name) {
                case 'reference_slugs':
                    $referenceSlugs = $currentValue;

                    $fields = [];
                    $termsToIds = [];
                    /** @var \Omeka\Api\Representation\ResourceClassRepresentation[] $resourceClasses */
                    $resourceClasses = $api->search('resource_classes')->getContent();
                    foreach ($resourceClasses as $resourceClass) {
                        $termsToIds['resource_classes'][$resourceClass->term()] = $resourceClass->id();
                        $fields['resource_classes'][$resourceClass->id()] = $resourceClass;
                    }
                    /** @var \Omeka\Api\Representation\PropertyRepresentation[] $properties */
                    $properties = $api->search('properties')->getContent();
                    foreach ($properties as $property) {
                        $termsToIds['properties'][$property->term()] = $property->id();
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
                        $term = $slugData['term'];
                        // Manage new vocabularies.
                        if (empty($termsToIds[$type][$term])) {
                            continue;
                        }
                        $id = $termsToIds[$type][$term];
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
        $html .= ' ' . $renderer->translate('So these options are used only to create global pages.'); // @translate
        $html .= '</p>';
        $html .= '<p>';
        $html .= $renderer->translate('This config allows to create routed pages for all sites.'); // @translate
        $html .= ' ' . $renderer->translate('References are limited by the pool of each site.'); // @translate
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

        // Prepare the list of the properties and classes, because the values
        // are saved by terms but the form is still using ids.

        $api = $services->get('Omeka\ApiManager');

        $idsToTerms = [];
        /** @var \Omeka\Api\Representation\ResourceClassRepresentation[] $resourceClasses */
        $resourceClasses = $api->search('resource_classes')->getContent();
        foreach ($resourceClasses as $resourceClass) {
            $idsToTerms['resource_classes'][$resourceClass->id()] = $resourceClass->term();
        }
        /** @var \Omeka\Api\Representation\PropertyRepresentation[] $properties */
        $properties = $api->search('properties')->getContent();
        foreach ($properties as $property) {
            $idsToTerms['properties'][$property->id()] = $property->term();
        }

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
                $referenceSlug['term'] = $idsToTerms[$type][$id];
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

        $defaultSettings = $config['reference']['config'];
        $params = array_intersect_key($params, $defaultSettings);
        foreach ($params as $name => $value) {
            $settings->set($name, $value);
        }
    }

    public function handleViewAdvancedSearch(Event $event): void
    {
        $services = $this->getServiceLocator();
        $settings = $services->get('Omeka\Settings');
        if (!$settings->get('reference_search_list_values', false)) {
            return;
        }

        $view = $event->getTarget();
        $assetUrl = $view->plugin('assetUrl');
        $view->headLink()
            ->appendStylesheet($assetUrl('vendor/chosen-js/chosen.css', 'Omeka'))
            ->appendStylesheet($assetUrl('css/reference.css', 'Reference'));
        $view->headScript()
            ->appendFile($assetUrl('vendor/chosen-js/chosen.jquery.js', 'Omeka'), 'text/javascript', ['defer' => 'defer'])
            ->appendFile($assetUrl('js/reference-advanced-search.js', 'Reference'), 'text/javascript', ['defer' => 'defer'])
            ->appendScript('var basePath = ' . json_encode($view->basePath()) . ';' . PHP_EOL
                . 'var siteSlug = ' . json_encode($view->params()->fromRoute('site-slug')));
    }
}
