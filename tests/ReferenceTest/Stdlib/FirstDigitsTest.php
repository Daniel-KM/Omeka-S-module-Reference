<?php declare(strict_types=1);

namespace ReferenceTest\Stdlib;

use CommonTest\AbstractHttpControllerTestCase;
use ReferenceTest\ReferenceTestTrait;

/**
 * Tests for the `first_digits` option with numeric values (decade/century grouping).
 *
 * @group stdlib
 */
class FirstDigitsTest extends AbstractHttpControllerTestCase
{
    use ReferenceTestTrait;

    /**
     * @var \Reference\Stdlib\References
     */
    protected $references;

    protected array $testItems = [];

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

    protected function createTestData(): void
    {
        // Items spanning multiple decades and centuries.
        $dates = [
            '2014-10-12',
            '2019-03-20',
            '2021-01-10',
            '1999-12-31',
            '1850-06-15',
            '1867-04-01',
        ];
        foreach ($dates as $date) {
            $this->testItems[] = $this->createItem([
                'dcterms:title' => [['@value' => "Item $date"]],
                'dcterms:date' => [['@value' => $date]],
            ]);
        }

        // Negative year item.
        $this->testItems[] = $this->createItem([
            'dcterms:title' => [['@value' => 'Ancient item']],
            'dcterms:date' => [['@value' => '-500-10-12']],
        ]);

        // Another negative year for grouping.
        $this->testItems[] = $this->createItem([
            'dcterms:title' => [['@value' => 'Ancient item 2']],
            'dcterms:date' => [['@value' => '-523-03-01']],
        ]);
    }

    /**
     * Test first_digits = true extracts full year (backward compat).
     */
    public function testFirstDigitsTrueExtractsFullYear(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:date'], [], [
                'first_digits' => true,
            ])
            ->list();

        $refs = $result['dcterms:date']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        $values = array_column($refs, 'val');
        // Should contain full years.
        $this->assertContains(2014, $values);
        $this->assertContains(1999, $values);
        $this->assertContains(1850, $values);
        $this->assertContains(-500, $values);
    }

    /**
     * Test first_digits = 3 groups by decade (first 3 digits).
     *
     * "2014" -> 201, "2019" -> 201, "2021" -> 202, "1999" -> 199, "1850" -> 185, "1867" -> 186
     */
    public function testFirstDigits3GroupsByDecade(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:date'], [], [
                'first_digits' => 3,
            ])
            ->list();

        $refs = $result['dcterms:date']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        $values = array_column($refs, 'val');
        // 2014 and 2019 should both map to 201.
        $this->assertContains(201, $values);
        // 2021 -> 202
        $this->assertContains(202, $values);
        // 1999 -> 199
        $this->assertContains(199, $values);
        // 1850 -> 185
        $this->assertContains(185, $values);

        // 2014 and 2019 are in the same decade group, so count >= 2.
        foreach ($refs as $ref) {
            if ($ref['val'] == 201) {
                $this->assertGreaterThanOrEqual(2, $ref['total']);
                break;
            }
        }
    }

    /**
     * Test first_digits = 2 groups by century (first 2 digits).
     *
     * "2014" -> 20, "1999" -> 19, "1850" -> 18, "-500" -> -50
     */
    public function testFirstDigits2GroupsByCentury(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:date'], [], [
                'first_digits' => 2,
            ])
            ->list();

        $refs = $result['dcterms:date']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        $values = array_column($refs, 'val');
        // All 20xx dates -> 20
        $this->assertContains(20, $values);
        // 1999 -> 19
        $this->assertContains(19, $values);
        // 1850, 1867 -> 18
        $this->assertContains(18, $values);

        // 20xx group should have 2014, 2019, 2021 = at least 3 items.
        foreach ($refs as $ref) {
            if ($ref['val'] == 20) {
                $this->assertGreaterThanOrEqual(3, $ref['total']);
                break;
            }
        }
    }

    /**
     * Test first_digits = 1 groups by millennium (first digit).
     *
     * "2014" -> 2, "1999" -> 1, "-500" -> -5
     */
    public function testFirstDigits1GroupsByMillennium(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:date'], [], [
                'first_digits' => 1,
            ])
            ->list();

        $refs = $result['dcterms:date']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        $values = array_column($refs, 'val');
        $this->assertContains(2, $values);
        $this->assertContains(1, $values);
        $this->assertContains(-5, $values);
    }

    /**
     * Test negative years are handled correctly with first_digits = 2.
     *
     * "-500" -> -50, "-523" -> -52
     */
    public function testNegativeYearsWithFirstDigits2(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:date'], [], [
                'first_digits' => 2,
            ])
            ->list();

        $refs = $result['dcterms:date']['o:references'] ?? [];
        $values = array_column($refs, 'val');

        // -500 -> first 2 digits of 500 = 50, then negate -> -50
        $this->assertContains(-50, $values);
        // -523 -> first 2 digits of 523 = 52, then negate -> -52
        $this->assertContains(-52, $values);
    }

    /**
     * Test negative years are handled correctly with first_digits = 3.
     *
     * "-500" -> -500, "-523" -> -523 (full 3 digits)
     */
    public function testNegativeYearsWithFirstDigits3(): void
    {
        $result = $this->references
            ->__invoke(['dcterms:date'], [], [
                'first_digits' => 3,
            ])
            ->list();

        $refs = $result['dcterms:date']['o:references'] ?? [];
        $values = array_column($refs, 'val');

        $this->assertContains(-500, $values);
        $this->assertContains(-523, $values);
    }

    /**
     * Test first_digits with locale filter.
     */
    public function testFirstDigitsWithLocale(): void
    {
        // Create items with language-tagged dates.
        $this->createItem([
            'dcterms:title' => [['@value' => 'French dated item']],
            'dcterms:date' => [['@value' => '2014-05-01', '@language' => 'fra']],
        ]);
        $this->createItem([
            'dcterms:title' => [['@value' => 'English dated item']],
            'dcterms:date' => [['@value' => '2019-05-01', '@language' => 'eng']],
        ]);

        $result = $this->references
            ->__invoke(['dcterms:date'], [], [
                'first_digits' => 3,
                'locale' => ['fra'],
            ])
            ->list();

        $refs = $result['dcterms:date']['o:references'] ?? [];
        $this->assertNotEmpty($refs);

        $values = array_column($refs, 'val');
        $this->assertContains(201, $values);
    }
}
