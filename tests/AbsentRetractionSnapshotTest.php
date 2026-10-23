<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\ExchangeRetractionState;
use ForumRewrite\Retraction\PublicationGuard;

final class AbsentRetractionSnapshotTest
{
    private function fixture(string $id = 'target'): string
    {
        $root = sys_get_temp_dir() . '/absent-snapshot-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0700, true);
        mkdir($root . '/records/post-retractions');
        file_put_contents($root . '/records/post-retractions/post-retraction-' . $id . '.txt',
            "Record-ID: post-retraction-$id\nCreated-At: 2026-10-14T12:00:00Z\nPost-ID: $id\nOperation: retract\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\n");
        return $root;
    }

    public function testSnapshotAbsenceIsNotGlobalAuthorityAndReappearanceIsBuildLocal(): void
    {
        foreach (['target', 'reply-20260526032118-654b2b08'] as $id) {
            $root = $this->fixture($id);
            assertSame(null, ExchangeRetractionState::read($root));
            $state = ExchangeRetractionState::snapshot($root);
            assertSame([$id => true], $state['absent']);
            assertSame([], $state['ids']);
            $generation = PublicationGuard::snapshotGeneration($root, $state['absent']);
            file_put_contents($root . '/records/posts/' . $id . '.txt', "Post-ID: $id\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nAuthor-Identity-ID: openpgp:" . str_repeat('b', 40) . "\n\nReappeared\n");
            assertSame($generation, PublicationGuard::snapshotGeneration($root, $state['absent']));
            assertSame([], ExchangeRetractionState::snapshot($root)['absent']);
            assertSame([], ExchangeRetractionState::read($root));
            $recordPath = $root . '/records/post-retractions/post-retraction-' . $id . '.txt';
            file_put_contents($recordPath, str_replace('12:00:00Z', '12:01:00Z', file_get_contents($recordPath)));
            assertTrue($generation !== PublicationGuard::snapshotGeneration($root, $state['absent']));
        }
    }

    public function testUnsafeAbsenceAndMalformedEvidenceRemainUnavailable(): void
    {
        $root = $this->fixture();
        symlink('/nonexistent-absent-snapshot', $root . '/records/posts/target.txt');
        assertSame(null, ExchangeRetractionState::snapshot($root));
        unlink($root . '/records/posts/target.txt');
        file_put_contents($root . '/records/post-retractions/post-retraction-target.txt', 'bad');
        assertSame(null, ExchangeRetractionState::snapshot($root));
    }

    public function publicationFixture(string $target = 'target'): array
    {
        $root = $this->fixture($target);
        $fixture = __DIR__ . '/fixtures/parity_minimal_v1';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture, FilesystemIterator::SKIP_DOTS)) as $file) {
            $path = $root . substr($file->getPathname(), strlen($fixture));
            if (!is_dir(dirname($path))) mkdir(dirname($path), 0700, true);
            copy($file->getPathname(), $path);
        }
        // Build before deletion to deliberately retain stale target content in the source model.
        $recordPath = $root . '/records/post-retractions/post-retraction-' . $target . '.txt';
        $record = file_get_contents($recordPath); unlink($recordPath);
        $rootId = 'root-001';
        if ($target !== 'root-001') file_put_contents($root . '/records/posts/' . $target . '.txt',
            "Post-ID: $target\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nThread-ID: $rootId\nParent-ID: $rootId\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\nABSENT-SECRET\n");
        $database = $root . '/source.sqlite3';
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, new \ForumRewrite\Canonical\CanonicalRecordRepository($root), 'absent_test'))->rebuild();
        unlink($root . '/records/posts/' . $target . '.txt');
        file_put_contents($recordPath, $record);
        return [$root, $database];
    }

    public function testBasePublicationFiltersStaleReplyAndRetainsSource(): void
    {
        [$root, $database] = $this->publicationFixture();
        $before = hash_file('sha256', $database);
        $result = (new \ForumRewrite\Offline\OfflineSnapshotPublisher($root . '/static'))->publish($database);
        $snapshot = new PDO('sqlite:' . $result['path']);
        assertSame(0, (int) $snapshot->query("SELECT COUNT(*) FROM posts WHERE post_id='target'")->fetchColumn());
        assertSame(1, (int) $snapshot->query("SELECT COUNT(*) FROM posts WHERE post_id='reply-001'")->fetchColumn());
        assertSame(false, str_contains(file_get_contents($result['path']), 'ABSENT-SECRET'));
        assertSame($before, hash_file('sha256', $database));
        assertTrue(is_file($root . '/records/post-retractions/post-retraction-target.txt'));
    }

    public function testAbsentRootRetainsRepliesWithContentFreeThread(): void
    {
        [$root, $database] = $this->publicationFixture('root-001');
        $result = (new \ForumRewrite\Offline\OfflineSnapshotPublisher($root . '/static'))->publish($database);
        $snapshot = new PDO('sqlite:' . $result['path']);
        assertSame(0, (int) $snapshot->query("SELECT COUNT(*) FROM posts WHERE post_id='root-001'")->fetchColumn());
        assertSame(1, (int) $snapshot->query("SELECT COUNT(*) FROM posts WHERE post_id='reply-001'")->fetchColumn());
        assertSame('', $snapshot->query("SELECT body_preview FROM threads WHERE root_post_id='root-001'")->fetchColumn());
        assertSame(null, $snapshot->query("SELECT subject FROM threads WHERE root_post_id='root-001'")->fetchColumn());
    }

    public function testMissingTrackedRetractionStillBlocks(): void
    {
        $root = $this->fixture();
        exec('git -C ' . escapeshellarg($root) . ' init -q');
        exec('git -C ' . escapeshellarg($root) . ' add records');
        exec('git -C ' . escapeshellarg($root) . ' -c user.name=Test -c user.email=test@example.invalid commit -qm fixture');
        unlink($root . '/records/post-retractions/post-retraction-target.txt');
        assertSame(null, ExchangeRetractionState::snapshot($root));
    }
}
