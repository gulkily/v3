<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LegacyPostTimestamp;
use ForumRewrite\Import\ContentImportPlanner;
use ForumRewrite\Import\ContentImportRunner;
use ForumRewrite\Import\ImportedContentPublisher;
use ForumRewrite\Import\RepositoryArchive;

final class LegacyTimestampImportTest
{
    public function testLegacyBootstrapPublishesAndDatesSurviveRebuildAndAnotherArchive(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source', true);
        $path = 'records/posts/root-001.txt';
        $bytes = preg_replace('/^Created-At:.*\n/m', '', file_get_contents($source . '/' . $path));
        $w->put($source . '/' . $path, $bytes);
        $w->put($source . '/' . $path . '.asc', 'Detached signature bytes');
        $w->git($source);
        $this->dateCommit($w, $source);
        $target = $w->repository('target');
        $w->put($target . '/records/instance/public.txt', file_get_contents($source . '/records/instance/public.txt'));
        $w->git($target);
        $extracted = $this->archive($w, $source, 'extracted');
        assertSame(1, $extracted['recovered_legacy_timestamps']);
        $database = $w->root . '/cache/index.sqlite3';
        $publisher = new ImportedContentPublisher(dirname(__DIR__), $target, $database, $w->root . '/static');
        $runner = new ContentImportRunner($target, $database);
        $preview = $runner->run($extracted['root'], static fn () => null, true);
        assertSame('complete', $preview['status']);
        assertTrue(!is_dir($target . '/records/post-timestamps'));
        $result = $runner->run($extracted['root'], $publisher->publishWhileLocked(...));
        assertSame('complete', $result['status']);
        assertSame($bytes, file_get_contents($target . '/' . $path));
        assertSame('Detached signature bytes', file_get_contents($target . '/' . $path . '.asc'));
        assertSame('2026-04-10T12:00:00Z', (new CanonicalRecordRepository($target))->loadPost($path)->createdAt);
        // A fresh builder must not use the newer local import commit date.
        $publisher->publishWhileLocked();
        $pdo = new PDO('sqlite:' . $database);
        assertSame('2026-04-10T12:00:00Z', $pdo->query("SELECT created_at FROM posts WHERE post_id='root-001'")->fetchColumn());
        assertTrue((int) $pdo->query('SELECT count(*) FROM profiles')->fetchColumn() > 0);
        assertSame(0, $runner->run($extracted['root'], static fn () => null)['counts']['import']);
        // Records alone now carry enough information for a second hop.
        $archive = $w->root . '/records-only.tar.gz';
        assertSame(0, $w->command(['tar', '-czf', $archive, '-C', $target, 'records'])[0]);
        $second = (new RepositoryArchive())->extract($archive, $w->root . '/second');
        assertSame(0, $second['recovered_legacy_timestamps']);
        $again = new CanonicalRecordRepository($second['root'], requireLegacyTimestampMetadata: true);
        assertSame('2026-04-10T12:00:00Z', $again->loadPost($path)->createdAt);
        assertSame('import', (new ContentImportPlanner())->plan($second['root'], $w->repository('empty'))[$path]['state']);
    }

    public function testPackedHistoryFollowsRenamesAndIgnoresSourceConfiguration(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $bytes = str_replace("Created-At: 2026-04-10T12:00:00Z\n", '', $w->post('legacy'));
        $w->put($source . '/records/posts/old.txt', $bytes);
        $w->git($source);
        $this->dateCommit($w, $source);
        rename($source . '/records/posts/old.txt', $source . '/records/posts/legacy.txt');
        $w->git($source);
        assertSame(0, $w->command(['git', '-C', $source, 'gc'])[0]);
        $w->put($source . '/.git/objects/info/alternates', '/untrusted/object/path');
        $w->put($source . '/.git/hooks/post-checkout', 'must not execute');
        file_put_contents($source . '/.git/config', "\n[include]\npath=/untrusted/config\n", FILE_APPEND);
        $result = $this->archive($w, $source, 'extracted');
        assertSame(1, $result['recovered_legacy_timestamps']);
        assertTrue(!is_dir($result['root'] . '/.git'));
        assertTrue(!is_file($result['root'] . '/.import-history/objects/info/alternates'));
        assertTrue(!is_dir($result['root'] . '/.import-history/hooks'));
        assertTrue(!str_contains(file_get_contents($result['root'] . '/.import-history/config'), 'include'));
        assertSame('2026-04-10T12:00:00Z', (new CanonicalRecordRepository($result['root']))->loadPost('records/posts/legacy.txt')->createdAt);
    }

    public function testUntrackedOrChangedLegacyBytesCannotBorrowHistoryDates(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $path = 'records/posts/legacy.txt';
        $bytes = str_replace("Created-At: 2026-04-10T12:00:00Z\n", '', $w->post('legacy'));
        $w->put($source . '/' . $path, $bytes);
        $w->git($source);
        $w->put($source . '/' . $path, $bytes . "Uncommitted change\n");
        $result = $this->archive($w, $source, 'extracted');
        assertSame(0, $result['recovered_legacy_timestamps']);
        $plan = (new ContentImportPlanner())->plan($result['root'], $w->repository('target'));
        assertSame('invalid', $plan[$path]['state']);
        assertStringContains('Created-At', $plan[$path]['reason']);
    }

    public function testChangedMetadataAndLocalDatesAreConflictsOrInvalid(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        $target = $w->repository('target');
        $path = 'records/posts/legacy.txt';
        $bytes = str_replace("Created-At: 2026-04-10T12:00:00Z\n", '', $w->post('legacy'));
        $meta = LegacyPostTimestamp::path('legacy');
        foreach ([$source, $target] as $root) {
            $w->put($root . '/' . $path, $bytes);
            $w->put($root . '/' . $meta, LegacyPostTimestamp::encode('legacy', $bytes, '2026-04-10T12:00:00Z'));
        }
        $w->put($source . '/' . $meta, LegacyPostTimestamp::encode('legacy', $bytes, '2026-04-11T12:00:00Z'));
        $plan = (new ContentImportPlanner())->plan($source, $target);
        assertSame('conflict', $plan[$meta]['state']);
        assertSame('invalid', $plan[$path]['state']);
        $w->put($source . '/' . $path, $bytes . "Changed\n");
        $plan = (new ContentImportPlanner())->plan($source, $target);
        assertSame('invalid', $plan[$path]['state']);
        assertStringContains('does not match post bytes', $plan[$path]['reason']);
    }

    public function testParallelAndSerialRecoveryAgreeAcrossMultipleBatchesAndRenames(): void
    {
        $w = new ImportTestWorkspace();
        $source = $w->repository('source');
        for ($i = 0; $i < 10; $i++) {
            $bytes = str_replace("Created-At: 2026-04-10T12:00:00Z\n", '', $w->post('legacy-' . $i));
            $w->put($source . '/records/posts/legacy-' . $i . '.txt', $bytes);
        }
        $w->git($source);
        $this->dateCommit($w, $source);
        // Keep the ID and bytes while moving a post into its canonical dated path.
        mkdir($source . '/records/posts/2026/04/10', 0700, true);
        rename($source . '/records/posts/legacy-0.txt', $source . '/records/posts/2026/04/10/legacy-0.txt');
        $w->git($source);
        $w->put($source . '/records/posts/legacy-9.txt', "Changed, uncommitted bytes\n");
        $w->put($source . '/records/posts/untracked.txt', str_replace("Created-At: 2026-04-10T12:00:00Z\n", '', $w->post('untracked')));
        $extracted = $this->archive($w, $source, 'extracted');
        assertSame(9, $extracted['recovered_legacy_timestamps']);
        $parallel = [];
        foreach (glob($extracted['root'] . '/records/post-timestamps/*.json') as $file) {
            $parallel[basename($file)] = file_get_contents($file);
            unlink($file);
        }
        $serialCount = (new \ForumRewrite\Import\LegacyArchiveTimestamps(maxConcurrentProcesses: 1))->recover($extracted['root']);
        assertSame(9, $serialCount);
        foreach ($parallel as $name => $bytes) {
            assertSame($bytes, file_get_contents($extracted['root'] . '/records/post-timestamps/' . $name));
        }
        assertSame('2026-04-10T12:00:00Z', (new CanonicalRecordRepository($extracted['root']))->loadPost('records/posts/2026/04/10/legacy-0.txt')->createdAt);
        assertTrue(!is_file($extracted['root'] . '/records/post-timestamps/untracked.json'));
        assertTrue(!is_file($extracted['root'] . '/records/post-timestamps/legacy-9.json'));
    }

    private function dateCommit(ImportTestWorkspace $w, string $source): void
    {
        [$code, $output] = $w->command(['env', 'GIT_AUTHOR_DATE=2026-04-10T12:00:00Z', 'GIT_COMMITTER_DATE=2026-04-10T12:00:00Z',
            'git', '-C', $source, 'commit', '--amend', '--no-edit', '--reset-author']);
        assertSame(0, $code, $output);
    }

    private function archive(ImportTestWorkspace $w, string $source, string $name): array
    {
        $archive = $w->root . '/' . $name . '.tar.gz';
        assertSame(0, $w->command(['tar', '-czf', $archive, '-C', $source, '.'])[0]);
        return (new RepositoryArchive())->extract($archive, $w->root . '/' . $name);
    }
}
