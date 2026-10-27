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
        $key = FeatureFlagRegistry::TEXT_BACKUP_ENABLED;
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

    private function request(ImportTestWorkspace $w, string $root, string $path, bool $text): string
    {
        $code = 'require $argv[1]."/autoload.php"; http_response_code(200); (new ForumRewrite\\Application($argv[1],$argv[2],$argv[3]))->handle("GET",$argv[4]); fwrite(STDOUT,"\nHTTP_STATUS=".http_response_code());';
        [$status, $body] = $w->command(['env', 'FORUM_APPROVED_MEMBERS_ONLY=false',
            'FORUM_TEXT_BACKUP_ENABLED=' . ($text ? 'true' : 'false'),
            'FORUM_IMAGE_ROOT=' . $w->root . '/images', PHP_BINARY, '-r', $code,
            dirname(__DIR__), $root, $w->root . '/index.sqlite3', $path]);
        assertSame(0, $status, $body);
        return $body;
    }
}
