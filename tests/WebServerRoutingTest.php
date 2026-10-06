<?php

declare(strict_types=1);

final class WebServerRoutingTest
{
    public function testDevelopmentRouterOnlyBypassesPresentationAssets(): void
    {
        $router = (string) file_get_contents(__DIR__ . '/../public/router.php');

        assertStringContains("str_starts_with(\$path, '/assets/')", $router);
        assertStringContains("'/favicon.ico'", $router);
        assertStringContains("'/manifest.webmanifest'", $router);
        assertStringContains("'/service_worker.js'", $router);
        assertStringContains('if ($isPublicAsset && is_file($file))', $router);
        assertStringNotContains("if (\$path !== '/' && is_file(\$file))", $router);
    }

    public function testApacheServesOnlyEligibleQdbStaticArtifactsBeforeTheFrontController(): void
    {
        $rewrite = (string) file_get_contents(__DIR__ . '/../public/.htaccess');

        assertStringContains('RewriteRule ^\\.static(?:/|$) - [F,L]', $rewrite);
        assertStringContains('RewriteCond %{REQUEST_URI} ^/(?:assets(?:/|$)|favicon\.ico|manifest\.webmanifest|service_worker\.js)$ [NC]', $rewrite);
        assertStringContains('RewriteCond %{REQUEST_METHOD} ^(?:GET|HEAD)$', $rewrite);
        assertStringContains('RewriteCond %{QUERY_STRING} ^$', $rewrite);
        assertStringContains('RewriteCond %{HTTP:Cookie} ^$', $rewrite);
        assertStringContains('RewriteCond %{DOCUMENT_ROOT}/.static/current/latest.html -f', $rewrite);
        assertStringContains('RewriteRule ^(latest|top|leetness)/?$ .static/current/$1.html [END]', $rewrite);
        assertStringContains('RewriteRule ^threads/([A-Za-z0-9._:-]+)/?$ .static/current/threads/$1.html [END]', $rewrite);
        assertStringContains('RewriteRule ^([1-9][0-9]*)/?$ .static/current/qdb/quotes/$1.html [END]', $rewrite);
        assertStringContains('RewriteRule ^ index.php [L]', $rewrite);
        assertStringNotContains('%{ENV:FORUM_APPROVED_MEMBERS_ONLY}', $rewrite);
        assertStringNotContains('RewriteRule ^.* .static/current', $rewrite);
    }
}
