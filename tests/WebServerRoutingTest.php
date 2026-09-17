<?php

declare(strict_types=1);

final class WebServerRoutingTest
{
    public function testDevelopmentRouterOnlyBypassesPresentationAssets(): void
    {
        $router = (string) file_get_contents(__DIR__ . '/../public/router.php');

        assertStringContains("str_starts_with(\$path, '/assets/')", $router);
        assertStringContains("\$path === '/favicon.ico'", $router);
        assertStringContains('if ($isPublicAsset && is_file($file))', $router);
        assertStringNotContains("if (\$path !== '/' && is_file(\$file))", $router);
    }

    public function testApacheRoutesAllNonAssetsThroughFrontController(): void
    {
        $rewrite = (string) file_get_contents(__DIR__ . '/../public/.htaccess');

        assertStringContains('RewriteCond %{REQUEST_URI} ^/(?:assets(?:/|$)|favicon\.ico$) [NC]', $rewrite);
        assertStringContains('RewriteRule ^ index.php [L]', $rewrite);
        assertStringNotContains('%{ENV:FORUM_APPROVED_MEMBERS_ONLY}', $rewrite);
        assertStringNotContains('%{DOCUMENT_ROOT}/index.html', $rewrite);
        assertStringNotContains('RewriteRule ^(threads|posts|profiles)', $rewrite);
    }
}
