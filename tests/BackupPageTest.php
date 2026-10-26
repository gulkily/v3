<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class BackupPageTest
{
    public function testDownloadsAreUniqueAndRetainEssentialWarnings(): void
    {
        $this->fixture(true, function (string $root, string $db): void {
            foreach (['/instance/', '/backup/', '/tools/backup/'] as $route) {
                $html = $this->render($root, $db, $route);
                foreach (['/downloads/repository.tar.gz', '/downloads/repository.zip', '/downloads/read_model.sqlite3', '/downloads/images.zip'] as $href) {
                    assertSame(1, substr_count($html, 'href="' . $href));
                }
                assertStringContains('Match the content revision.', $html);
                assertStringContains('Public content only; not a full site backup.', $html);
                assertStringContains('(may be out of date)', $html);
                assertStringContains('<strong>Instance:</strong> ' . \ForumRewrite\SiteConfig::displayName(), $html);
                assertStringContains('<strong>Admins:</strong>', $html);
                assertStringContains('/user/guest', $html);
                assertStringContains('<strong>Index generated:</strong>', $html);
                $pdo = new PDO('sqlite:' . $db);
                $generated = $pdo->query("SELECT value FROM metadata WHERE key = 'rebuilt_at'")->fetchColumn();
                $revision = $pdo->query("SELECT value FROM metadata WHERE key = 'repository_head'")->fetchColumn();
                assertStringContains('datetime="' . $generated . '"', $html);
                assertTrue(is_string($revision) && strlen($revision) === 40);
                assertStringContains(substr($revision, 0, 12), $html);
                assertStringContains('Recent included items', $html);
                assertStringContains('class="backup-preview"', $html);
                assertStringContains('href="/posts/reply-001"', $html);
                assertStringContains('Reply body.', $html);
                assertStringContains('href="/activity/">All activity</a>', $html);
                foreach (['Snapshot freshness', 'About these downloads', '<table'] as $removed) {
                    assertStringNotContains($removed, $html);
                }
            }
        });
    }

    public function testImagesLinkAndPairingNoteAreAbsentWhenUnavailable(): void
    {
        $this->fixture(false, function (string $root, string $db): void {
            $html = $this->render($root, $db, '/tools/backup/');
            assertStringContains('Content (.tar.gz)', $html);
            assertStringNotContains('/downloads/images.zip', $html);
            assertStringNotContains('Match the content revision.', $html);
        });
    }

    private function fixture(bool $withImages, callable $test): void
    {
        $workspace = new ImportTestWorkspace();
        $root = $workspace->repository('repository', true);
        $workspace->git($root);
        $db = $workspace->root . '/read.sqlite3';
        $images = $workspace->root . '/images-private';
        if ($withImages && !mkdir($images, 0700, true)) {
            throw new RuntimeException('Unable to create image root.');
        }
        $previous = [getenv('FORUM_APPROVED_MEMBERS_ONLY'), getenv('FORUM_IMAGE_ROOT')];
        $session = $_SESSION ?? [];
        $cookies = $_COOKIE;
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION = []; $_COOKIE = [];
        putenv('FORUM_APPROVED_MEMBERS_ONLY=false');
        putenv('FORUM_IMAGE_ROOT=' . $images);
        try {
            (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
            $test($root, $db);
        } finally {
            $_SESSION = $session; $_COOKIE = $cookies;
            putenv($previous[0] === false ? 'FORUM_APPROVED_MEMBERS_ONLY' : 'FORUM_APPROVED_MEMBERS_ONLY=' . $previous[0]);
            putenv($previous[1] === false ? 'FORUM_IMAGE_ROOT' : 'FORUM_IMAGE_ROOT=' . $previous[1]);
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
