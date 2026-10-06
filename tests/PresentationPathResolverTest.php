<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\PresentationPathResolver;
use ForumRewrite\SiteProfileRegistry;

final class PresentationPathResolverTest
{
    public function testStaticHtmlRootsPreserveZenmemesAndIsolateOtherProfiles(): void
    {
        $projectRoot = '/srv/forum';
        $baseRoot = $projectRoot . '/state/static_html';
        $roots = [];

        foreach (SiteProfileRegistry::all() as $profile) {
            $root = PresentationPathResolver::staticHtmlRoot($projectRoot, $profile);
            assertSame(true, $root === $baseRoot || $root === $baseRoot . '_' . $profile['browserNamespace']);
            assertSame(false, isset($roots[$root]));
            $roots[$root] = true;
        }

        assertSame(true, isset($roots[$baseRoot]));
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
            );
        }
    }
}
