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
use Omeka\Settings\SettingsInterface;

/**
 * Reference
 *
 * Allows to serve an alphabetized and a hierarchical page of links to searches
 * for all resources classes and properties of all resources of Omeka S.
 *
 * @copyright Daniel Berthereau, 2017-2021
 * @license http://www.cecill.info/licences/Licence_CeCILL_V2.1-en.txt
 */
class Module extends AbstractModule
{
    const NAMESPACE = __NAMESPACE__;

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
            \Omeka\Form\SiteSettingsForm::class,
            'form.add_elements',
            [$this, 'handleSiteSettings']
        );

        // The job should be run when template title and description are updated.

        // Only prePersist can be used, because other entity events are called
        // during flush. But prePersist is not triggered for now, so use postPersist
        // and postUpdate with insert through sql.
        // But, unlike postUpdate, the values have no id yet during postPersist.
        // So use common api.create.post for each resource too.
        // Deletion is automatically managed via database "on delete cascade"
        // in all cases.
        $adapters = [
            \Omeka\Api\Adapter\ItemAdapter::class,
            \Omeka\Api\Adapter\ItemSetAdapter::class,
            \Omeka\Api\Adapter\MediaAdapter::class,
            \Annotate\Api\Adapter\AnnotationAdapter::class,
            \Article\Api\Adapter\ArticleAdapter::class,
        ];
        foreach ($adapters as $adapter) {
            $sharedEventManager->attach(
                $adapter,
                'api.create.post',
                [$this, 'updateReferenceMetadataApiPost']
            );
            // $sharedEventManager->attach(
            //     $adapter,
            //     'api.update.post',
            //     [$this, 'updateReferenceMetadataApiPost']
            // );
        }
        // $sharedEventManager->attach(
        //     \Omeka\Entity\Resource::class,
        //     'entity.persist.post',
        //     [$this, 'updateReferenceMetadata']
        // );
        $sharedEventManager->attach(
            \Omeka\Entity\Resource::class,
            'entity.update.post',
            [$this, 'updateReferenceMetadata']
        );
    }

    protected function initDataToPopulate(SettingsInterface $settings, string $settingsType, $id = null, iterable $values = []): bool
    {
        // Check site settings , because array options cannot be set by default
        // automatically.
        if ($settingsType === 'site_settings') {
            $exist = $settings->get('reference_resource_name');
            if (is_null($exist)) {
                $config = $this->getConfig();
                $settings->set('reference_options', $config['reference']['site_settings']['reference_options']);
                $settings->set('reference_slugs', $config['reference']['site_settings']['reference_slugs']);
            }
        }
        return parent::initDataToPopulate($settings, $settingsType, $id, $values);
    }

    public function updateReferenceMetadataApiPost(Event $event): void
    {
        /** @var \Omeka\Entity\Resource $resource */
        $resource = $event->getParam('response')->getContent();

        $services = $this->getServiceLocator();
        $currentReferenceMetadata = $services->get('ControllerPluginManager')->get('currentReferenceMetadata');
        $referenceMetadatas = $currentReferenceMetadata($resource);
        if (!count($referenceMetadatas)) {
            return;
        }

        $entityManager = $services->get('Omeka\EntityManager');
        foreach ($referenceMetadatas as $metadata) {
            $entityManager->persist($metadata);
        }
        $entityManager->flush();
    }

    public function updateReferenceMetadata(Event $event): void
    {
        /** @var \Omeka\Entity\Resource $resource */
        $resource = $event->getTarget();

        $this->deleteReferenceMetadataResource($resource);

        $services = $this->getServiceLocator();
        $currentReferenceMetadata = $services->get('ControllerPluginManager')->get('currentReferenceMetadata');
        $referenceMetadatas = $currentReferenceMetadata($resource);
        if (!count($referenceMetadatas)) {
            return;
        }

        // When a post flush event is used, flush is not available to avoid loop,
        // so use only sql.
        $parameters = [];
        $sql = "INSERT INTO `reference_metadata` (`resource_id`, `value_id`, `field`, `lang`, `is_public`, `text`) VALUES\n";
        foreach ($referenceMetadatas as $key => $metadata) {
            $sql .= "(:resource_id_$key, :value_id_$key, :field_$key, :lang_$key, :is_public_$key, :text_$key),\n";
            $parameters["resource_id_$key"] = $metadata->getResource()->getId();
            $parameters["value_id_$key"] = $metadata->getValue()->getId();
            $parameters["field_$key"] = $metadata->getField();
            $parameters["lang_$key"] = $metadata->getLang();
            $parameters["is_public_$key"] = $metadata->getIsPublic();
            $parameters["text_$key"] = $metadata->getText();
        }
        $sql = trim($sql, " \n,");
        $this->getServiceLocator()->get('Omeka\Connection')->executeStatement($sql, $parameters);
    }

    /**
     * Remove all existing reference metadata of a resource.
     */
    protected function deleteReferenceMetadataResource(\Omeka\Entity\Resource $resource): void
    {
        $this->getServiceLocator()->get('Omeka\Connection')->executeStatement(
            'DELETE FROM `reference_metadata` WHERE `resource_id` = :resource',
            ['resource' => $resource->getId()]
        );
    }
}
