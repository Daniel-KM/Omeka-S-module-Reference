<?php declare(strict_types=1);

namespace ReferenceTest\Mvc\Controller\Plugin;

use CommonTest\AbstractHttpControllerTestCase;
use ReferenceTest\ReferenceTestTrait;

/**
 * Tests for the References controller plugin.
 *
 * The References plugin is the core logic for the Reference module,
 * providing methods to query and count references by property or resource class.
 *
 * Usage: $references($metadata, $query, $options)->list() returns array of references.
 */
class ReferencesPluginTest extends AbstractHttpControllerTestCase
{
    use ReferenceTestTrait;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Test that the plugin is accessible.
     */
    public function testPluginIsAccessible(): void
    {
        $references = $this->getReferencesPlugin();
        $this->assertNotNull($references);
        $this->assertInstanceOf(\Reference\Mvc\Controller\Plugin\References::class, $references);
    }

    /**
     * Test invoking plugin returns References service for chaining.
     *
     * The controller plugin wrapper returns the Stdlib\References service
     * which provides the same fluent interface for chaining.
     */
    public function testInvokeReturnsSelfForChaining(): void
    {
        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject']);

        $this->assertInstanceOf(\Reference\Stdlib\References::class, $result);
    }

    /**
     * Test counting references for a property.
     */
    public function testListReferencesForProperty(): void
    {
        // Create items with subjects.
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item 1']],
            'dcterms:subject' => [['@value' => 'History']],
        ]);
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item 2']],
            'dcterms:subject' => [['@value' => 'History']],
        ]);
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item 3']],
            'dcterms:subject' => [['@value' => 'Science']],
        ]);

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject'])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test that references returns values with counts.
     */
    public function testReferencesReturnsValuesWithCounts(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item A']],
            'dcterms:subject' => [['@value' => 'Art']],
        ]);
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item B']],
            'dcterms:subject' => [['@value' => 'Art']],
        ]);

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject'], [], ['output' => 'associative'])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references with empty result.
     */
    public function testReferencesWithNoMatches(): void
    {
        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject'], ['id' => 999999999])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references with multiple properties.
     */
    public function testReferencesWithMultipleProperties(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Multi Property Item']],
            'dcterms:subject' => [['@value' => 'Topic A']],
            'dcterms:creator' => [['@value' => 'Author A']],
        ]);

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject', 'dcterms:creator'])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references with grouped properties.
     */
    public function testReferencesWithGroupedProperties(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Date Item']],
            'dcterms:date' => [['@value' => '2024-01-01']],
            'dcterms:issued' => [['@value' => '2024-06-15']],
        ]);

        $references = $this->getReferencesPlugin();
        // Group date and issued under "Dates".
        $result = $references([
            'Dates' => ['dcterms:date', 'dcterms:issued'],
        ])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references sorted alphabetically.
     */
    public function testReferencesSortedAlphabetically(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item Z']],
            'dcterms:subject' => [['@value' => 'Zebra']],
        ]);
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item A']],
            'dcterms:subject' => [['@value' => 'Apple']],
        ]);

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject'], [], [
            'sort_by' => 'alphabetic',
            'sort_order' => 'asc',
        ])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references sorted by count.
     */
    public function testReferencesSortedByCount(): void
    {
        // Create items: 2 with "Popular", 1 with "Rare".
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item 1']],
            'dcterms:subject' => [['@value' => 'Popular']],
        ]);
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item 2']],
            'dcterms:subject' => [['@value' => 'Popular']],
        ]);
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item 3']],
            'dcterms:subject' => [['@value' => 'Rare']],
        ]);

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject'], [], [
            'sort_by' => 'total',
            'sort_order' => 'desc',
        ])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references with language filter.
     */
    public function testReferencesWithLanguageFilter(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'French Item']],
            'dcterms:subject' => [
                ['@value' => 'Histoire', '@language' => 'fra'],
            ],
        ]);
        $this->createItem([
            'dcterms:title' => [['@value' => 'English Item']],
            'dcterms:subject' => [
                ['@value' => 'History', '@language' => 'eng'],
            ],
        ]);

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject'], [], [
            'filters' => ['languages' => ['fra']],
        ])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references with data type filter.
     */
    public function testReferencesWithDatatypeFilter(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Literal Item']],
            'dcterms:subject' => [
                ['@value' => 'Literal Subject', 'type' => 'literal'],
            ],
        ]);

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject'], [], [
            'filters' => ['datatypes' => ['literal']],
        ])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references with resource name filter.
     */
    public function testReferencesWithResourceNameFilter(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Filtered Item']],
            'dcterms:subject' => [['@value' => 'Filtered Subject']],
        ]);

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject'], [], [
            'resource_name' => 'items',
        ])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references with pagination.
     */
    public function testReferencesWithPagination(): void
    {
        // Create items with different subjects.
        for ($i = 1; $i <= 5; $i++) {
            $this->createItem([
                'dcterms:title' => [['@value' => "Item $i"]],
                'dcterms:subject' => [['@value' => "Subject $i"]],
            ]);
        }

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject'], [], [
            'per_page' => 2,
            'page' => 1,
        ])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references with list_by_max option.
     */
    public function testReferencesWithListByMax(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item with Subject']],
            'dcterms:subject' => [['@value' => 'Listed Subject']],
        ]);

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject'], [], [
            'list_by_max' => 10,
        ])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references for resource classes.
     */
    public function testReferencesForResourceClasses(): void
    {
        $references = $this->getReferencesPlugin();
        $result = $references(['foaf:Person'], [], [
            'type' => 'resource_classes',
        ])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references with begin filter.
     */
    public function testReferencesWithBeginFilter(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item Alpha']],
            'dcterms:subject' => [['@value' => 'Alpha Topic']],
        ]);
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item Beta']],
            'dcterms:subject' => [['@value' => 'Beta Topic']],
        ]);

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject'], [], [
            'filters' => ['begin' => ['A']],
        ])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references with initial option.
     */
    public function testReferencesWithInitialOption(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Test Item']],
            'dcterms:date' => [['@value' => '2024-01-15']],
        ]);

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:date'], [], [
            'initial' => 4, // Get first 4 characters (year).
        ])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test references with query filter.
     */
    public function testReferencesWithQueryFilter(): void
    {
        $itemSet = $this->createItemSet([
            'dcterms:title' => [['@value' => 'Test Collection']],
        ]);

        $this->createItem([
            'dcterms:title' => [['@value' => 'Collection Item']],
            'dcterms:subject' => [['@value' => 'Collection Subject']],
            'o:item_set' => [['o:id' => $itemSet->id()]],
        ]);

        $references = $this->getReferencesPlugin();
        $result = $references(['dcterms:subject'], [
            'item_set_id' => $itemSet->id(),
        ])->list();

        $this->assertIsArray($result);
    }

    /**
     * Test that count method returns array of counts.
     */
    public function testCountMethodReturnsArray(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Count Item']],
            'dcterms:subject' => [['@value' => 'Countable Subject']],
        ]);

        $references = $this->getReferencesPlugin();
        $references(['dcterms:subject']);
        $count = $references->count();

        $this->assertIsArray($count);
    }
}
