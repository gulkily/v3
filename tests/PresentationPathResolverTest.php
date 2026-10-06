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
        $profiles = SiteProfileRegistry::all();

        assertSame('/srv/forum/state/static_html', PresentationPathResolver::staticHtmlRoot($projectRoot, $profiles['zenmemes']));
        assertSame('/srv/forum/state/static_html_chouse', PresentationPathResolver::staticHtmlRoot($projectRoot, $profiles['chouse']));
        assertSame('/srv/forum/state/static_html_qdb', PresentationPathResolver::staticHtmlRoot($projectRoot, $profiles['qdb']));
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
