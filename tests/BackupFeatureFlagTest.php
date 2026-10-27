<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\PrivateConfigSchema;

final class BackupFeatureFlagTest
{
    public function testTextDefaultsAndEnvironmentPrecedence(): void
    {
        foreach ([FeatureFlagRegistry::TEXT_BACKUP_ENABLED, FeatureFlagRegistry::IMAGE_BACKUP_ENABLED] as $key) {
        $previous = getenv($key);
        try {
            putenv($key);
            assertTrue((new FeatureFlagEvaluator())->isEnabled($key));
            assertSame(false, (new FeatureFlagEvaluator(siteValues: [$key => false]))->isEnabled($key));
            putenv($key . '=true');
            $state = (new FeatureFlagEvaluator(siteValues: [$key => false]))->evaluate($key);
            assertTrue($state->effectiveValue);
            assertTrue($state->isLocked());
            assertTrue(in_array($key, PrivateConfigSchema::environmentKeys(), true));
            assertSame(false, array_key_exists($key, PrivateConfigSchema::templateDefaults()));
        } finally { putenv($previous === false ? $key : $key . '=' . $previous); }
        }
    }

    public function testTextDownloadDenialAndRecoveryIncludesWarmArchivesAndViewer(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source', true); $w->git($root);
        foreach (['/downloads/repository.tar.gz', '/downloads/repository.zip', '/downloads/read_model.sqlite3'] as $path) {
            $enabled = $this->request($w, $root, $path, true);
            assertStringContains('HTTP_STATUS=200', $enabled);
            $denied = $this->request($w, $root, $path . '?revision=' . str_repeat('0', 40), false);
            assertSame("Backup unavailable.\n\nHTTP_STATUS=403", $denied);
            assertStringContains('HTTP_STATUS=200', $this->request($w, $root, $path, true));
        }
        $page = $this->request($w, $root, '/tools/backup/', false);
        assertStringNotContains('href="/downloads/repository', $page);
        assertStringNotContains('href="/downloads/read_model', $page);
        foreach (['/tools/sqlite/', '/tools/codebase/'] as $path) {
            $page = $this->request($w, $root, $path, false);
            assertStringNotContains('data-sqlite-viewer', $page);
            assertStringNotContains('href="/downloads/read_model', $page);
            assertStringContains('unavailable', $page);
        }
        assertStringContains('data-sqlite-viewer', $this->request($w, $root, '/tools/sqlite/', true));
        // Text denial does not disable the independent image archive path.
        assertStringContains('HTTP_STATUS=200', $this->request($w, $root, '/downloads/images.tar', false));
    }

    public function testIndependentImagePolicyPreservesRetainedImagesAndArchives(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source', true); $w->git($root);
        mkdir($w->root . '/images');
        $png = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aX1sAAAAASUVORK5CYII=');
        $w->put($w->root . '/pixel.png', $png);
        $ref = \ForumRewrite\Images\ImageStore::inspect($w->root . '/pixel.png', '');
        $store = new \ForumRewrite\Images\ImageStore($root, $w->root . '/images');
        $store->put($w->root . '/pixel.png', $ref);
        $retained = $store->resolve($ref);
        foreach ([true, false] as $text) {
            foreach ([true, false, true] as $images) {
                $body = $this->request($w, $root, '/downloads/images.tar', $text, $images);
                assertStringContains('HTTP_STATUS=' . ($images ? '200' : '403'), $body);
                $body = $this->request($w, $root, '/downloads/repository.zip', $text, $images);
                assertStringContains('HTTP_STATUS=' . ($text ? '200' : '403'), $body);
                $page = $this->request($w, $root, '/tools/backup/', $text, $images);
                assertSame($images, str_contains($page, 'href="/downloads/images.tar'));
                assertSame($text, str_contains($page, 'href="/downloads/repository.zip'));
                if ($images && !$text) { assertStringContains('requires a matching content backup', $page); }
                assertSame($png, file_get_contents($retained));
            }
        }
        // Refusal precedes revision validation even for a previously built archive.
        assertSame("Backup unavailable.\n\nHTTP_STATUS=403", $this->request($w, $root,
            '/downloads/images.tar?revision=' . str_repeat('0', 40), true, false));
        assertTrue(count(glob($w->root . '/archive-exports/*/metadata')) > 0);
    }

    public function testNavigationAliasesAndStaticPolicyTransitions(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source', true); $w->git($root);
        foreach ([true, false, true] as $enabled) {
            foreach (['/instance', '/instance/', '/backup', '/backup/', '/tools/backup', '/tools/backup/', '/tools/'] as $path) {
                $page = $this->request($w, $root, $path, $enabled, $enabled);
                assertSame($enabled, str_contains($page, 'href="/tools/backup/"'), $path . ' navigation');
                if (!$enabled && $path !== '/tools/') assertStringContains('Backups are unavailable.', $page);
            }
            $nav = $this->request($w, $root, '/api/feature_flags/navigation', $enabled, $enabled);
            $state = json_decode(explode("\nHTTP_STATUS=", $nav)[0], true);
            assertSame($enabled, str_contains($state['tools_html'], 'href="/tools/backup/"'), 'API navigation');
        }
        $static = $w->root . '/static'; mkdir($static . '/current/instance', 0700, true);
        $w->put($static . '/current/instance/index.html', '<body data-backup-state="11">CACHED BACKUP PAGE</body>');
        $code = 'require $argv[1]."/autoload.php"; (new ForumRewrite\\Host\\FrontController($argv[1],$argv[2],$argv[3],$argv[4]))->handle("GET","/tools/backup/");';
        foreach ([true, false, true] as $enabled) {
            [$status, $html] = $w->command(['env', 'FORUM_APPROVED_MEMBERS_ONLY=false', 'FORUM_IMAGE_CONTENT_ENABLED=false',
                'FORUM_TEXT_BACKUP_ENABLED=' . ($enabled ? 'true' : 'false'), 'FORUM_IMAGE_BACKUP_ENABLED=true',
                PHP_BINARY, '-r', $code, dirname(__DIR__), $root, $w->root . '/index.sqlite3', $static]);
            assertSame(0, $status, $html);
            assertSame($enabled, str_contains($html, 'CACHED BACKUP PAGE'), 'static policy');
        }
    }

    public function testBusyHighTrafficCacheNeverReturnsWrongPolicy(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source');
        $cache = new \ForumRewrite\Host\HighTrafficCache($w->root, $root, $w->root . '/index.sqlite3', $w->root . '/public');
        $cache->initialize(); $cache->limits['wait_ms'] = 1;
        $cache->atomic($cache->path('/'), '<body data-backup-state="11">WARM</body>');
        $lock = fopen($cache->root . '/locks/' . substr(hash('sha256', '/'), 0, 3) . '.lock', 'c'); flock($lock, LOCK_EX);
        $key = FeatureFlagRegistry::TEXT_BACKUP_ENABLED; $old = getenv($key);
        try {
            putenv($key . '=true'); assertStringContains('WARM', $cache->generate('/'));
            putenv($key . '=false'); assertSame(null, $cache->generate('/'));
            putenv($key . '=true'); assertStringContains('WARM', $cache->generate('/'));
        } finally { fclose($lock); putenv($old === false ? $key : $key . '=' . $old); }
    }

    private function request(ImportTestWorkspace $w, string $root, string $path, bool $text, bool $images = true): string
    {
        $code = 'require $argv[1]."/autoload.php"; http_response_code(200); (new ForumRewrite\\Application($argv[1],$argv[2],$argv[3]))->handle("GET",$argv[4]); fwrite(STDOUT,"\nHTTP_STATUS=".http_response_code());';
        [$status, $body] = $w->command(['env', 'FORUM_APPROVED_MEMBERS_ONLY=false',
            'FORUM_TEXT_BACKUP_ENABLED=' . ($text ? 'true' : 'false'),
            'FORUM_IMAGE_BACKUP_ENABLED=' . ($images ? 'true' : 'false'),
            'FORUM_IMAGE_CONTENT_ENABLED=false',
            'FORUM_IMAGE_ROOT=' . $w->root . '/images', PHP_BINARY, '-r', $code,
            dirname(__DIR__), $root, $w->root . '/index.sqlite3', $path]);
        assertSame(0, $status, $body);
        return $body;
    }
}
