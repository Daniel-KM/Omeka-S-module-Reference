<?php declare(strict_types=1);

/**
 * Bootstrap file for module tests.
 *
 * Use Common module Bootstrap helper for test setup.
 */

require dirname(__DIR__, 3) . '/modules/Common/tests/Bootstrap.php';

\CommonTest\Bootstrap::bootstrap(
    [
        'Common',
        'Reference',
        // Optional: registered globally via STI on resource, so its table must
        // exist for resource value queries (linked resources).
        '?DigitalObject',
        // Optional: references of the access statuses.
        '?Access',
    ],
    'ReferenceTest',
    __DIR__ . '/ReferenceTest'
);
