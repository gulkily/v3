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

        $privatePath = sys_get_temp_dir() . '/private-message-export-' . $suffix . '.sqlite3';
        $syncPath = sys_get_temp_dir() . '/history-sync-export-' . $suffix . '.sqlite3';
        $priorMessagePath = getenv('PRIVATE_MESSAGE_DATABASE_PATH');
        $priorSyncPath = getenv('PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH');
        putenv('PRIVATE_MESSAGE_DATABASE_PATH=' . $privatePath);
        putenv('PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH=' . $syncPath);
        (new \ForumRewrite\Messaging\PrivateMessageStore(new PDO('sqlite:' . $privatePath)))
            ->storeEnvelope('private-export-marker', 'now', 'alice', 'bob', 'source', 'PRIVATE-ORIGINAL-EXPORT-MARKER');
        (new \ForumRewrite\Messaging\PrivateMessageHistorySyncStore(new PDO('sqlite:' . $syncPath)))
            ->upload('alice', 'source', 'target', 'PRIVATE-HISTORY-EXPORT-MARKER', [['message_id'=>'private-export-marker', 'digest'=>str_repeat('a',64)]]);
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
            $snapshot = new PDO('sqlite:' . $artifactRoot . '/offline/snapshot.sqlite3');
            assertSame(0, (int)$snapshot->query("SELECT COUNT(*) FROM sqlite_master WHERE name LIKE 'history_sync_%' OR name LIKE 'private_message%'")->fetchColumn());
            $snapshot = null;
            foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($artifactRoot, \FilesystemIterator::SKIP_DOTS)) as $artifact) {
                if (!$artifact->isFile()) continue;
                $contents = (string)file_get_contents($artifact->getPathname());
                assertStringNotContains('PRIVATE-ORIGINAL-EXPORT-MARKER', $contents);
                assertStringNotContains('PRIVATE-HISTORY-EXPORT-MARKER', $contents);
                assertSame(false, in_array($artifact->getBasename(), [basename($privatePath), basename($syncPath)], true));
            }
            foreach ($this->htmlFiles($artifactRoot) as $path) {
                $html = (string) file_get_contents($path);
                assertStringNotContains('private_message_reader', $html);
                assertStringNotContains('private_message_list', $html);
                assertStringNotContains('private_message_conversation', $html);
                assertStringNotContains('private_message_unread', $html);
                assertStringNotContains('private_message_seen', $html);
                assertStringNotContains('/assets/private_message_history_sync.', $html);
                assertStringNotContains('/assets/private_message_history_crypto.', $html);
                assertStringNotContains('/assets/private_message_history_status.', $html);
                assertStringNotContains('data-private-message-unread', $html);
                assertStringNotContains('data-private-message-composer', $html);
                assertStringNotContains('private-message-unavailable-details', $html);
                assertStringNotContains('unavailable-group', $html);
                assertStringNotContains('message_time', $html);
                assertStringNotContains('private_messages.js', $html);
                assertStringNotContains('href="/messages', $html);
            }
        } finally {
            putenv($priorMessagePath === false ? 'PRIVATE_MESSAGE_DATABASE_PATH' : 'PRIVATE_MESSAGE_DATABASE_PATH=' . $priorMessagePath);
            putenv($priorSyncPath === false ? 'PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH' : 'PRIVATE_MESSAGE_HISTORY_SYNC_DATABASE_PATH=' . $priorSyncPath);
            foreach ([$privatePath, $syncPath] as $privateFile) { @unlink($privateFile); @unlink($privateFile . '-journal'); }
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
