<?php declare(strict_types=1);

namespace Reference;

if (!class_exists(\Common\TraitModule::class)) {
    require_once dirname(__DIR__) . '/Common/TraitModule.php';
}

use Common\Stdlib\PsrMessage;
use Common\TraitModule;
use Laminas\EventManager\Event;
use Laminas\EventManager\SharedEventManagerInterface;
use Laminas\Mvc\MvcEvent;
use Omeka\Module\AbstractModule;
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
    use TraitModule;

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

    protected function preInstall(): void
    {
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');
        $translate = $plugins->get('translate');

        if (!method_exists($this, 'checkModuleActiveVersion') || !$this->checkModuleActiveVersion('Common', '3.4.62')) {
            $message = new \Omeka\Stdlib\Message(
                $translate('The module %1$s should be upgraded to version %2$s or later.'), // @translate
                'Common', '3.4.62'
            );
            throw new \Omeka\Module\Exception\ModuleCannotInstallException((string) $message);
        }
    }

    protected function postInstall(): void
    {
        $services = $this->getServiceLocator();
        $messenger = $services->get('ControllerPluginManager')->get('messenger');
        try {
            $services->get(\Omeka\Job\Dispatcher::class)
                ->dispatch(\Reference\Job\UpdateReferenceMetadata::class);
            $message = new PsrMessage(
                'The translated and linked resource metadata are currently indexing.' // @translate
            );
        } catch (\Exception $e) {
            $message = new PsrMessage(
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
                [$this, 'updateReferenceMetadataApiCreatePost']
            );
            // $sharedEventManager->attach(
            //     $adapter,
            //     'api.update.post',
            //     [$this, 'updateReferenceMetadataApiCreatePost']
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

        // Add a job to upgrade structure from v3.
        $sharedEventManager->attach(
            \EasyAdmin\Form\CheckAndFixForm::class,
            'form.add_elements',
            [$this, 'handleEasyAdminJobsForm']
            );
        $sharedEventManager->attach(
            \EasyAdmin\Controller\CheckAndFixController::class,
            'easyadmin.job',
            [$this, 'handleEasyAdminJobs']
        );
    }

    protected function initDataToPopulate(SettingsInterface $settings, string $settingsType, $id = null): bool
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

        // Copy of the trait method: a trait method cannot be called when
        // overridden.

        // This method is not in the interface, but is set for config, site and
        // user settings.
        if (!method_exists($settings, 'getTableName')) {
            return false;
        }

        $config = $this->getConfig();
        $space = strtolower(static::NAMESPACE);
        if (empty($config[$space][$settingsType])) {
            return false;
        }

        /** @var \Doctrine\DBAL\Connection $connection */
        $services = $this->getServiceLocator();
        $connection = $services->get('Omeka\Connection');
        if ($id) {
            if (!method_exists($settings, 'getTargetIdColumnName')) {
                return false;
            }
            $sql = sprintf('SELECT id, value FROM %s WHERE %s = :target_id', $settings->getTableName(), $settings->getTargetIdColumnName());
            $stmt = $connection->executeQuery($sql, ['target_id' => $id]);
        } else {
            $sql = sprintf('SELECT id, value FROM %s', $settings->getTableName());
            $stmt = $connection->executeQuery($sql);
        }

        $translator = $services->get('MvcTranslator');

        $currentSettings = $stmt->fetchAllKeyValue();
        $defaultSettings = $config[$space][$settingsType];
        // Skip settings that are arrays, because the fields "multi-checkbox"
        // and "multi-select" are removed when no value are selected, so it's
        // not possible to determine if it's a new setting or an old empty
        // setting currently. So fill them via upgrade in that case or fill the
        // values.
        // TODO Find a way to save empty multi-checkboxes and multi-selects (core fix).
        $defaultSettings = array_filter($defaultSettings, fn ($v) => !is_array($v));
        $missingSettings = array_diff_key($defaultSettings, $currentSettings);

        foreach ($missingSettings as $name => $value) {
            $settings->set(
                $name,
                $this->isSettingTranslatable($settingsType, $name) ? $translator->translate($value) : $value
            );
        }

        return true;
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
            $message = new PsrMessage(
                'Another job is running for the same process (job {link_job}#{job_id}{link_end} ({link_log}logs{link_end}).', // @translate
                [
                    'link_job' => sprintf('<a href="%s">', $urlHelper('admin/id', ['controller' => 'job', 'id' => $jobId])),
                    'job_id' => $jobId,
                    'link_end' => '</a>',
                    'link_log' => sprintf('<a href="%1$s">', class_exists('Log\Module', false)
                        ? $urlHelper('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $jobId]])
                        : $urlHelper('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $jobId])),
                ]
            );
            $message->setEscapeHtml(false);
            $messenger->addWarning($message);
            return;
        }

        $job = $services->get(\Omeka\Job\Dispatcher::class)
            ->dispatch(\Reference\Job\UpdateReferenceMetadata::class);
        $jobId = $job->getId();

        $message = new PsrMessage(
            'Indexing translated and linked resource metadata in background (job {link_job}#{job_id}{link_end} ({link_log}logs{link_end}).', // @translate
            [
                'link_job' => sprintf('<a href="%s">', $urlHelper('admin/id', ['controller' => 'job', 'id' => $jobId])),
                'job_id' => $jobId,
                'link_end' => '</a>',
                'link_log' => sprintf('<a href="%1$s">', class_exists('Log\Module', false)
                    ? $urlHelper('admin/default', ['controller' => 'log'], ['query' => ['job_id' => $jobId]])
                    : $urlHelper('admin/id', ['controller' => 'job', 'action' => 'log', 'id' => $jobId])
                ),
            ]
        );
        $message->setEscapeHtml(false);
        $messenger->addSuccess($message);
    }

    /**
     * This method should be created for "create" only.
     */
    public function updateReferenceMetadataApiCreatePost(Event $event): void
    {
        /** @var \Omeka\Entity\Resource $resource */
        $resource = $event->getParam('response')->getContent();

        $services = $this->getServiceLocator();
        $currentReferenceMetadata = $services->get('ControllerPluginManager')->get('currentReferenceMetadata');

        /** @var \Reference\Entity\Metadata[] $referenceMetadatas */
        $referenceMetadatas = $currentReferenceMetadata($resource);
        if (!count($referenceMetadatas)) {
            return;
        }

        // Because this is an indexer, another entity manager is used to avoid
        // conflicts with the main entity manager, for example when the job is
        // run in foreground or multiple resources are imported in bulk, so a
        // flush() or a clear() will not be applied on the imported resources
        // but only on the indexed resources.
        /** @var \Doctrine\ORM\EntityManager $entityManager */
        $entityManager = $services->get('Omeka\EntityManager');
        $entityManager = \Doctrine\ORM\EntityManager::create(
            $entityManager->getConnection(),
            $entityManager->getConfiguration(),
            $entityManager->getEventManager()
        );

        foreach ($referenceMetadatas as $referenceMetadata) {
            // Use references to avoid doctrine issue "A new entity was found".
            // It should be done here even if already done in CurrentReferenceMetadata,
            // because it is not the same entity manager.
            // Reference for resource.
            $referenceResource = $referenceMetadata->getResource();
            $referenceResourceRef = $entityManager->getReference($referenceResource->getResourceId(), $referenceResource->getId());
            $referenceMetadata->setResource($referenceResourceRef);
            // Reference for value.
            $referenceValue = $referenceMetadata->getValue();
            $referenceValueRef = $entityManager->getReference(\Omeka\Entity\Value::class, $referenceValue->getId());
            $referenceMetadata->setValue($referenceValueRef);
            // Persist without issue.
            $entityManager->persist($referenceMetadata);
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
        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        // TODO Improve the query to avoid a loop on query. Check using doctrine dql.
        // Use an insert, because all existing references are removed above.
        $sql = <<<'SQL'
INSERT INTO `reference_metadata` (`resource_id`, `value_id`, `field`, `lang`, `is_public`, `text`)
VALUES (:resource_id, :value_id, :field, :lang, :is_public, :text);
SQL;
        // No field is nullable, so types are known.
        $types = [
            'resource_id' => \Doctrine\DBAL\ParameterType::INTEGER,
            'value_id' => \Doctrine\DBAL\ParameterType::INTEGER,
            'field' => \Doctrine\DBAL\ParameterType::STRING,
            'lang' => \Doctrine\DBAL\ParameterType::STRING,
            'is_public' => \Doctrine\DBAL\ParameterType::INTEGER,
            'text' => \Doctrine\DBAL\ParameterType::STRING,
        ];
        foreach ($referenceMetadatas as $metadata) {
            $parameters = [
                'resource_id' => (int) $metadata->getResource()->getId(),
                'value_id' => (int) $metadata->getValue()->getId(),
                'field' => (string) $metadata->getField(),
                'lang' => (string) $metadata->getLang(),
                'is_public' => (int) $metadata->getIsPublic(),
                'text' => (string) $metadata->getText(),
            ];
            $connection->executeStatement($sql, $parameters, $types);
        }
    }

    public function handleEasyAdminJobsForm(Event $event): void
    {
        /**
         * @var \EasyAdmin\Form\CheckAndFixForm $form
         * @var \Laminas\Form\Element\Radio $process
         */
        $form = $event->getTarget();
        $fieldset = $form->get('module_tasks');
        $process = $fieldset->get('process');
        $valueOptions = $process->getValueOptions();
        $valueOptions['reference_index'] = 'Reference: Index fields'; // @translate
        $process->setValueOptions($valueOptions);
    }

    public function handleEasyAdminJobs(Event $event): void
    {
        $process = $event->getParam('process');
        if ($process === 'reference_index') {
            $event->setParam('job', \Reference\Job\UpdateReferenceMetadata::class);
            $event->setParam('args', []);
        }
    }

    /**
     * Remove all existing reference metadata of a resource.
     */
    protected function deleteReferenceMetadataResource(\Omeka\Entity\Resource $resource): void
    {
        $this->getServiceLocator()->get('Omeka\Connection')->executeStatement(
            'DELETE FROM `reference_metadata` WHERE `resource_id` = :resource_id',
            ['resource_id' => $resource->getId()],
            ['resource_id' => \Doctrine\DBAL\ParameterType::INTEGER]
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
        $result = $connection->executeQuery($sql, ['class' => $class], ['class' => \Doctrine\DBAL\ParameterType::STRING])->fetchAllAssociative();

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
