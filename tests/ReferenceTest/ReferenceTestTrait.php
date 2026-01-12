<?php declare(strict_types=1);

namespace ReferenceTest;

use Laminas\ServiceManager\ServiceLocatorInterface;
use Omeka\Api\Manager as ApiManager;
use Omeka\Api\Representation\ItemRepresentation;
use Omeka\Api\Representation\ItemSetRepresentation;
use Omeka\Api\Representation\SiteRepresentation;

/**
 * Shared test helpers for Reference module tests.
 */
trait ReferenceTestTrait
{
    /**
     * @var ServiceLocatorInterface
     */
    protected $services;

    /**
     * @var array IDs of items created during tests (for cleanup).
     */
    protected array $createdResources = [];

    /**
     * @var array IDs of item sets created during tests (for cleanup).
     */
    protected array $createdItemSets = [];

    /**
     * @var bool Whether admin is logged in.
     */
    protected bool $isLoggedIn = false;

    /**
     * Get the service locator.
     */
    protected function getServiceLocator(): ServiceLocatorInterface
    {
        if (isset($this->application) && $this->application !== null) {
            return $this->application->getServiceManager();
        }
        return $this->getApplication()->getServiceManager();
    }

    /**
     * Reset the cached service locator.
     */
    protected function resetServiceLocator(): void
    {
        $this->services = null;
    }

    /**
     * Get the API manager.
     */
    protected function api(): ApiManager
    {
        if ($this->isLoggedIn) {
            $this->ensureLoggedIn();
        }
        return $this->getServiceLocator()->get('Omeka\ApiManager');
    }

    /**
     * Get the entity manager.
     */
    public function getEntityManager(): \Doctrine\ORM\EntityManager
    {
        return $this->getServiceLocator()->get('Omeka\EntityManager');
    }

    /**
     * Login as admin user.
     */
    protected function loginAdmin(): void
    {
        $this->isLoggedIn = true;
        $this->ensureLoggedIn();
    }

    /**
     * Ensure admin is logged in on the current application instance.
     */
    protected function ensureLoggedIn(): void
    {
        $services = $this->getServiceLocator();
        $auth = $services->get('Omeka\AuthenticationService');

        if ($auth->hasIdentity()) {
            return;
        }

        $adapter = $auth->getAdapter();
        $adapter->setIdentity('admin@example.com');
        $adapter->setCredential('root');
        $auth->authenticate();
    }

    /**
     * Logout current user.
     */
    protected function logout(): void
    {
        $this->isLoggedIn = false;
        $auth = $this->getServiceLocator()->get('Omeka\AuthenticationService');
        $auth->clearIdentity();
    }

    /**
     * Create a test item.
     *
     * @param array $data Item data with property terms as keys.
     * @return ItemRepresentation
     */
    protected function createItem(array $data): ItemRepresentation
    {
        // Convert property terms to proper format if needed.
        $itemData = [];
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');

        foreach ($data as $term => $values) {
            // Skip non-property fields.
            if (strpos($term, ':') === false) {
                $itemData[$term] = $values;
                continue;
            }

            $propertyId = $easyMeta->propertyId($term);
            if (!$propertyId) {
                continue;
            }

            $itemData[$term] = [];
            foreach ($values as $value) {
                $valueData = [
                    'type' => $value['type'] ?? 'literal',
                    'property_id' => $propertyId,
                ];
                if (isset($value['@value'])) {
                    $valueData['@value'] = $value['@value'];
                }
                if (isset($value['@id'])) {
                    $valueData['@id'] = $value['@id'];
                }
                if (isset($value['@language'])) {
                    $valueData['@language'] = $value['@language'];
                }
                if (isset($value['o:label'])) {
                    $valueData['o:label'] = $value['o:label'];
                }
                if (isset($value['is_public'])) {
                    $valueData['is_public'] = $value['is_public'];
                }
                $itemData[$term][] = $valueData;
            }
        }

        $response = $this->api()->create('items', $itemData);
        $item = $response->getContent();
        $this->createdResources[] = ['type' => 'items', 'id' => $item->id()];

        return $item;
    }

    /**
     * Create a test item set.
     *
     * @param array $data Item set data with property terms as keys.
     * @return ItemSetRepresentation
     */
    protected function createItemSet(array $data): ItemSetRepresentation
    {
        $itemSetData = [];
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');

        foreach ($data as $term => $values) {
            if (strpos($term, ':') === false) {
                $itemSetData[$term] = $values;
                continue;
            }

            $propertyId = $easyMeta->propertyId($term);
            if (!$propertyId) {
                continue;
            }

            $itemSetData[$term] = [];
            foreach ($values as $value) {
                $valueData = [
                    'type' => $value['type'] ?? 'literal',
                    'property_id' => $propertyId,
                ];
                if (isset($value['@value'])) {
                    $valueData['@value'] = $value['@value'];
                }
                $itemSetData[$term][] = $valueData;
            }
        }

        $response = $this->api()->create('item_sets', $itemSetData);
        $itemSet = $response->getContent();
        $this->createdItemSets[] = $itemSet->id();

        return $itemSet;
    }

    /**
     * Get the References controller plugin.
     *
     * @return \Reference\Mvc\Controller\Plugin\References
     */
    protected function getReferencesPlugin()
    {
        return $this->getServiceLocator()
            ->get('ControllerPluginManager')
            ->get('references');
    }

    /**
     * Get the ReferenceTree controller plugin.
     *
     * @return \Reference\Mvc\Controller\Plugin\ReferenceTree
     */
    protected function getReferenceTreePlugin()
    {
        return $this->getServiceLocator()
            ->get('ControllerPluginManager')
            ->get('referenceTree');
    }

    /**
     * Get the References view helper.
     *
     * @return \Reference\View\Helper\References
     */
    protected function getReferencesViewHelper()
    {
        return $this->getServiceLocator()
            ->get('ViewHelperManager')
            ->get('references');
    }

    /**
     * Get a site for testing.
     *
     * @return SiteRepresentation
     */
    protected function getTestSite(): SiteRepresentation
    {
        $sites = $this->api()->search('sites', ['limit' => 1])->getContent();
        if (empty($sites)) {
            // Create a site if none exists.
            $response = $this->api()->create('sites', [
                'o:title' => 'Test Site',
                'o:slug' => 'test-site',
                'o:theme' => 'default',
            ]);
            $site = $response->getContent();
            $this->createdResources[] = ['type' => 'sites', 'id' => $site->id()];
            return $site;
        }
        return $sites[0];
    }

    /**
     * Clean up created resources after test.
     */
    protected function cleanupResources(): void
    {
        // Delete created items and other resources.
        foreach ($this->createdResources as $resource) {
            try {
                $this->api()->delete($resource['type'], $resource['id']);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdResources = [];

        // Delete created item sets.
        foreach ($this->createdItemSets as $itemSetId) {
            try {
                $this->api()->delete('item_sets', $itemSetId);
            } catch (\Exception $e) {
                // Ignore errors during cleanup.
            }
        }
        $this->createdItemSets = [];
    }
}
