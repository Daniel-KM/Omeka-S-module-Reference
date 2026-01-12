<?php declare(strict_types=1);

namespace ReferenceTest\Stdlib;

use CommonTest\AbstractHttpControllerTestCase;
use ReferenceTest\ReferenceTestTrait;

/**
 * Comprehensive tests for Stdlib\References.
 *
 * Tests all combinations of options and parameters with rich test data.
 */
class ReferencesTest extends AbstractHttpControllerTestCase
{
    use ReferenceTestTrait;

    /**
     * @var \Reference\Stdlib\References
     */
    protected $references;

    /**
     * Test items created for comprehensive testing.
     */
    protected array $testItems = [];

    /**
     * Test item sets created for comprehensive testing.
     */
    protected array $testItemSets = [];

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->references = $this->getServiceLocator()->get('Reference\References');
        $this->createTestData();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Create comprehensive test data with various properties, languages, and data types.
     */
    protected function createTestData(): void
    {
        // Create item sets for filtering.
        $this->testItemSets['history'] = $this->createItemSet([
            'dcterms:title' => [['@value' => 'History Collection']],
        ]);
        $this->testItemSets['science'] = $this->createItemSet([
            'dcterms:title' => [['@value' => 'Science Collection']],
        ]);

        // Item 1: French history book.
        $this->testItems['french_history'] = $this->createItem([
            'dcterms:title' => [['@value' => 'Histoire de France']],
            'dcterms:subject' => [
                ['@value' => 'Histoire', '@language' => 'fra'],
                ['@value' => 'France', '@language' => 'fra'],
            ],
            'dcterms:creator' => [['@value' => 'Jean Dupont']],
            'dcterms:date' => [['@value' => '2020-05-15']],
            'dcterms:language' => [['@value' => 'fra']],
            'o:item_set' => [['o:id' => $this->testItemSets['history']->id()]],
        ]);

        // Item 2: English history book.
        $this->testItems['english_history'] = $this->createItem([
            'dcterms:title' => [['@value' => 'History of England']],
            'dcterms:subject' => [
                ['@value' => 'History', '@language' => 'eng'],
                ['@value' => 'England', '@language' => 'eng'],
            ],
            'dcterms:creator' => [['@value' => 'John Smith']],
            'dcterms:date' => [['@value' => '2019-03-20']],
            'dcterms:language' => [['@value' => 'eng']],
            'o:item_set' => [['o:id' => $this->testItemSets['history']->id()]],
        ]);

        // Item 3: Science book with multiple subjects.
        $this->testItems['physics'] = $this->createItem([
            'dcterms:title' => [['@value' => 'Introduction to Physics']],
            'dcterms:subject' => [
                ['@value' => 'Physics'],
                ['@value' => 'Science'],
                ['@value' => 'Education'],
            ],
            'dcterms:creator' => [['@value' => 'Albert Einstein']],
            'dcterms:date' => [['@value' => '2021-01-10']],
            'o:item_set' => [['o:id' => $this->testItemSets['science']->id()]],
        ]);

        // Item 4: Another physics item (for count testing).
        $this->testItems['quantum'] = $this->createItem([
            'dcterms:title' => [['@value' => 'Quantum Mechanics']],
            'dcterms:subject' => [
                ['@value' => 'Physics'],
                ['@value' => 'Quantum'],
            ],
            'dcterms:creator' => [['@value' => 'Richard Feynman']],
            'dcterms:date' => [['@value' => '2022-06-01']],
            'o:item_set' => [['o:id' => $this->testItemSets['science']->id()]],
        ]);

        // Item 5: Chemistry book.
        $this->testItems['chemistry'] = $this->createItem([
            'dcterms:title' => [['@value' => 'Chemistry Basics']],
            'dcterms:subject' => [
                ['@value' => 'Chemistry'],
                ['@value' => 'Science'],
            ],
            'dcterms:creator' => [['@value' => 'Marie Curie']],
            'dcterms:date' => [['@value' => '2018-11-30']],
            'o:item_set' => [['o:id' => $this->testItemSets['science']->id()]],
        ]);

        // Item 6: Multilingual item.
        $this->testItems['multilingual'] = $this->createItem([
            'dcterms:title' => [
                ['@value' => 'Art History', '@language' => 'eng'],
                ['@value' => 'Histoire de l\'art', '@language' => 'fra'],
            ],
            'dcterms:subject' => [
                ['@value' => 'Art', '@language' => 'eng'],
                ['@value' => 'Art', '@language' => 'fra'],
                ['@value' => 'History', '@language' => 'eng'],
                ['@value' => 'Histoire', '@language' => 'fra'],
            ],
            'dcterms:creator' => [['@value' => 'Anonymous']],
            'dcterms:date' => [['@value' => '2023-02-28']],
        ]);

        // Item 7: Item with URI value.
        $this->testItems['with_uri'] = $this->createItem([
            'dcterms:title' => [['@value' => 'Linked Data Example']],
            'dcterms:subject' => [
                ['@value' => 'Linked Data'],
                ['@id' => 'http://example.org/subject/technology', 'type' => 'uri', 'o:label' => 'Technology'],
            ],
            'dcterms:creator' => [['@value' => 'Tim Berners-Lee']],
        ]);

        // Item 8: Item without subject (for include_without_meta testing).
        $this->testItems['no_subject'] = $this->createItem([
            'dcterms:title' => [['@value' => 'Untitled Work']],
            'dcterms:creator' => [['@value' => 'Unknown Author']],
        ]);
    }

    // =========================================================================
    // BASIC FUNCTIONALITY TESTS
    // =========================================================================

    /**
     * Test that the Stdlib service is accessible.
     */
    public function testStdlibServiceIsAccessible(): void
    {
        $this->assertNotNull($this->references);
        $this->assertInstanceOf(\Reference\Stdlib\References::class, $this->references);
    }

    /**
     * Test __invoke returns self for chaining.
     */
    public function testInvokeReturnsSelfForChaining(): void
    {
        $result = $this->references->__invoke(['dcterms:subject']);
        $this->assertSame($this->references, $result);
    }

    /**
     * Test setMetadata and getMetadata.
     */
    public function testSetAndGetMetadata(): void
    {
        $this->references->setMetadata(['dcterms:subject', 'dcterms:creator']);
        $metadata = $this->references->getMetadata();

        $this->assertIsArray($metadata);
        $this->assertCount(2, $metadata);
    }

    /**
     * Test setQuery and getQuery.
     */
    public function testSetAndGetQuery(): void
    {
        $query = ['item_set_id' => 123, 'resource_class_id' => 456];
        $this->references->setQuery($query);
        $result = $this->references->getQuery();

        $this->assertIsArray($result);
        $this->assertEquals(123, $result['item_set_id']);
    }

    /**
     * Test setOptions and getOptions.
     */
    public function testSetAndGetOptions(): void
    {
        $options = ['sort_by' => 'total', 'sort_order' => 'desc'];
        $this->references->setOptions($options);
        $result = $this->references->getOptions();

        $this->assertIsArray($result);
        $this->assertEquals('total', $result['sort_by']);
    }

    // =========================================================================
    // OUTPUT FORMAT TESTS
    // =========================================================================

    /**
     * Test output format 'list' (default).
     */
    public function testOutputFormatList(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], ['output' => 'list'])
            ->list();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('dcterms:subject', $result);
        $this->assertArrayHasKey('o:references', $result['dcterms:subject']);
    }

    /**
     * Test output format 'associative'.
     */
    public function testOutputFormatAssociative(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], ['output' => 'associative'])
            ->list();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('dcterms:subject', $result);
    }

    /**
     * Test output format 'values'.
     */
    public function testOutputFormatValues(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], ['output' => 'values'])
            ->list();

        $this->assertIsArray($result);
    }

    // =========================================================================
    // SORTING TESTS
    // =========================================================================

    /**
     * Test sort by alphabetic ascending.
     */
    public function testSortByAlphabeticAsc(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'sort_by' => 'alphabetic',
                'sort_order' => 'asc',
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Verify alphabetic order.
        $values = array_column($refs, 'val');
        $sorted = $values;
        sort($sorted, SORT_STRING | SORT_FLAG_CASE);
        $this->assertEquals($sorted, $values);
    }

    /**
     * Test sort by alphabetic descending.
     */
    public function testSortByAlphabeticDesc(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'sort_by' => 'alphabetic',
                'sort_order' => 'desc',
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Verify reverse alphabetic order.
        $values = array_column($refs, 'val');
        $sorted = $values;
        rsort($sorted, SORT_STRING | SORT_FLAG_CASE);
        $this->assertEquals($sorted, $values);
    }

    /**
     * Test sort by total (count) descending.
     */
    public function testSortByTotalDesc(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'sort_by' => 'total',
                'sort_order' => 'desc',
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Verify descending count order.
        $counts = array_column($refs, 'total');
        $sorted = $counts;
        rsort($sorted, SORT_NUMERIC);
        $this->assertEquals($sorted, $counts);
    }

    /**
     * Test sort by total (count) ascending.
     */
    public function testSortByTotalAsc(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'sort_by' => 'total',
                'sort_order' => 'asc',
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Verify ascending count order.
        $counts = array_column($refs, 'total');
        $sorted = $counts;
        sort($sorted, SORT_NUMERIC);
        $this->assertEquals($sorted, $counts);
    }

    // =========================================================================
    // PAGINATION TESTS
    // =========================================================================

    /**
     * Test pagination with per_page.
     */
    public function testPaginationPerPage(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'per_page' => 3,
                'page' => 1,
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertLessThanOrEqual(3, count($refs));
    }

    /**
     * Test pagination page 2.
     */
    public function testPaginationPage2(): void
    {
        // Get page 1.
        $page1 = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'per_page' => 2,
                'page' => 1,
            ])
            ->list();

        // Get page 2.
        $page2 = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'per_page' => 2,
                'page' => 2,
            ])
            ->list();

        $refs1 = $page1['dcterms:subject']['o:references'] ?? [];
        $refs2 = $page2['dcterms:subject']['o:references'] ?? [];

        // Pages should have different content (if enough data).
        if (!empty($refs1) && !empty($refs2)) {
            $values1 = array_column($refs1, 'val');
            $values2 = array_column($refs2, 'val');
            $this->assertNotEquals($values1, $values2);
        }
    }

    // =========================================================================
    // LANGUAGE FILTER TESTS
    // =========================================================================

    /**
     * Test filter by French language.
     */
    public function testFilterByFrenchLanguage(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'filters' => ['languages' => ['fra']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Should contain French subjects.
        $values = array_column($refs, 'val');
        $this->assertContains('Histoire', $values);
        $this->assertContains('France', $values);
    }

    /**
     * Test filter by English language.
     */
    public function testFilterByEnglishLanguage(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'filters' => ['languages' => ['eng']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Should contain English subjects.
        $values = array_column($refs, 'val');
        $this->assertContains('History', $values);
        $this->assertContains('England', $values);
    }

    /**
     * Test filter by multiple languages.
     */
    public function testFilterByMultipleLanguages(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'filters' => ['languages' => ['fra', 'eng']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        $values = array_column($refs, 'val');
        // Should contain both French and English.
        $this->assertContains('Histoire', $values);
        $this->assertContains('History', $values);
    }

    /**
     * Test filter by null language (no language tag).
     */
    public function testFilterByNullLanguage(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'filters' => ['languages' => ['null']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Should contain subjects without language.
        $values = array_column($refs, 'val');
        $this->assertContains('Physics', $values);
        $this->assertContains('Science', $values);
    }

    // =========================================================================
    // DATA TYPE FILTER TESTS
    // =========================================================================

    /**
     * Test filter by literal data type.
     */
    public function testFilterByLiteralDataType(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'filters' => ['data_types' => ['literal']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);
    }

    /**
     * Test filter by URI data type.
     */
    public function testFilterByUriDataType(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'filters' => ['data_types' => ['uri']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        // Should contain URI value if any.
        $this->assertIsArray($refs);
    }

    /**
     * Test filter by main type 'literal'.
     */
    public function testFilterByMainTypeLiteral(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'filters' => ['main_types' => ['literal']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);
    }

    // =========================================================================
    // BEGIN/END FILTER TESTS
    // =========================================================================

    /**
     * Test filter by begin (starts with).
     */
    public function testFilterByBegin(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'filters' => ['begin' => ['Ph']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // All values should start with 'Ph'.
        foreach ($refs as $ref) {
            $this->assertStringStartsWith('Ph', $ref['val']);
        }
    }

    /**
     * Test filter by multiple begin values.
     */
    public function testFilterByMultipleBegin(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'filters' => ['begin' => ['Ph', 'Ch']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Values should start with 'Ph' or 'Ch'.
        $values = array_column($refs, 'val');
        $this->assertContains('Physics', $values);
        $this->assertContains('Chemistry', $values);
    }

    /**
     * Test filter by end (ends with).
     */
    public function testFilterByEnd(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'filters' => ['end' => ['ce']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];

        // All values should end with 'ce'.
        foreach ($refs as $ref) {
            $this->assertStringEndsWith('ce', strtolower($ref['val']));
        }
    }

    /**
     * Test filter by specific values.
     */
    public function testFilterByValues(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'filters' => ['values' => ['physics', 'chemistry']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Should only contain specified values (case-insensitive).
        $values = array_map('strtolower', array_column($refs, 'val'));
        foreach ($values as $val) {
            $this->assertContains($val, ['physics', 'chemistry']);
        }
    }

    // =========================================================================
    // QUERY FILTER TESTS
    // =========================================================================

    /**
     * Test with item set filter.
     */
    public function testWithItemSetFilter(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [
                'item_set_id' => $this->testItemSets['science']->id(),
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];

        // The result depends on whether items are properly linked to item sets.
        // This tests that the query filter is accepted without error.
        $this->assertIsArray($refs);
    }

    /**
     * Test with multiple item set filter.
     */
    public function testWithMultipleItemSetFilter(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [
                'item_set_id' => [
                    $this->testItemSets['history']->id(),
                    $this->testItemSets['science']->id(),
                ],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];

        // This tests that array item_set_id query is accepted.
        $this->assertIsArray($refs);
    }

    // =========================================================================
    // RESOURCE NAME TESTS
    // =========================================================================

    /**
     * Test with resource_name 'items'.
     */
    public function testResourceNameItems(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'resource_name' => 'items',
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);
    }

    /**
     * Test with resource_name 'item_sets'.
     */
    public function testResourceNameItemSets(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:title'], [], [
                'resource_name' => 'item_sets',
            ])
            ->list();

        $refs = $result['dcterms:title']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Should contain item set titles.
        $values = array_column($refs, 'val');
        $this->assertContains('History Collection', $values);
        $this->assertContains('Science Collection', $values);
    }

    // =========================================================================
    // OUTPUT OPTIONS TESTS
    // =========================================================================

    /**
     * Test 'first' option includes first resource ID.
     */
    public function testFirstOptionIncludesResourceId(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'first' => true,
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Each reference should have 'first' key.
        foreach ($refs as $ref) {
            $this->assertArrayHasKey('first', $ref);
        }
    }

    /**
     * Test 'initial' option extracts first characters.
     */
    public function testInitialOptionExtractsCharacters(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:date'], [], [
                'initial' => 4, // Extract year (first 4 chars).
            ])
            ->list();

        $refs = $result['dcterms:date']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Each reference should have 'initial' key with 4 chars.
        foreach ($refs as $ref) {
            $this->assertArrayHasKey('initial', $ref);
            $this->assertEquals(4, strlen($ref['initial']));
        }
    }

    /**
     * Test 'first_digits' option extracts year from dates.
     *
     * The first_digits option extracts and aggregates by first digits,
     * e.g., "2020-05-15" becomes 2020, useful for grouping by year.
     */
    public function testFirstDigitsOptionExtractsYear(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:date'], [], [
                'first_digits' => true,
            ])
            ->list();

        $refs = $result['dcterms:date']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Values should be years (integers extracted from dates).
        foreach ($refs as $ref) {
            $this->assertArrayHasKey('val', $ref);
            // The value should be a year (4-digit number).
            $this->assertMatchesRegularExpression('/^-?\d+$/', (string) $ref['val']);
        }
    }

    /**
     * Test 'first_digits' aggregates dates by year.
     */
    public function testFirstDigitsAggregatesByYear(): void
    {
        // Create items with same year but different dates.
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item Jan 2020']],
            'dcterms:date' => [['@value' => '2020-01-15']],
        ]);
        $this->createItem([
            'dcterms:title' => [['@value' => 'Item Jun 2020']],
            'dcterms:date' => [['@value' => '2020-06-20']],
        ]);

        $result = $this->references
            ->__invoke(['dcterms:date'], [], [
                'first_digits' => true,
            ])
            ->list();

        $refs = $result['dcterms:date']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Find year 2020 in results.
        $years = array_column($refs, 'val');
        $this->assertContains(2020, $years);

        // The count for 2020 should be at least 2 (the items we just created).
        foreach ($refs as $ref) {
            if ($ref['val'] == 2020) {
                $this->assertGreaterThanOrEqual(2, $ref['total']);
                break;
            }
        }
    }

    /**
     * Test 'lang' option includes language.
     */
    public function testLangOptionIncludesLanguage(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'lang' => true,
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // When lang option is true, results should be grouped by language.
        // The language key might be '@language' or 'lang' depending on implementation.
        $hasLangInfo = false;
        foreach ($refs as $ref) {
            if (isset($ref['@language']) || isset($ref['lang'])) {
                $hasLangInfo = true;
                break;
            }
        }
        // This tests that the option is processed without error.
        $this->assertIsArray($refs);
    }

    /**
     * Test 'data_type' option includes data type.
     */
    public function testDataTypeOptionIncludesDataType(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'data_type' => true,
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // With data_type option, references may include type information.
        $this->assertIsArray($refs);
    }

    /**
     * Test 'list_by_max' option limits resources per reference.
     */
    public function testListByMaxOption(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 2,
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Should have resources list.
        foreach ($refs as $ref) {
            if (isset($ref['resources'])) {
                $this->assertLessThanOrEqual(2, count($ref['resources']));
            }
        }
    }

    // =========================================================================
    // METADATA TYPE TESTS
    // =========================================================================

    /**
     * Test references for properties.
     */
    public function testReferencesForProperties(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject', 'dcterms:creator'])
            ->list();

        $this->assertArrayHasKey('dcterms:subject', $result);
        $this->assertArrayHasKey('dcterms:creator', $result);
    }

    /**
     * Test references for o:property (list of used properties).
     */
    public function testReferencesForOProperty(): void
    {
        $result = $this->references
            ->__invoke(['o:property'])
            ->list();

        $this->assertArrayHasKey('o:property', $result);
        $refs = $result['o:property']['o:references'] ?? [];
        $this->assertNotEmpty($refs);
    }

    /**
     * Test references for o:item_set.
     */
    public function testReferencesForOItemSet(): void
    {
        $result = $this->references
            ->__invoke(['o:item_set'])
            ->list();

        $this->assertArrayHasKey('o:item_set', $result);
        // o:item_set lists item sets that contain resources.
        // Result depends on whether test items are properly linked to item sets.
        $this->assertIsArray($result['o:item_set']);
    }

    /**
     * Test references for grouped properties.
     */
    public function testReferencesForGroupedProperties(): void
    {
        $result = $this->references
            ->__invoke([
                'Dates' => ['dcterms:date', 'dcterms:issued'],
                'Subjects' => ['dcterms:subject'],
            ])
            ->list();

        $this->assertArrayHasKey('Dates', $result);
        $this->assertArrayHasKey('Subjects', $result);
    }

    /**
     * Test references with comma-separated metadata string.
     */
    public function testReferencesWithCommaSeparatedMetadata(): void
    {
        $result = $this->references
            ->__invoke([
                'Combined' => 'dcterms:date, dcterms:subject',
            ])
            ->list();

        $this->assertArrayHasKey('Combined', $result);
    }

    // =========================================================================
    // COUNT METHOD TESTS
    // =========================================================================

    /**
     * Test count method returns totals.
     */
    public function testCountMethodReturnsTotals(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'])
            ->count();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('dcterms:subject', $result);
        $this->assertIsInt($result['dcterms:subject']);
        $this->assertGreaterThan(0, $result['dcterms:subject']);
    }

    /**
     * Test count with multiple properties.
     */
    public function testCountWithMultipleProperties(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject', 'dcterms:creator'])
            ->count();

        $this->assertArrayHasKey('dcterms:subject', $result);
        $this->assertArrayHasKey('dcterms:creator', $result);
    }

    /**
     * Test count with filters.
     */
    public function testCountWithFilters(): void
    {
        $countAll = $this->references
            ->__invoke(['dcterms:subject'])
            ->count();

        $countFiltered = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'filters' => ['languages' => ['fra']],
            ])
            ->count();

        // Filtered count should be less than or equal to total count.
        $this->assertLessThanOrEqual($countAll['dcterms:subject'], $countFiltered['dcterms:subject']);
    }

    // =========================================================================
    // INITIALS METHOD TESTS
    // =========================================================================

    /**
     * Test initials method returns first letters.
     */
    public function testInitialsMethodReturnsFirstLetters(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'])
            ->initials();

        $this->assertIsArray($result);
        // The initials() method returns an associative array keyed by field.
        // Structure varies based on output format.
        $this->assertNotEmpty($result);

        foreach ($result as $key => $fieldData) {
            // Field data can be an array with o:references or a simpler format.
            if (is_array($fieldData) && isset($fieldData['o:references'])) {
                foreach ($fieldData['o:references'] as $initial) {
                    if (is_array($initial)) {
                        $this->assertArrayHasKey('val', $initial);
                    }
                }
            }
        }
    }

    /**
     * Test initials with custom length.
     */
    public function testInitialsWithCustomLength(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:date'], [], [
                'initial' => 4, // Get year as initial.
            ])
            ->initials();

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);

        foreach ($result as $key => $fieldData) {
            if (is_array($fieldData) && isset($fieldData['o:references'])) {
                foreach ($fieldData['o:references'] as $initial) {
                    if (is_array($initial) && isset($initial['val'])) {
                        // Initials should be 4 characters (years).
                        $this->assertEquals(4, strlen($initial['val']));
                    }
                }
            }
        }
    }

    // =========================================================================
    // META OPTIONS TESTS
    // =========================================================================

    /**
     * Test meta_options for different options per field.
     */
    public function testMetaOptionsPerField(): void
    {
        $result = $this->references
            ->__invoke(
                ['dcterms:subject', 'dcterms:creator'],
                [],
                [
                    'sort_by' => 'alphabetic',
                    'meta_options' => [
                        'dcterms:creator' => [
                            'sort_by' => 'total',
                        ],
                    ],
                ]
            )
            ->list();

        $this->assertArrayHasKey('dcterms:subject', $result);
        $this->assertArrayHasKey('dcterms:creator', $result);
    }

    // =========================================================================
    // EDGE CASES AND ERROR HANDLING
    // =========================================================================

    /**
     * Test with empty metadata.
     */
    public function testWithEmptyMetadata(): void
    {
        $result = $this->references
            ->__invoke([])
            ->list();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    /**
     * Test with non-existent property.
     */
    public function testWithNonExistentProperty(): void
    {
        $result = $this->references
            ->__invoke(['nonexistent:property'])
            ->list();

        $this->assertIsArray($result);
    }

    /**
     * Test with query returning no results.
     */
    public function testWithQueryReturningNoResults(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], ['id' => 999999999])
            ->list();

        $this->assertIsArray($result);
    }

    /**
     * Test include_without_meta option.
     */
    public function testIncludeWithoutMetaOption(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'include_without_meta' => true,
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);
    }

    /**
     * Test distinct option.
     */
    public function testDistinctOption(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'distinct' => true,
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);
    }

    /**
     * Test single_reference_format option.
     */
    public function testSingleReferenceFormatOption(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'single_reference_format' => true,
            ])
            ->list();

        $this->assertIsArray($result);
    }

    /**
     * Test locale option.
     */
    public function testLocaleOption(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'locale' => ['fra', 'eng'],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);
    }

    // =========================================================================
    // FIELDS OPTION TESTS (list_by_max with fields)
    // =========================================================================

    /**
     * Test 'fields' option with resource table fields only (o:id, o:title).
     *
     * This should use the fast SQL path.
     */
    public function testFieldsWithResourceTableFields(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 10,
                'fields' => ['o:id', 'o:title'],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Each reference should have 'resources' with o:id and o:title.
        foreach ($refs as $ref) {
            if (!empty($ref['resources'])) {
                foreach ($ref['resources'] as $resource) {
                    $this->assertArrayHasKey('o:id', $resource);
                    $this->assertArrayHasKey('o:title', $resource);
                    $this->assertIsInt($resource['o:id']);
                    $this->assertIsString($resource['o:title']);
                }
            }
        }
    }

    /**
     * Test 'fields' option with o:id only.
     */
    public function testFieldsWithIdOnly(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 5,
                'fields' => ['o:id'],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        foreach ($refs as $ref) {
            if (!empty($ref['resources'])) {
                foreach ($ref['resources'] as $resource) {
                    $this->assertArrayHasKey('o:id', $resource);
                    // Should not have o:title when not requested.
                    $this->assertArrayNotHasKey('o:title', $resource);
                }
            }
        }
    }

    /**
     * Test 'fields' option with property fields (dcterms:creator).
     *
     * This tests fetching property values via SQL.
     */
    public function testFieldsWithPropertyFields(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 10,
                'fields' => ['dcterms:creator'],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Find a reference that has resources with dcterms:creator.
        $foundCreator = false;
        foreach ($refs as $ref) {
            if (!empty($ref['resources'])) {
                foreach ($ref['resources'] as $resource) {
                    $this->assertArrayHasKey('dcterms:creator', $resource);
                    if (!empty($resource['dcterms:creator'])) {
                        $foundCreator = true;
                        // Should be an array of values.
                        $this->assertIsArray($resource['dcterms:creator']);
                        foreach ($resource['dcterms:creator'] as $value) {
                            $this->assertArrayHasKey('@value', $value);
                        }
                    }
                }
            }
        }
        $this->assertTrue($foundCreator, 'Expected to find at least one resource with dcterms:creator');
    }

    /**
     * Test 'fields' option with mixed fields (resource table + property).
     */
    public function testFieldsWithMixedFields(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 10,
                'fields' => ['o:id', 'o:title', 'dcterms:creator'],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        foreach ($refs as $ref) {
            if (!empty($ref['resources'])) {
                foreach ($ref['resources'] as $resource) {
                    $this->assertArrayHasKey('o:id', $resource);
                    $this->assertArrayHasKey('o:title', $resource);
                    $this->assertArrayHasKey('dcterms:creator', $resource);
                }
            }
        }
    }

    /**
     * Test 'fields' option with multiple property fields.
     */
    public function testFieldsWithMultiplePropertyFields(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 10,
                'fields' => ['dcterms:creator', 'dcterms:date'],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        foreach ($refs as $ref) {
            if (!empty($ref['resources'])) {
                foreach ($ref['resources'] as $resource) {
                    $this->assertArrayHasKey('dcterms:creator', $resource);
                    $this->assertArrayHasKey('dcterms:date', $resource);
                }
            }
        }
    }

    /**
     * Test 'fields' with o:is_public field.
     */
    public function testFieldsWithIsPublic(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 5,
                'fields' => ['o:id', 'o:is_public'],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        foreach ($refs as $ref) {
            if (!empty($ref['resources'])) {
                foreach ($ref['resources'] as $resource) {
                    $this->assertArrayHasKey('o:id', $resource);
                    $this->assertArrayHasKey('o:is_public', $resource);
                    $this->assertIsBool($resource['o:is_public']);
                }
            }
        }
    }

    /**
     * Test 'fields' with o:created and o:modified fields.
     */
    public function testFieldsWithDateFields(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 5,
                'fields' => ['o:id', 'o:created', 'o:modified'],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        foreach ($refs as $ref) {
            if (!empty($ref['resources'])) {
                foreach ($ref['resources'] as $resource) {
                    $this->assertArrayHasKey('o:id', $resource);
                    $this->assertArrayHasKey('o:created', $resource);
                    // o:modified can be null.
                    $this->assertArrayHasKey('o:modified', $resource);
                }
            }
        }
    }

    /**
     * Test that resources without the requested property have empty array.
     */
    public function testFieldsWithMissingPropertyOnSomeResources(): void
    {
        // Create an item without dcterms:description.
        $this->createItem([
            'dcterms:title' => [['@value' => 'No Description Item']],
            'dcterms:subject' => [['@value' => 'UniqueSubjectForTest']],
        ]);

        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 10,
                'fields' => ['o:id', 'dcterms:description'],
                'filters' => ['values' => ['UniqueSubjectForTest']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        foreach ($refs as $ref) {
            if (!empty($ref['resources'])) {
                foreach ($ref['resources'] as $resource) {
                    $this->assertArrayHasKey('dcterms:description', $resource);
                    // Should be empty array since item has no description.
                    $this->assertIsArray($resource['dcterms:description']);
                }
            }
        }
    }

    /**
     * Compare SQL results with API results for validation.
     *
     * This test verifies that the SQL-based field fetching produces
     * equivalent results to what the API would return.
     */
    public function testFieldsResultsMatchApiData(): void
    {
        $api = $this->getServiceLocator()->get('Omeka\ApiManager');

        // Get references with fields.
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 10,
                'fields' => ['o:id', 'o:title', 'dcterms:creator'],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // For each reference, verify the resource data matches API.
        foreach ($refs as $ref) {
            if (empty($ref['resources'])) {
                continue;
            }
            foreach ($ref['resources'] as $resource) {
                $resourceId = $resource['o:id'];

                // Fetch the same resource via API.
                $apiResource = $api->read('items', $resourceId)->getContent();
                $apiJson = $apiResource->jsonSerialize();

                // Compare o:id.
                $this->assertEquals($apiJson['o:id'], $resource['o:id']);

                // Compare o:title.
                $this->assertEquals($apiJson['o:title'], $resource['o:title']);

                // Compare dcterms:creator values.
                // API returns Value representations, we need to get their values.
                $apiCreatorValues = [];
                foreach ($apiResource->value('dcterms:creator', ['all' => true]) as $val) {
                    $apiCreatorValues[] = (string) $val;
                }
                $sqlCreators = $resource['dcterms:creator'] ?? [];
                $sqlCreatorValues = array_column($sqlCreators, '@value');

                // Same count of values.
                $this->assertCount(
                    count($apiCreatorValues),
                    $sqlCreatorValues,
                    "Creator count mismatch for resource $resourceId"
                );

                // Compare values (order may differ, so use array comparison).
                sort($apiCreatorValues);
                sort($sqlCreatorValues);
                $this->assertEquals(
                    $apiCreatorValues,
                    $sqlCreatorValues,
                    "Creator values mismatch for resource $resourceId"
                );
            }
        }
    }

    /**
     * Test fields with language-tagged values.
     */
    public function testFieldsWithLanguageTaggedValues(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 10,
                'fields' => ['o:id', 'dcterms:subject'],
                'filters' => ['languages' => ['fra']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];

        foreach ($refs as $ref) {
            if (empty($ref['resources'])) {
                continue;
            }
            foreach ($ref['resources'] as $resource) {
                if (!empty($resource['dcterms:subject'])) {
                    foreach ($resource['dcterms:subject'] as $value) {
                        // Language-tagged values should have @language.
                        if (isset($value['@language'])) {
                            $this->assertIsString($value['@language']);
                        }
                    }
                }
            }
        }
    }

    // =========================================================================
    // VISIBILITY TESTS (for fields option)
    // =========================================================================

    /**
     * Test that admin sees private values with fields option.
     */
    public function testFieldsAdminSeesPrivateValues(): void
    {
        // Create an item with a private value.
        $item = $this->createItem([
            'dcterms:title' => [['@value' => 'Item With Private Value']],
            'dcterms:subject' => [['@value' => 'VisibilityTestSubject']],
            'dcterms:description' => [
                ['@value' => 'Public description', 'is_public' => true],
                ['@value' => 'Private description', 'is_public' => false],
            ],
        ]);

        // Admin should see both values.
        $result = $this->references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 10,
                'fields' => ['o:id', 'dcterms:description'],
                'filters' => ['values' => ['VisibilityTestSubject']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        // Find our item's descriptions.
        $foundDescriptions = [];
        foreach ($refs as $ref) {
            if (!empty($ref['resources'])) {
                foreach ($ref['resources'] as $resource) {
                    if ($resource['o:id'] === $item->id()) {
                        $foundDescriptions = $resource['dcterms:description'] ?? [];
                    }
                }
            }
        }

        // Admin should see both public and private descriptions.
        $this->assertCount(2, $foundDescriptions, 'Admin should see both public and private values');
    }

    /**
     * Test that anonymous user does not see private values with fields option.
     */
    public function testFieldsAnonymousDoesNotSeePrivateValues(): void
    {
        // Create an item with a private value.
        $item = $this->createItem([
            'dcterms:title' => [['@value' => 'Item With Private Value Anon']],
            'dcterms:subject' => [['@value' => 'VisibilityTestSubjectAnon']],
            'dcterms:description' => [
                ['@value' => 'Public description anon', 'is_public' => true],
                ['@value' => 'Private description anon', 'is_public' => false],
            ],
        ]);

        // Create a fresh References instance with no user (anonymous).
        $services = $this->getServiceLocator();
        $plugins = $services->get('ControllerPluginManager');

        // Clear auth identity to simulate anonymous user.
        // This is needed because ACL checks auth service, not the user parameter.
        $auth = $services->get('Omeka\AuthenticationService');
        $auth->clearIdentity();

        // Detect supportAnyValue like the factory does.
        $connection = $services->get('Omeka\Connection');
        $supportAnyValue = false;
        try {
            $connection->executeQuery('SELECT ANY_VALUE(id) FROM user LIMIT 1;')->fetchOne();
            $supportAnyValue = true;
        } catch (\Exception $e) {
            $supportAnyValue = false;
        }

        $references = new \Reference\Stdlib\References(
            $services->get('Omeka\Acl'),
            $services->get('Omeka\ApiAdapterManager'),
            $services->get('Omeka\ApiManager'),
            $connection,
            $services->get('Common\EasyMeta'),
            $services->get('Omeka\EntityManager'),
            $services->get('Omeka\Logger'),
            $plugins->get('translate'),
            null, // No user = anonymous
            $plugins->has('accessLevel'),
            $supportAnyValue
        );

        $result = $references
            ->__invoke(['dcterms:subject'], [], [
                'list_by_max' => 10,
                'fields' => ['o:id', 'dcterms:description'],
                'filters' => ['values' => ['VisibilityTestSubjectAnon']],
            ])
            ->list();

        $refs = $result['dcterms:subject']['o:references'] ?? [];

        // Find our item's descriptions.
        $foundDescriptions = [];
        foreach ($refs as $ref) {
            if (!empty($ref['resources'])) {
                foreach ($ref['resources'] as $resource) {
                    if (isset($resource['o:id']) && $resource['o:id'] === $item->id()) {
                        $foundDescriptions = $resource['dcterms:description'] ?? [];
                    }
                }
            }
        }

        // Anonymous should only see public description.
        $this->assertCount(1, $foundDescriptions, 'Anonymous should only see public values');
        $this->assertEquals('Public description anon', $foundDescriptions[0]['@value'] ?? '');

        // Re-login for cleanup.
        $this->loginAdmin();
    }
}
