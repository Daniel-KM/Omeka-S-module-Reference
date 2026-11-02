<?php declare(strict_types=1);

namespace Reference;

// Common may be installed but not registered in autoloader, in particular
// during upgrade. So dynamically register all classes of the module.
if (!defined('COMMON_PSR4_FALLBACK')) {
    foreach ([
        OMEKA_PATH . '/modules/Common/src',
        OMEKA_PATH . '/composer-addons/modules/Common/src',
        dirname(__DIR__) . '/Common/src',
    ] as $commonSrc) {
        if (file_exists($commonSrc . '/TraitModule.php')) {
            define('COMMON_PSR4_FALLBACK', $commonSrc);
            spl_autoload_register(static function ($class): void {
                if (str_starts_with($class, 'Common\\')) {
                    $file = COMMON_PSR4_FALLBACK . '/' . strtr(substr($class, 7), '\\', '/') . '.php';
                    if (file_exists($file)) {
                        require_once $file;
                    }
                }
            });
            break;
        }
    }
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;

/**
 * Reference
 *
 * Allows to serve an alphabetized and a hierarchical page of links to searches
 * for all resources classes and properties of all resources of Omeka S.
 *
 * @copyright Daniel Berthereau, 2017-2026
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    use TraitModule;

    const NAMESPACE = __NAMESPACE__;

    public function onBootstrap(MvcEvent $event): void
    {
        parent::onBootstrap($event);

        $this->getServiceLocator()->get('Omeka\Acl')
            ->allow(
                [
                    \Omeka\Permissions\Acl::ROLE_RESEARCHER,
                    \Omeka\Permissions\Acl::ROLE_AUTHOR,
                    \Omeka\Permissions\Acl::ROLE_REVIEWER,
                    \Omeka\Permissions\Acl::ROLE_EDITOR,
                    \Omeka\Permissions\Acl::ROLE_SITE_ADMIN,
                    \Omeka\Permissions\Acl::ROLE_GLOBAL_ADMIN,
                ],
                [\Reference\Controller\Admin\ReferenceController::class],
                ['browse', 'show', 'values']
            )
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

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $translator = $services->get('MvcTranslator');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.95')) {
            $message = new \Omeka\Stdlib\Message(
                $translator->translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.95'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        $messenger = $services->get('ControllerPluginManager')->get('messenger');

        if (!extension_loaded('intl')) {
            $messenger->addWarning(new PsrMessage(
                'The php extension "intl" is recommended to fix issues with diacritic letters.' // @translate
            ));
        }
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );
    }

}
