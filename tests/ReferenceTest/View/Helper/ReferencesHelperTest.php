<?php declare(strict_types=1);

namespace ReferenceTest\View\Helper;

use CommonTest\AbstractHttpControllerTestCase;
use ReferenceTest\ReferenceTestTrait;

/**
 * Tests for the References view helper.
 *
 * The view helper provides list(), count(), and initials() methods
 * that take metadata as the first parameter.
 */
class ReferencesHelperTest extends AbstractHttpControllerTestCase
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
     * Test that the view helper is accessible.
     */
    public function testViewHelperIsAccessible(): void
    {
        $references = $this->getReferencesViewHelper();
        $this->assertNotNull($references);
        $this->assertInstanceOf(\Reference\View\Helper\References::class, $references);
    }

    /**
     * Test that invoking the helper returns self for chaining.
     */
    public function testInvokeReturnsSelf(): void
    {
        $references = $this->getReferencesViewHelper();
        $result = $references();

        $this->assertSame($references, $result);
    }

    /**
     * Test list method returns array.
     */
    public function testListMethodReturnsArray(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Helper Test Item']],
            'dcterms:subject' => [['@value' => 'Helper Subject']],
        ]);

        $references = $this->getReferencesViewHelper();
        // The view helper list() method takes metadata directly.
        $result = $references()->list('dcterms:subject');

        $this->assertIsArray($result);
    }

    /**
     * Test list method with multiple properties.
     */
    public function testListMethodWithMultipleProperties(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Multi Property Item']],
            'dcterms:subject' => [['@value' => 'Subject Value']],
            'dcterms:creator' => [['@value' => 'Creator Value']],
        ]);

        $references = $this->getReferencesViewHelper();
        $result = $references()->list(['dcterms:subject', 'dcterms:creator']);

        $this->assertIsArray($result);
    }

    /**
     * Test count method returns integer for single metadata.
     */
    public function testCountMethodReturnsInteger(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Count Test Item']],
            'dcterms:subject' => [['@value' => 'Count Subject']],
        ]);

        $references = $this->getReferencesViewHelper();
        // For a single metadata string, count returns an integer.
        $count = $references()->count('dcterms:subject');

        $this->assertIsInt($count);
    }

    /**
     * Test count method returns array for multiple metadata.
     */
    public function testCountMethodReturnsArrayForMultiple(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Count Test Item']],
            'dcterms:subject' => [['@value' => 'Count Subject']],
            'dcterms:creator' => [['@value' => 'Count Creator']],
        ]);

        $references = $this->getReferencesViewHelper();
        // For an array of metadata, count returns an array.
        $count = $references()->count(['dcterms:subject', 'dcterms:creator']);

        $this->assertIsArray($count);
    }

    /**
     * Test initials method returns array.
     */
    public function testInitialsMethodReturnsArray(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Initial Test Item']],
            'dcterms:subject' => [['@value' => 'Apple']],
        ]);
        $this->createItem([
            'dcterms:title' => [['@value' => 'Initial Test Item 2']],
            'dcterms:subject' => [['@value' => 'Banana']],
        ]);

        $references = $this->getReferencesViewHelper();
        $result = $references()->initials('dcterms:subject');

        $this->assertIsArray($result);
    }
}
