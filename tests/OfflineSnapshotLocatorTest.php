<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Offline\OfflineSnapshotLocator;

final class OfflineSnapshotLocatorTest
{
    private const SHA_STATIC = 'aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const SHA_RELEASE = 'bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

    public function testNoSnapshotYieldsNoServedPathAndEmptyRevision(): void
    {
        $root = $this->makeRoot();
        try {
            $locator = new OfflineSnapshotLocator();
            assertSame(null, $locator->servedSnapshotPath($root));
            assertSame('', $locator->manifestRevision($root));
            assertSame(null, $locator->servedSnapshotPath(''));
        } finally {
            $this->deleteTree($root);
        }
    }

    public function testStaticRootSnapshotProvidesItsManifestRevision(): void
    {
        $root = $this->makeRoot();
        try {
            $this->writeSnapshot($root . '/offline', self::SHA_STATIC);
            assertSame($root . '/offline/snapshot.sqlite3', (new OfflineSnapshotLocator())->servedSnapshotPath($root));
            assertSame(self::SHA_STATIC, (new OfflineSnapshotLocator())->manifestRevision($root));
        } finally {
            $this->deleteTree($root);
        }
    }

    public function testActiveReleaseSnapshotIsUsedWhenStaticRootHasNone(): void
    {
        $root = $this->makeRoot();
        try {
            $this->writeSnapshot($root . '/releases/one/offline', self::SHA_RELEASE);
            symlink($root . '/releases/one', $root . '/current');
            assertSame(self::SHA_RELEASE, (new OfflineSnapshotLocator())->manifestRevision($root));
        } finally {
            $this->deleteTree($root);
        }
    }

    public function testStaticRootWinsOverActiveRelease(): void
    {
        $root = $this->makeRoot();
        try {
            $this->writeSnapshot($root . '/releases/one/offline', self::SHA_RELEASE);
            symlink($root . '/releases/one', $root . '/current');
            $this->writeSnapshot($root . '/offline', self::SHA_STATIC);
            assertSame(self::SHA_STATIC, (new OfflineSnapshotLocator())->manifestRevision($root));
        } finally {
            $this->deleteTree($root);
        }
    }

    public function testMissingOrMalformedManifestYieldsEmptyRevision(): void
    {
        $root = $this->makeRoot();
        try {
            mkdir($root . '/offline', 0777, true);
            file_put_contents($root . '/offline/snapshot.sqlite3', "SQLite format 3\000fixture");
            assertSame('', (new OfflineSnapshotLocator())->manifestRevision($root));

            file_put_contents($root . '/offline/manifest.json', '{"sha256":"not-a-hash"}');
            assertSame('', (new OfflineSnapshotLocator())->manifestRevision($root));

            file_put_contents($root . '/offline/manifest.json', 'not json');
            assertSame('', (new OfflineSnapshotLocator())->manifestRevision($root));
        } finally {
            $this->deleteTree($root);
        }
    }

    private function writeSnapshot(string $directory, string $sha256): void
    {
        mkdir($directory, 0777, true);
        file_put_contents($directory . '/snapshot.sqlite3', "SQLite format 3\000fixture");
        file_put_contents($directory . '/manifest.json', json_encode(['sha256' => $sha256], JSON_THROW_ON_ERROR));
    }

    private function makeRoot(): string
    {
        $root = sys_get_temp_dir() . '/forum-offline-locator-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);

        return $root;
    }

    private function deleteTree(string $path): void
    {
        if (is_link($path)) {
            unlink($path);
            return;
        }
        if (!is_dir($path)) {
            @unlink($path);
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                $this->deleteTree($path . '/' . $entry);
            }
        }
        rmdir($path);
    }
}
