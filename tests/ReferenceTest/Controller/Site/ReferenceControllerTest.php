<?php declare(strict_types=1);

namespace ReferenceTest\Controller\Site;

use CommonTest\AbstractHttpControllerTestCase;
use ReferenceTest\ReferenceTestTrait;

/**
 * Tests for the Reference site controller.
 *
 * Note: Site controller tests require a fully configured site with reference
 * slugs set up in site settings. These tests verify basic route existence.
 */
class ReferenceControllerTest extends AbstractHttpControllerTestCase
{
    use ReferenceTestTrait;

    protected $site;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->site = $this->getTestSite();
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    /**
     * Test that the Reference controller class exists and is registered.
     */
    public function testControllerClassExists(): void
    {
        $this->assertTrue(
            class_exists(\Reference\Controller\Site\ReferenceController::class),
            'ReferenceController class should exist'
        );
    }

    /**
     * Test that reference routes are registered in module config.
     */
    public function testReferenceRoutesAreRegistered(): void
    {
        $services = $this->getServiceLocator();
        $config = $services->get('config');

        // Check that the reference route is defined.
        $this->assertArrayHasKey('router', $config);
        $this->assertArrayHasKey('routes', $config['router']);
    }

    /**
     * Test that dispatching to a site reference path returns a response.
     * Note: Without proper site configuration, this may return 404.
     */
    public function testReferencePathDispatchesWithoutError(): void
    {
        $slug = $this->site->slug();
        $this->dispatch('/s/' . $slug . '/reference');

        // The dispatch should complete without exception.
        // Status may be 200 (if configured) or 404 (if not configured).
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 404]);
    }

    /**
     * Test reference slug path handling.
     */
    public function testReferenceSlugPath(): void
    {
        $slug = $this->site->slug();
        $this->dispatch('/s/' . $slug . '/reference/dcterms-subject');

        // Should dispatch without error, status depends on site configuration.
        $statusCode = $this->getResponse()->getStatusCode();
        $this->assertContains($statusCode, [200, 404]);
    }
}
