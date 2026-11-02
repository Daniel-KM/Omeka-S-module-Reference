<?php declare(strict_types=1);

namespace ReferenceTest\Stdlib;

use CommonTest\AbstractHttpControllerTestCase;
use ReferenceTest\ReferenceTestTrait;

/**
 * Tests for the references of the access statuses (module Access).
 */
class AccessReferencesTest extends AbstractHttpControllerTestCase
{
    use ReferenceTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $module = $this->getServiceLocator()->get('Omeka\ModuleManager')->getModule('Access');
        if (!$module || $module->getState() !== \Omeka\Module\Manager::STATE_ACTIVE) {
            $this->markTestSkipped('Requires module Access.');
        }
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    public function testResourceWithoutAccessStatusIsCountedAsFree(): void
    {
        $ids = [];
        foreach (['Free', 'Reserved', 'Without status'] as $title) {
            $ids[$title] = $this->createItem([
                'dcterms:title' => [['@value' => $title]],
            ])->id();
        }

        $connection = $this->getServiceLocator()->get('Omeka\Connection');
        $connection->executeStatement(
            'UPDATE access_status SET level = "reserved", level_set = "reserved" WHERE id = ?',
            [$ids['Reserved']]
        );
        $connection->executeStatement(
            'DELETE FROM access_status WHERE id = ?',
            [$ids['Without status']]
        );

        $result = $this->getServiceLocator()->get('Reference\References')
            ->__invoke(['access'], ['id' => array_values($ids)], ['output' => 'associative'])
            ->list();

        $this->assertSame(
            ['free' => 2, 'reserved' => 1],
            array_map('intval', $result['access']['o:references'] ?? [])
        );
    }
}
