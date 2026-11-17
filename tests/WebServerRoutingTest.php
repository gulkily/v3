<?php

declare(strict_types=1);

final class WebServerRoutingTest
{
    public function testDevelopmentRouterOnlyBypassesPresentationAssets(): void
    {
        $router = (string) file_get_contents(__DIR__ . '/../public/router.php');

        assertStringContains("str_starts_with(\$path, '/assets/')", $router);
        assertStringContains("'/favicon.ico'", $router);
        assertStringNotContains("'/manifest.webmanifest'", $router);
        assertStringNotContains("'/service_worker.js'", $router);
        assertStringContains('if ($isPublicAsset && is_file($file))', $router);
        assertStringNotContains("if (\$path !== '/' && is_file(\$file))", $router);
    }

    public function testApacheRoutesContentThroughFlagAndMembershipGate(): void
    {
        $rewrite = (string) file_get_contents(__DIR__ . '/../public/.htaccess');

        assertStringContains('RewriteCond %{REQUEST_URI} ^/(?:assets/[^/]+\.(?:css|js|ico|png|jpe?g|gif|svg|webp|woff2?)|favicon\.ico)$ [NC]', $rewrite);
        assertStringNotContains('^/(?:assets(?:/|$)|favicon', $rewrite);
        assertStringContains('RewriteRule ^\\.static(?:/|$) - [F,L]', $rewrite);
        assertStringContains('RewriteRule ^ index.php [L]', $rewrite);
        assertStringNotContains('%{ENV:FORUM_APPROVED_MEMBERS_ONLY}', $rewrite);
        assertStringNotContains('RewriteRule ^.* .static/current', $rewrite);
    }

    public function testShippedRoutingCarriesHighTrafficRulesSoTheModeNeverEditsIt(): void
    {
        $rewrite = (string) file_get_contents(__DIR__ . '/../public/.htaccess');

        assertTrue(str_starts_with($rewrite, \ForumRewrite\Host\HighTrafficMode::routingRules()));
        assertStringContains('public/.high-traffic', (string) file_get_contents(__DIR__ . '/../.gitignore'));
    }

    public function testApacheAssetBypassAdmitsOnlyPresentationFiles(): void
    {
        $rewrite = (string) file_get_contents(__DIR__ . '/../public/.htaccess');
        assertTrue(preg_match('~^RewriteCond %\{REQUEST_URI\} (\S+) \[NC\]$~m', $rewrite, $matches) === 1);
        $condition = '~' . $matches[1] . '~i';

        foreach (['/assets/site.css', '/assets/browser_signing.js', '/assets/favicon-qdb.ico', '/assets/logo.PNG', '/assets/font.woff2', '/favicon.ico'] as $path) {
            assertTrue(preg_match($condition, $path) === 1, $path);
        }
        foreach (['/assets', '/assets/', '/assets/1111.html', '/assets/tour/', '/assets/tour/board.html', '/assets/openpgp.min.js.map',
            '/assets/sqlite_query_catalog.sql', '/assets/sql-wasm.wasm', '/assets/openpgp.LICENSE', '/assets/sub/site.css'] as $path) {
            assertTrue(preg_match($condition, $path) === 0, $path);
        }
    }
}
