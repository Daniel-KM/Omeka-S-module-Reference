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
 * @copyright Daniel Berthereau, 2017-2023
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

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        try {
            $services->get(\Omeka\Job\Dispatcher::class)
                ->dispatch(\Reference\Job\UpdateReferenceMetadata::class);
            $message = new \Omeka\Stdlib\Message(
                'The translated and linked resource metadata are currently indexing.' // @translate
            );
        } catch (\Exception $e) {
            $message = new \Omeka\Stdlib\Message(
                'Translated linked resource metadata should be indexed in main settings.' // @translate
            );
        }
        $messenger->addWarning($message);
    }

    public function attachListeners(SharedEventManagerInterface $sharedEventManager): void
    {
        $sharedEventManager->attach(
            \Omeka\Form\SettingForm::class,
            'form.add_elements',
            [$this, 'handleMainSettings']
        );

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
        // Check site settings, because array options cannot be set by default
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

    public function handleMainSettings(Event $event): void
    {
        /**
         * @var \Laminas\Form\Fieldset $fieldset
         * @var \Laminas\Form\Form $form
         */
        $services = $this->getServiceLocator();
        $fieldset = $services->get('FormElementManager')->get(\Reference\Form\SettingsFieldset::class);
        $fieldset->setName('reference');
        $form = $event->getTarget();
        $form
            ->setOption('element_groups', array_merge($form->getOption('element_groups') ?: [], $fieldset->getOption('element_groups')));

        if (version_compare(\Omeka\Module::VERSION, '4', '<')) {
            $form->add($fieldset);
        } else {
            foreach ($fieldset->getFieldsets() as $subFieldset) {
                $form->add($subFieldset);
            }
            foreach ($fieldset->getElements() as $element) {
                $form->add($element);
            }
        }

        $settings = $services->get('Omeka\Settings');
        $job = $settings->get('reference_metadata_job');
        $settings->delete('reference_metadata_job');
        if (!$job) {
            return;
        }

        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        $urlHelper = $services->get('ViewHelperManager')->get('url');

        // Check if a zip job is already running before running a new one.
        $jobId = $this->checkJob(\Reference\Job\UpdateReferenceMetadata::class);
        if ($jobId) {
            $message = new \Omeka\Stdlib\Message(
                'Another job is running for the same process (job %1$s#%2$d%3$s, %4$slogs%3$s).', // @translate
                sprintf(
                    '<a href="%s">',
                    htmlspecialchars($urlHelper('admin/id', ['controller' => 'job', 'id' => $jobId]))
                ),
                $jobId,
                '</a>',
                sprintf(
                    '<a href="%s">',
                    // Check if module Log is enabled (avoid issue when disabled).
                    htmlspecialchars(class_exists(\Log\Stdlib\PsrMessage::class)
                        ? $urlHelper('admin/log/default', [], ['query' => ['job_id' => $jobId]])
                        : $urlHelper('admin/id', ['controller' => 'job', 'id' => $jobId, 'action' => 'log'])
                ))
            );
            $message->setEscapeHtml(false);
            $messenger->addWarning($message);
            return;
        }

        $job = $services->get(\Omeka\Job\Dispatcher::class)
            ->dispatch(\Reference\Job\UpdateReferenceMetadata::class);
        $jobId = $job->getId();

        $message = new \Omeka\Stdlib\Message(
            'Indexing translated and linked resource metadata in background (job %1$s#%2$d%3$s, %4$slogs%3$s).', // @translate
            sprintf(
                '<a href="%s">',
                htmlspecialchars($urlHelper('admin/id', ['controller' => 'job', 'id' => $jobId]))
            ),
            $jobId,
            '</a>',
            sprintf(
                '<a href="%s">',
                // Check if module Log is enabled (avoid issue when disabled).
                htmlspecialchars(class_exists(\Log\Stdlib\PsrMessage::class)
                    ? $urlHelper('admin/log/default', [], ['query' => ['job_id' => $jobId]])
                    : $urlHelper('admin/id', ['controller' => 'job', 'id' => $jobId, 'action' => 'log'])
            ))
        );
        $message->setEscapeHtml(false);
        $messenger->addSuccess($message);
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
        // See commit #eb068cc the explanation of the foreach instead of the
        // single query (possible memory issue with extracted text).
        // TODO Improve the query to avoid a loop on query. Check using doctrine dql.
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        foreach ($referenceMetadatas as $metadata) {
            $sql = "INSERT INTO `reference_metadata` (`resource_id`, `value_id`, `field`, `lang`, `is_public`, `text`) VALUES (:resource_id, :value_id, :field, :lang, :is_public, :text)";
            $parameters = [
                'resource_id' => (int) $metadata->getResource()->getId(),
                'value_id' => (int) $metadata->getValue()->getId(),
                'field' => (string) $metadata->getField(),
                'lang' => (string) $metadata->getLang(),
                'is_public' => (int) $metadata->getIsPublic(),
                'text' => (string) $metadata->getText(),
            ];
            $connection->executeStatement($sql, $parameters);
        }
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

    /**
     * Check if a job is running for a class and return the first running job id.
     */
    protected function checkJob(string $class): int
    {
        $sql = <<<SQL
SELECT id, pid, status
FROM job
WHERE status IN ("starting", "stopping", "in_progress")
    AND class = :class
ORDER BY id ASC;
SQL;

        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $result = $connection->executeQuery($sql, ['class' => $class])->fetchAllAssociative();

        // Unselect processes without pid.
        foreach ($result as $id => $row) {
            // TODO The check of the pid works only with Linux.
            if (!$row['pid'] || !file_exists('/proc/' . $row['pid'])) {
                unset($result[$id]);
            }
        }

        if (count($result)) {
            reset($result);
            return key($result);
        }

        return 0;
    }
}
