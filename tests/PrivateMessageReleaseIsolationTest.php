<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Host\StaticArtifactBuilder;

final class PrivateMessageReleaseIsolationTest
{
    public function testStaticReleaseAndOfflineSnapshotExcludePrivateMailboxArtifacts(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-private-release-' . $suffix . '.sqlite3';
        $artifactRoot = sys_get_temp_dir() . '/forum-rewrite-private-release-artifacts-' . $suffix;

        try {
            (new StaticArtifactBuilder(
                dirname(__DIR__),
                __DIR__ . '/fixtures/parity_minimal_v1',
                $databasePath,
                $artifactRoot,
            ))->build();

            assertSame(false, is_dir($artifactRoot . '/messages'));
            assertSame(false, is_file($artifactRoot . '/offline/messages.sqlite3'));
            assertSame(false, is_file($artifactRoot . '/messages.sqlite3'));
            foreach ($this->htmlFiles($artifactRoot) as $path) {
                $html = (string) file_get_contents($path);
                assertStringNotContains('private_message_reader', $html);
                assertStringNotContains('private_message_list', $html);
                assertStringNotContains('private_message_conversation', $html);
                assertStringNotContains('private_message_unread', $html);
                assertStringNotContains('private_message_seen', $html);
                assertStringNotContains('data-private-message-unread', $html);
                assertStringNotContains('data-private-message-composer', $html);
                assertStringNotContains('private-message-unavailable-details', $html);
                assertStringNotContains('unavailable-group', $html);
                assertStringNotContains('message_time', $html);
                assertStringNotContains('private_messages.js', $html);
                assertStringNotContains('href="/messages', $html);
            }
        } finally {
            @unlink($databasePath);
            @unlink($databasePath . '-journal');
            $this->deleteTree($artifactRoot);
        }
    }

    /** @return list<string> */
    private function htmlFiles(string $root): array
    {
        if (!is_dir($root)) {
            return [];
        }

        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));
        foreach ($iterator as $file) {
            if ($file->isFile() && strtolower($file->getExtension()) === 'html') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );
        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($path);
    }
}
