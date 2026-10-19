<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class BackupPageTest
{
    public function testImagesSectionHasItsOwnTableAndPairingNoteWhenImageBackupIsAvailable(): void
    {
        $this->fixture(true, function (string $root, string $db): void {
            $html = $this->render($root, $db, '/tools/backup/');
            assertStringContains('>Text content</h2>', $html);
            assertStringContains('data-role="text-content-downloads"', $html);
            assertStringContains('data-role="image-downloads"', $html);
            assertOrdered($html, '>Text content</h2>', '>Images</h2>');
            assertOrdered($html, '>Images</h2>', 'data-role="image-downloads"');
            assertOrdered($html, 'data-role="image-downloads"', 'keep files with matching repository revisions together');
            assertOrdered($html, 'keep files with matching repository revisions together', '>Recent included items</h2>');
            assertSame(1, substr_count($html, 'href="/downloads/images.tar'));
            assertStringNotContains('>Image backup</h2>', $html);
            assertStringNotContains('>Downloads</h2>', $html);
        });
    }

    public function testImagesSectionIsAbsentWhenImageBackupIsUnavailable(): void
    {
        $this->fixture(false, function (string $root, string $db): void {
            $html = $this->render($root, $db, '/tools/backup/');
            assertStringContains('data-role="text-content-downloads"', $html);
            assertStringNotContains('>Images</h2>', $html);
            assertStringNotContains('image-downloads', $html);
            assertStringNotContains('/downloads/images.tar', $html);
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
