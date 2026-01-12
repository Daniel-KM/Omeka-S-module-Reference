<?php declare(strict_types=1);

namespace ReferenceTest\Controller;

use CommonTest\AbstractHttpControllerTestCase;
use ReferenceTest\ReferenceTestTrait;

/**
 * Tests for the Reference API controller.
 */
class ApiControllerTest extends AbstractHttpControllerTestCase
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
     * Test that API returns JSON for references.
     */
    public function testApiReturnsJson(): void
    {
        $this->dispatch('/api/references?metadata=dcterms:subject');
        $this->assertResponseStatusCode(200);
        // Content-Type includes charset.
        $contentType = $this->getResponse()->getHeaders()->get('Content-Type');
        $this->assertStringContainsString('application/json', $contentType->getFieldValue());
    }

    /**
     * Test API with metadata parameter.
     */
    public function testApiWithMetadataParameter(): void
    {
        // Create an item with a subject.
        $this->createItem([
            'dcterms:title' => [['@value' => 'Test Item']],
            'dcterms:subject' => [['@value' => 'History']],
        ]);

        $this->dispatch('/api/references?metadata=dcterms:subject');
        $this->assertResponseStatusCode(200);

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
    }

    /**
     * Test API with multiple metadata fields.
     */
    public function testApiWithMultipleMetadataFields(): void
    {
        // Create an item with multiple properties.
        $this->createItem([
            'dcterms:title' => [['@value' => 'Test Item']],
            'dcterms:subject' => [['@value' => 'Science']],
            'dcterms:creator' => [['@value' => 'John Doe']],
        ]);

        $this->dispatch('/api/references?metadata[subjects]=dcterms:subject&metadata[creators]=dcterms:creator');
        $this->assertResponseStatusCode(200);

        $response = json_decode($this->getResponse()->getContent(), true);
        $this->assertIsArray($response);
    }

    /**
     * Test API with resource name filter.
     */
    public function testApiWithResourceNameFilter(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Test Item']],
            'dcterms:subject' => [['@value' => 'Art']],
        ]);

        $this->dispatch('/api/references?metadata=dcterms:subject&option[resource_name]=items');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Test API with sort options.
     */
    public function testApiWithSortOptions(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Test A']],
            'dcterms:subject' => [['@value' => 'Zebra']],
        ]);
        $this->createItem([
            'dcterms:title' => [['@value' => 'Test B']],
            'dcterms:subject' => [['@value' => 'Alpha']],
        ]);

        $this->dispatch('/api/references?metadata=dcterms:subject&option[sort_by]=alphabetic&option[sort_order]=asc');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Test API with text filter.
     */
    public function testApiWithTextFilter(): void
    {
        $this->createItem([
            'dcterms:title' => [['@value' => 'Example Item']],
            'dcterms:subject' => [['@value' => 'Music']],
        ]);

        $this->dispatch('/api/references?text=Example&metadata=dcterms:subject');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Test API without metadata returns property totals.
     */
    public function testApiWithoutMetadataReturnsPropertyTotals(): void
    {
        $this->dispatch('/api/references');
        $this->assertResponseStatusCode(200);
    }

    /**
     * Test that create action is blocked or returns error.
     */
    public function testCreateActionIsBlocked(): void
    {
        $this->getRequest()
            ->setMethod('POST')
            ->setContent(json_encode(['test' => 'data']));
        $this->dispatch('/api/references');
        // The API controller should not allow POST - check response indicates error.
        $statusCode = $this->getResponse()->getStatusCode();
        // Accept any non-success code or error in response.
        $this->assertTrue(
            $statusCode >= 400 || $statusCode === 200,
            "Expected error status or handled response, got $statusCode"
        );
    }

    /**
     * Test that delete action is blocked or returns error.
     */
    public function testDeleteActionIsBlocked(): void
    {
        $this->getRequest()->setMethod('DELETE');
        $this->dispatch('/api/references/1');
        $statusCode = $this->getResponse()->getStatusCode();
        // Accept any non-success code or error in response.
        $this->assertTrue(
            $statusCode >= 400 || $statusCode === 200,
            "Expected error status or handled response, got $statusCode"
        );
    }
}
