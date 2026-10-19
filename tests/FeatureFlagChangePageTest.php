<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class FeatureFlagChangePageTest
{
    private const OPERATOR = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    private const SOURCE = 'records/feature-flag-changes/feature-flag-change-test-001.txt';

    public function testSignedChangeRendersPageAndActivityAndRssLinkToIt(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            $workspace->put($root . '/' . self::SOURCE, "Record-ID: feature-flag-change-test-001\nCreated-At: 2026-01-02T03:04:05Z\nFlag-Key: FORUM_IMAGE_CONTENT_ENABLED\nValue: true\nOperator-Identity-ID: " . self::OPERATOR . "\n\n");
            foreach ([['add', self::SOURCE], ['commit', '-qm', 'Record flag change']] as $args) {
                [$code, $output] = $workspace->command(['git', '-C', $root, ...$args]);
                assertSame(0, $code, $output);
            }
            [, $sha] = $workspace->command(['git', '-C', $root, 'rev-parse', 'HEAD']);
            $sha = trim($sha);
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            $html = $this->render($root, $db, '/tools/feature-flags/changes/feature-flag-change-test-001');
            assertSame(200, http_response_code());
            assertStringContains('<h1>Feature flag change</h1>', $html);
            assertStringContains('FORUM_IMAGE_CONTENT_ENABLED', $html);
            assertStringContains('<strong>Set to:</strong> enabled', $html);
            assertStringContains('class="nav-link is-active" href="/tools/feature-flags/"', $html);
            assertStringContains('href="/source/blob/' . $sha . '/' . self::SOURCE . '"', $html);
            assertStringContains('href="/source/commits/' . $sha . '"', $html);
            assertStringContains('does not re-verify the signature', $html);
            $activity = $this->render($root, $db, '/activity/');
            assertStringContains('<a href="/tools/feature-flags/changes/feature-flag-change-test-001">site feature flags</a>', $activity);
            assertStringNotContains('<a href="/tools/feature-flags/">site feature flags</a>', $activity);
            assertStringContains('/tools/feature-flags/changes/feature-flag-change-test-001', $this->render($root, $db, '/activity/?view=all&format=rss'));
        });
    }

    public function testMissingMalformedAndUnparseableChangesHaveSafeErrors(): void
    {
        $this->fixture(function ($workspace, $root, $db): void {
            $workspace->put($root . '/records/feature-flag-changes/broken.txt', "not a canonical record\n");
            foreach (['/tools/feature-flags/changes/ffc-missing', '/tools/feature-flags/changes/..%2Fx', '/tools/feature-flags/changes/%2e%2e', '/tools/feature-flags/changes/broken'] as $url) {
                assertStringContains('Feature flag change unavailable', $this->render($root, $db, $url));
                assertSame(404, http_response_code());
            }
        });
    }

    private function fixture(callable $test): void
    {
        $workspace = new ImportTestWorkspace();
        $root = $workspace->repository('repository', true);
        $db = $workspace->root . '/read.sqlite3';
        $flag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        $session = $_SESSION ?? [];
        $cookies = $_COOKIE;
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION = []; $_COOKIE = [];
        putenv('FORUM_APPROVED_MEMBERS_ONLY=false');
        try {
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            $workspace->git($root);
            $test($workspace, $root, $db);
        } finally {
            $_SESSION = $session; $_COOKIE = $cookies;
            putenv($flag === false ? 'FORUM_APPROVED_MEMBERS_ONLY' : 'FORUM_APPROVED_MEMBERS_ONLY=' . $flag);
            if (session_status() === PHP_SESSION_ACTIVE) session_write_close();
        }
    }

    private function render(string $root, string $db, string $url): string
    {
        http_response_code(200);
        ob_start();
        try {
            (new Application(dirname(__DIR__), $root, $db))->handle('GET', $url);
            return (string) ob_get_contents();
        } finally { ob_end_clean(); }
    }
}
