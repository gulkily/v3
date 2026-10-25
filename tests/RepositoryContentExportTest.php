<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Images\ImageBackup;
use ForumRewrite\Images\ImageRestore;
use ForumRewrite\Images\RepositorySnapshot;
use ForumRewrite\Import\RepositoryArchive;

final class RepositoryContentExportTest
{
    public function testBothFormatsOmitOversizedHistoryAndPreserveLegacyBytesDatesAndImagePair(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source');
        $legacy = str_replace("Created-At: 2026-04-10T12:00:00Z\n", '', $w->post('legacy'));
        $w->put($root . '/records/posts/legacy.txt', $legacy);
        $w->put($root . '/records/posts/legacy.txt.asc', 'unchanged detached signature');
        $w->put($root . '/records/posts/modern.txt', $w->post('modern'));
        $w->git($root);
        $backup = new ImageBackup($root, $w->root . '/index.sqlite3');
        $head = $backup->revision();
        $date = (new CanonicalRecordRepository($root, allowLegacyFilesystemTimestamp: false))->loadPost('records/posts/legacy.txt')->createdAt;
        // A sparse file simulates history larger than web export capacity without disk cost.
        $large = fopen($root . '/.git/large-history-test', 'wb');
        assertTrue(ftruncate($large, 600 * 1024 * 1024));
        fclose($large);
        $archives = [];
        try {
            $images = $backup->export('images', $head); $archives[] = $images['path'];
            foreach (['tar.gz', 'zip'] as $format) {
                $export = $backup->export($format, $head); $archives[] = $export['path'];
                assertTrue(filesize($export['path']) < 100000);
                $destination = $w->root . '/unpacked-' . $format;
                mkdir($destination);
                $command = $format === 'zip' ? ['unzip', '-q', $export['path'], '-d', $destination]
                    : ['tar', '-xzf', $export['path'], '-C', $destination];
                assertSame(0, $w->command($command)[0]);
                $snapshot = $destination . '/repository';
                assertSame(false, file_exists($snapshot . '/.git'));
                assertSame($legacy, file_get_contents($snapshot . '/records/posts/legacy.txt'));
                assertSame('unchanged detached signature', file_get_contents($snapshot . '/records/posts/legacy.txt.asc'));
                assertSame($date, (new CanonicalRecordRepository($snapshot, requireLegacyTimestampMetadata: true))->loadPost('records/posts/legacy.txt')->createdAt);
                assertSame($head, RepositorySnapshot::revision($snapshot));
                $restore = new ImageRestore();
                assertSame('verified', $restore->run($images['path'], $snapshot, $w->root . '/restore.sqlite3', $w->root . '/images-' . $format, true)['status']);
                assertSame('restored', $restore->run($images['path'], $snapshot, $w->root . '/restore.sqlite3', $w->root . '/images-' . $format)['status']);
                file_put_contents($snapshot . '/records/posts/modern.txt', 'changed');
                $this->reject(fn () => $restore->run($images['path'], $snapshot, $w->root . '/restore.sqlite3', $w->root . '/images-' . $format, true));
                if ($format === 'tar.gz') {
                    $import = (new RepositoryArchive())->extract($export['path'], $w->root . '/import');
                    assertSame(0, $import['recovered_legacy_timestamps']);
                    assertSame($date, (new CanonicalRecordRepository($import['root'], requireLegacyTimestampMetadata: true))->loadPost('records/posts/legacy.txt')->createdAt);
                    assertSame(false, is_dir($import['root'] . '/.import-history'));
                }
            }
            assertSame($head, $backup->revision());
            assertSame(false, file_exists($root . '/records/post-timestamps/legacy.json'));
            assertSame(600 * 1024 * 1024, filesize($root . '/.git/large-history-test'));
            assertSame('', trim($w->command(['git', '-C', $root, 'status', '--porcelain'])[1]));
            $this->reject(fn () => $backup->export('tar.gz', str_repeat('0', 40)));
            file_put_contents($root . '/records/posts/modern.txt', 'uncommitted');
            $this->reject(fn () => $backup->export('zip'));
        } finally {
            foreach ($archives as $archive) @unlink($archive);
        }
    }

    public function testUnpinnedAndPinnedDownloadRoutesBothExcludeGit(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source', true);
        $w->git($root);
        $database = $w->root . '/index.sqlite3';
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
        $head = (new ImageBackup($root, $database))->revision();
        foreach (['tar.gz', 'zip'] as $format) {
            foreach (['', '?revision=' . $head] as $query) {
                $code = 'require $argv[1]."/autoload.php"; (new ForumRewrite\\Application($argv[1],$argv[2],$argv[3]))->handle("GET",$argv[4]);';
                [$status, $bytes] = $w->command(['env', 'FORUM_APPROVED_MEMBERS_ONLY=false', 'FORUM_IMAGE_ROOT=' . $w->root . '/no-images',
                    'php', '-r', $code, dirname(__DIR__), $root, $database, '/downloads/repository.' . $format . $query]);
                assertSame(0, $status);
                $archive = $w->root . '/route.' . $format;
                file_put_contents($archive, $bytes);
                [$status, $listing] = $w->command($format === 'zip' ? ['unzip', '-Z1', $archive] : ['tar', '-tzf', $archive]);
                assertSame(0, $status);
                assertStringContains('repository/records/posts/', $listing);
                assertStringNotContains('/.git/', $listing);
                assertStringContains('repository/repository-snapshot.json', $listing);
            }
        }
    }

    public function testArchiveGuidePreservesNotesAndCollisionsWithoutChangingSource(): void
    {
        $w = new ImportTestWorkspace();
        $guide = file_get_contents(dirname(__DIR__) . '/resources/repository-archive/README.md');
        foreach (['missing' => null, 'custom' => "# My notes\r\nKeep these bytes.\r\n", 'current' => $guide] as $case => $notes) {
            $root = $w->repository($case);
            $w->put($root . '/records/posts/post.txt', $w->post('post'));
            if ($notes !== null) { $w->put($root . '/README.md', $notes); }
            $w->put($root . '/README.repository.md', 'Existing file');
            $w->put($root . '/README.repository-2.md/keep.txt', 'Existing directory');
            $w->git($root);
            $backup = new ImageBackup($root, $w->root . '/index.sqlite3');
            $head = $backup->revision();
            $expected = $guide . ($case === 'custom' ? "\nOriginal repository notes: [README.repository-3.md](README.repository-3.md).\n" : '');
            foreach (['tar.gz', 'zip'] as $format) {
                $export = $backup->export($format);
                try {
                    $destination = $w->root . '/' . $case . '-' . $format;
                    mkdir($destination);
                    assertSame(0, $w->command($format === 'zip'
                        ? ['unzip', '-q', $export['path'], '-d', $destination]
                        : ['tar', '-xzf', $export['path'], '-C', $destination])[0]);
                    $snapshot = $destination . '/repository';
                    assertSame($expected, file_get_contents($snapshot . '/README.md'));
                    assertSame('Existing file', file_get_contents($snapshot . '/README.repository.md'));
                    assertSame('Existing directory', file_get_contents($snapshot . '/README.repository-2.md/keep.txt'));
                    assertSame($case === 'custom', is_file($snapshot . '/README.repository-3.md'));
                    if ($case === 'custom') { assertSame($notes, file_get_contents($snapshot . '/README.repository-3.md')); }
                    assertSame($head, RepositorySnapshot::revision($snapshot));
                    assertSame(file_get_contents($root . '/records/posts/post.txt'), file_get_contents($snapshot . '/records/posts/post.txt'));
                    \ForumRewrite\Images\RepositoryArchiveReadme::write($snapshot);
                    assertSame($expected, file_get_contents($snapshot . '/README.md'));
                    assertSame(false, file_exists($snapshot . '/README.repository-4.md'));
                } finally { unlink($export['path']); }
            }
            assertSame($notes, is_file($root . '/README.md') ? file_get_contents($root . '/README.md') : null);
            assertSame($head, $backup->revision());
            assertSame('', trim($w->command(['git', '-C', $root, 'status', '--porcelain'])[1]));
        }
    }

    private function reject(callable $operation): void
    {
        try { $operation(); } catch (RuntimeException) { return; }
        throw new RuntimeException('Expected export or restore rejection.');
    }
}
