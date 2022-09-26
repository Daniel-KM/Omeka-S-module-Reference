<?php declare(strict_types=1);

namespace Reference\Job;

use Doctrine\ORM\EntityManager;
use Omeka\Job\AbstractJob;

/**
 * @see \BulkCheck\Job\DbResourceTitle
 */
class UpdateReferenceMetadata extends AbstractJob
{
    /**
     * Limit for the loop to avoid heavy sql requests.
     *
     * @var int
     */
    const SQL_LIMIT = 100;

    /**
     * @var \Doctrine\DBAL\Connection
     */
    protected $connection;

    public function perform(): void
    {
        $services = $this->getServiceLocator();

        // The reference id is the job id for now.
        $referenceIdProcessor = new \Laminas\Log\Processor\ReferenceId();
        $referenceIdProcessor->setReferenceId('Reference/Metadata/job_' . $this->job->getId());

        $this->logger = $services->get('Omeka\Logger');
        $this->logger->addProcessor($referenceIdProcessor);

        // Because this is an indexer that is used in background, another entity
        // manager is used to avoid conflicts with the main entity manager, for
        // example when the job is run in foreground or multiple resources are
        // imported in bulk, so a flush() or a clear() will not be applied on
        // the imported resources but only on the indexed resources.
        $this->connection = $services->get('Omeka\Connection');
        $this->entityManager = $this->getNewEntityManager($services->get('Omeka\EntityManager'));
        $this->resourceRepository = $this->entityManager->getRepository(\Omeka\Entity\Resource::class);

        $this->logger->notice(
            'Starting creation of reference metadata.' // @translate
        );

        $sql = 'SELECT COUNT(id) FROM resource;';
        $totalResources = $this->connection->executeQuery($sql)->fetchOne();
        if (empty($totalResources)) {
            $this->logger->notice(
                'No resource to process.' // @translate
            );
            return;
        }

        $totalToProcess = $totalResources;

        $currentReferenceMetadata = $services->get('ControllerPluginManager')->get('currentReferenceMetadata');

        $offset = 0;
        $totalProcessed = 0;
        while (true) {
            /** @var \Omeka\Entity\Resource[] $resources */
            $resources = $this->resourceRepository->findBy([], ['id' => 'ASC'], self::SQL_LIMIT, $offset);
            if (!count($resources)) {
                break;
            }

            if ($offset) {
                $this->logger->notice(
                    '{processed}/{total} resources processed.', // @translate
                    ['processed' => $offset, 'total' => $totalToProcess]
                );
                if ($this->shouldStop()) {
                    $this->logger->warn(
                        'The job was stopped.' // @translate
                    );
                    return;
                }
            }

            $ids = [];
            foreach ($resources as $resource) {
                $ids[] = (int) $resource->getId();
                $referenceMetadatas = $currentReferenceMetadata($resource);
                foreach ($referenceMetadatas as $metadata) {
                    $this->entityManager->persist($metadata);
                }
                // Avoid memory issue.
                unset($resource);
                ++$totalProcessed;
            }

            $this->connection->executeStatement(
                'DELETE FROM `reference_metadata` WHERE `resource_id` IN (:resources)',
                ['resources' => $ids],
                ['resources' => \Doctrine\DBAL\Connection::PARAM_INT_ARRAY]
            );

            unset($resources);
            $this->entityManager->flush();
            $this->entityManager->clear();

            $offset += self::SQL_LIMIT;
        }

        $this->logger->notice(
            'End of process: {processed}/{total} processed.', // @translate
            ['processed' => $totalProcessed, 'total' => $totalToProcess]
        );
    }

    /**
     * Create a new EntityManager with the same config.
     */
    private function getNewEntityManager(EntityManager $entityManager): EntityManager
    {
        return EntityManager::create(
            $entityManager->getConnection(),
            $entityManager->getConfiguration(),
            $entityManager->getEventManager()
        );
    }
}
