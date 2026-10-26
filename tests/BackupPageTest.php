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
                foreach (['/downloads/repository.tar.gz', '/downloads/repository.zip', '/downloads/read_model.sqlite3', '/downloads/images.tar'] as $href) {
                    assertSame(1, substr_count($html, 'href="' . $href));
                }
                assertStringContains('Keep content and image downloads with matching revisions.', $html);
                assertStringContains('Public content only. Back up Git history, private data, browser keys and drafts, software, settings and external media separately.', $html);
                assertStringContains('The SQLite index may be out of date.', $html);
                foreach (['Snapshot freshness', 'Recent included items', 'About these downloads', '<table', '<strong>Admin:', '<strong>Name:'] as $removed) {
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
            assertStringNotContains('/downloads/images.tar', $html);
            assertStringNotContains('matching revisions', $html);
        });
    }

    private function fixture(bool $withImages, callable $test): void
    {
        $workspace = new ImportTestWorkspace();
        $root = $workspace->repository('repository', true);
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
