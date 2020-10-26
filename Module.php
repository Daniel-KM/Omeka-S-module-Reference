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
use Laminas\Mvc\MvcEvent;
use Laminas\View\Renderer\PhpRenderer;

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
        $html = '<p>';
        $html .= $renderer->translate('It is recommended to create reference with the blocks of the site pages.'); // @translate
        $html .= ' ' . $renderer->translate('So these options are used only to create global pages.'); // @translate
        $html .= '</p>';
        $html .= '<p>';
        $html .= $renderer->translate('This config allows to create routed pages for all sites.'); // @translate
        $html .= ' ' . $renderer->translate('References are limited by the pool of each site.'); // @translate
        $html .= '</p>';
        return $html
            . parent::getConfigForm($renderer);
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
