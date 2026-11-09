<?php declare(strict_types=1);

namespace ReferenceTest\View;

use CommonTest\AbstractHttpControllerTestCase;
use Laminas\Router\Http\Segment;
use ReferenceTest\ReferenceTestTrait;

/**
 * Tests the links of the references to a search page.
 *
 * The module AdvancedSearch is optional, so the route of the search page and
 * the helper that gets the search config are simulated.
 */
class SearchConfigLinksTest extends AbstractHttpControllerTestCase
{
    use ReferenceTestTrait;

    protected $site;

    public function setUp(): void
    {
        parent::setUp();
        $this->loginAdmin();
        $this->site = $this->getTestSite();

        // Set the route match with the site slug.
        $this->dispatch('/s/' . $this->site->slug() . '/item');

        $services = $this->getApplication()->getServiceManager();
        $services->get('Router')->addRoute('search-page-test', Segment::factory([
            'route' => '/s/:site-slug/test-search',
        ]));
        $viewHelpers = $services->get('ViewHelperManager');
        $viewHelpers->setAllowOverride(true);
        $viewHelpers->setService('getSearchConfig', new class {
            public function __invoke($slug = null)
            {
                return new class {
                    public function slug(): string
                    {
                        return 'test';
                    }
                };
            }
        });
        $viewHelpers->setAllowOverride(false);
    }

    public function tearDown(): void
    {
        $this->cleanupResources();
        parent::tearDown();
    }

    public function testListLinksHaveNoSiteAndStartWithFilter(): void
    {
        $this->createSiteItem(['dcterms:subject' => 'ZzRef Alpha']);
        $this->createSiteItem(['dcterms:subject' => 'ZzRef Alpha']);

        $queries = $this->searchQueries($this->references()->displayListForFields(
            ['fields' => ['dcterms:subject']],
            ['site_id' => $this->site->id()],
            $this->options()
        ));

        $query = $this->queryForValue($queries, 'ZzRef Alpha');
        $this->assertArrayNotHasKey('site_id', $query);
        $this->assertSame(['join' => 'or', 'field' => 'dcterms:subject', 'type' => 'eq', 'val' => 'ZzRef Alpha'], $query['filter'][0]);
    }

    public function testEmptyTitleLinkUsesFilter(): void
    {
        $classId = (int) $this->getServiceLocator()->get('Common\EasyMeta')->resourceClassId('dctype:Text');
        $this->createSiteItem([], $classId);
        $this->createSiteItem([], $classId);

        $queries = $this->searchQueries($this->references()->displayListForFields(
            ['fields' => ['dctype:Text']],
            ['site_id' => $this->site->id()],
            ['include_without_meta' => true] + $this->options()
        ));

        $emptyQueries = array_filter($queries, fn ($query) => ($query['filter'][0]['type'] ?? null) === 'nex');
        $this->assertNotEmpty($emptyQueries);
        foreach ($emptyQueries as $query) {
            $this->assertArrayNotHasKey('site_id', $query);
            $this->assertArrayNotHasKey('property', $query);
            $this->assertSame([$classId], array_map('intval', $query['resource_class_id']));
            $this->assertSame(['join' => 'and', 'field' => 'dcterms:title', 'type' => 'nex'], $query['filter'][0]);
        }
    }

    public function testTreeLinksHaveNoSiteAndStartWithFilter(): void
    {
        $this->createSiteItem(['dcterms:subject' => 'ZzRef Parent']);
        $this->createSiteItem(['dcterms:subject' => 'ZzRef Child']);
        $this->createSiteItem(['dcterms:subject' => 'ZzRef Child']);

        // As in the block, the template reads the query from the options.
        $query = ['site_id' => $this->site->id()];
        $queries = $this->searchQueries($this->references()->displayTree(
            "ZzRef Parent\n- ZzRef Child",
            $query,
            ['query' => $query] + $this->options()
        ));

        $this->assertNotEmpty($queries);
        foreach ($queries as $query) {
            $this->assertArrayNotHasKey('site_id', $query);
            $this->assertArrayNotHasKey('property', $query);
            $this->assertSame('or', $query['filter'][0]['join']);
            $this->assertSame('dcterms:subject', $query['filter'][0]['field']);
        }
        $query = $this->queryForValue($queries, 'ZzRef Child');
        $this->assertSame('eq', $query['filter'][0]['type']);
    }

    /**
     * Without search page (no module AdvancedSearch), the links are standard
     * Omeka queries limited to the site.
     */
    public function testLinksWithoutSearchConfigUseProperty(): void
    {
        $this->createSiteItem(['dcterms:subject' => 'ZzRef Alpha']);
        $this->createSiteItem(['dcterms:subject' => 'ZzRef Alpha']);
        $this->createSiteItem(['dcterms:subject' => 'ZzRef Parent']);
        $query = ['site_id' => $this->site->id()];
        $options = ['search_config' => null] + $this->options();

        $html = $this->references()->displayListForFields(['fields' => ['dcterms:subject']], $query, $options)
            . $this->references()->displayTree('ZzRef Parent', $query, ['query' => $query] + $options);

        $this->assertStringNotContainsString('/test-search', $html);
        preg_match_all('~href="([^"]*/s/' . preg_quote($this->site->slug(), '~') . '/item\?[^"]*)"~', $html, $matches);
        $values = [];
        foreach ($matches[1] as $href) {
            parse_str((string) parse_url(html_entity_decode($href), PHP_URL_QUERY), $linkQuery);
            $this->assertEquals($this->site->id(), $linkQuery['site_id']);
            $this->assertArrayNotHasKey('filter', $linkQuery);
            $this->assertSame('or', $linkQuery['property'][0]['joiner']);
            $values[] = $linkQuery['property'][0]['text'];
        }
        $this->assertContains('ZzRef Alpha', $values);
        $this->assertContains('ZzRef Parent', $values);
    }

    protected function references(): \Reference\View\Helper\References
    {
        return $this->getApplication()->getServiceManager()->get('ViewHelperManager')->get('references');
    }

    protected function options(): array
    {
        return [
            'search_config' => 'test',
            'link_to_single' => false,
            'skiplinks' => false,
            'headings' => false,
        ];
    }

    protected function createSiteItem(array $values, ?int $classId = null): void
    {
        $easyMeta = $this->getServiceLocator()->get('Common\EasyMeta');
        $data = ['o:site' => [$this->site->id()]];
        if ($classId) {
            $data['o:resource_class'] = ['o:id' => $classId];
        }
        foreach ($values as $term => $value) {
            $data[$term][] = ['type' => 'literal', 'property_id' => $easyMeta->propertyId($term), '@value' => $value];
        }
        $item = $this->api()->create('items', $data)->getContent();
        $this->createdResources[] = ['type' => 'items', 'id' => $item->id()];
    }

    /**
     * Get the queries of the links to the search page.
     */
    protected function searchQueries(string $html): array
    {
        preg_match_all('~href="([^"]*/test-search\?[^"]*)"~', $html, $matches);
        return array_map(function ($href) {
            parse_str((string) parse_url(html_entity_decode($href), PHP_URL_QUERY), $query);
            return $query;
        }, $matches[1]);
    }

    protected function queryForValue(array $queries, string $value): array
    {
        foreach ($queries as $query) {
            if (in_array($value, array_column($query['filter'] ?? [], 'val'), true)) {
                return $query;
            }
        }
        $this->fail(sprintf('No link to the search page for "%s".', $value));
    }
}
