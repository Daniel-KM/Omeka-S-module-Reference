<?php declare(strict_types=1);

namespace ReferenceTest\View;

use CommonTest\AbstractHttpControllerTestCase;
use ReferenceTest\ReferenceTestTrait;

/**
 * Tests for the template of the skip links (list of initials).
 */
class SkiplinksTest extends AbstractHttpControllerTestCase
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
     * @dataProvider activeInitialProvider
     */
    public function testActiveInitial(string $begin, string $expected): void
    {
        $this->dispatch('/s/' . $this->getTestSite()->slug() . '/reference', 'GET', $begin === '' ? [] : ['begin' => $begin]);

        $html = $this->getApplication()->getServiceManager()->get('ViewRenderer')
            ->partial('common/reference-skiplinks', [
                'options' => ['by_initial' => true],
                'references' => [],
                'initials' => ['A' => 1, 'B' => 2, 'C' => 3],
            ]);

        $this->assertSame(1, substr_count($html, ' active"'));
        $this->assertMatchesRegularExpression(
            '~<li class="pagination-range active"><a href="[^"]*\?begin=' . $expected . '">' . $expected . '</a></li>~',
            $html
        );
    }

    public function activeInitialProvider(): array
    {
        return [
            'lower case' => ['b', 'B'],
            'upper case' => ['C', 'C'],
            'default' => ['', 'A'],
        ];
    }
}
