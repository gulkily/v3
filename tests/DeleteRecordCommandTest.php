<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/ReadModelSnapshot.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\ReadModel\IncrementalReadModelUpdater;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ThreadLabelFileIndex;
use ForumRewrite\Support\LocalRepositoryBootstrap;

/**
 * Operator output of `./v3 delete-record <post-id>`: step lines on stderr while it
 * runs, -q/--quiet keeping only errors and the final line, --json leaving stderr empty.
 */
final class DeleteRecordCommandTest
{
    private const AUTHOR = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testInspectAndDeletePrintStepsInOrderOnStderr(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            [$status, $stdout, $stderr] = $this->run([$root, $database, $artifacts, '--inspect'], 'root-001');
            assertSame(0, $status);
            assertStringContains('source_path: records/posts/', $stdout);
            $this->assertLinesInOrder($stderr, ['] Reading post root-001', '] Finding labels for thread root-001']);
            assertTrue(preg_match('/^Inspected root-001 in \d+\.\d\ds$/m', $stderr) === 1, $stderr);

            [$status, $stdout, $stderr] = $this->run([$root, $database, $artifacts], 'root-001');
            assertSame(0, $status, $stderr);
            assertStringContains('Source label cleanup accepted: root-001', $stdout);
            $this->assertLinesInOrder($stderr, [
                '] Taking read-model lock',
                '] Reading post root-001',
                '] Finding labels for thread root-001',
                '] Committing removal',
                '] Clearing generated pages',
                '] Rebuilding read model',
                '] Checking result',
            ]);
            assertTrue(preg_match('/^Deleted root-001 in \d+\.\d\ds$/m', $stderr) === 1, $stderr);
            assertSame(false, is_file($root . '/records/posts/root-001.txt'));
        } finally {
            $this->remove($project);
        }
    }

    public function testQuietKeepsOnlyTheFinalLineAndJsonKeepsStderrEmpty(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            [$status, , $stderr] = $this->run([$root, $database, $artifacts, '--inspect', '-q'], 'root-001');
            assertSame(0, $status);
            assertTrue(preg_match('/\A^Inspected root-001 in \d+\.\d\ds\n\z/m', $stderr) === 1, $stderr);

            [$status, $stdout, $stderr] = $this->run([$root, $database, $artifacts, '--inspect', '--json'], 'root-001');
            assertSame(0, $status);
            assertSame('', $stderr);
            assertSame('records/posts/root-001.txt', json_decode($stdout, true)['source_path']);

            [$status, , $stderr] = $this->run([$root, $database, $artifacts, '--quiet'], 'root-001');
            assertSame(0, $status, $stderr);
            assertTrue(preg_match('/\A^Deleted root-001 in \d+\.\d\ds\n\z/m', $stderr) === 1, $stderr);
        } finally {
            $this->remove($project);
        }
    }

    public function testQuietStillReportsErrors(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            [$status, , $stderr] = $this->run([$root, $database, $artifacts, '--inspect', '-q'], 'no-such-post');
            assertTrue($status !== 0);
            assertStringContains('Source evidence unavailable', $stderr);
        } finally {
            $this->remove($project);
        }
    }

    public function testInspectReadsTheLabelIndexAndMatchesAFullScan(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            $this->commitLabel($root, 'label-owned', 'root-001', 'root-001');
            $this->commitLabel($root, 'label-other', 'thread-zenmemes-rules', null);
            $this->rebuild($root, $database);
            assertSame(['records/thread-labels/label-owned.txt', 'records/thread-labels/thread-label-20260415153000-ab12cd34.txt'],
                ThreadLabelFileIndex::paths($database, $root, 'root-001'));

            $indexed = LabelRemoval::inspect($root, 'root-001', null, $database);
            $scanned = LabelRemoval::inspect($root, 'root-001');
            unset($indexed['post'], $scanned['post']);
            assertSame($scanned, $indexed);
            assertSame(['records/thread-labels/label-owned.txt'], array_keys($indexed['proven']));

            [$status, , $stderr] = $this->run([$root, $database, $artifacts, '--inspect'], 'root-001');
            assertSame(0, $status);
            assertStringContains('] Finding labels for thread root-001' . "\n", $stderr);
        } finally {
            $this->remove($project);
        }
    }

    public function testInspectScansWhenTheReadModelIsBehindHeadOrMissing(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            $this->rebuild($root, $database);
            $this->commitLabel($root, 'label-late', 'root-001', 'root-001');
            assertSame(null, ThreadLabelFileIndex::paths($database, $root, 'root-001'));
            assertSame(null, ThreadLabelFileIndex::paths($root . '/missing.sqlite3', $root, 'root-001'));

            [$status, $stdout, $stderr] = $this->run([$root, $database, $artifacts, '--inspect'], 'root-001');
            assertSame(0, $status);
            assertStringContains('(scanning all label files)', $stderr);
            assertStringContains('label-late.txt', $stdout);
        } finally {
            $this->remove($project);
        }
    }

    public function testIncrementalLabelWriteKeepsTheIndexCurrent(): void
    {
        [$project, $root, $database] = $this->environment();
        try {
            $this->rebuild($root, $database);
            $commit = $this->commitLabel($root, 'label-new', 'root-001', null);
            (new IncrementalReadModelUpdater($database, $root))->applyThreadLabelWrite('root-001', $commit);
            assertSame(['records/thread-labels/label-new.txt', 'records/thread-labels/thread-label-20260415153000-ab12cd34.txt'],
                ThreadLabelFileIndex::paths($database, $root, 'root-001'));
        } finally {
            $this->remove($project);
        }
    }

    public function testVersion47ModelFallsBackForInspectAndStartupAddsTheIndex(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            $this->rebuild($root, $database);
            $pdo = new PDO('sqlite:' . $database);
            $pdo->exec('DROP TABLE thread_label_files');
            $pdo->exec("UPDATE metadata SET value = '47' WHERE key = 'schema_version'");
            $pdo = null;
            assertSame(null, ThreadLabelFileIndex::paths($database, $root, 'root-001'));
            [$status, , $stderr] = $this->run([$root, $database, $artifacts, '--inspect'], 'root-001');
            assertSame(0, $status);
            assertStringContains('(scanning all label files)', $stderr);

            $application = new Application(dirname(__DIR__), $root, $database);
            ob_start();
            $application->handle('GET', '/threads/root-001');
            assertStringContains('Hello world', (string) ob_get_clean());
            $pdo = new PDO('sqlite:' . $database);
            assertSame(ReadModelMetadata::SCHEMA_VERSION, $pdo->query("SELECT value FROM metadata WHERE key = 'schema_version'")->fetchColumn());
            assertSame(['records/thread-labels/thread-label-20260415153000-ab12cd34.txt'], ThreadLabelFileIndex::paths($database, $root, 'root-001'));
        } finally {
            $this->remove($project);
        }
    }

    public function testRemovalCommitTouchesOnlyItsPathsAndLeavesACleanTree(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            $this->commitLabel($root, 'label-owned', 'root-001', 'root-001');
            $this->rebuild($root, $database);
            $before = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
            [$status, , $stderr] = $this->run([$root, $database, $artifacts], 'root-001');
            assertSame(0, $status, $stderr);

            assertSame($before, trim(LabelRemoval::git($root, ['rev-parse', 'HEAD~1'])));
            $changed = explode("\n", trim(LabelRemoval::git($root, ['diff', '--name-status', 'HEAD~1', 'HEAD'])));
            sort($changed);
            assertSame([
                "A\trecords/post-removals/root-001.txt",
                "D\trecords/posts/root-001.txt",
                "D\trecords/thread-labels/label-owned.txt",
            ], $changed);
            assertSame('Accept source label cleanup root-001', trim(LabelRemoval::git($root, ['log', '-1', '--format=%s'])));
            assertSame('', trim(LabelRemoval::git($root, ['status', '--porcelain'])));
        } finally {
            $this->remove($project);
        }
    }

    public function testRerunAfterCommitButBeforeIndexUpdateFinishesWithoutASecondCommit(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            [$status, , $stderr] = $this->run([$root, $database, $artifacts], 'root-001');
            assertSame(0, $status, $stderr);
            $head = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
            // Model a kill after fast-import: the commit exists, but the index still lists the post.
            $blob = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD~1:records/posts/root-001.txt']));
            LabelRemoval::git($root, ['update-index', '--add', '--cacheinfo', '100644,' . $blob . ',records/posts/root-001.txt']);
            $journal = trim(LabelRemoval::git($root, ['rev-parse', '--absolute-git-dir'])) . '/source-label-removal-root-001.json';
            file_put_contents($journal, json_encode(['format' => 'removal-recovery-v1', 'record' => LabelRemoval::read($root, 'root-001')]));

            [$status, , $stderr] = $this->run([$root, $database, $artifacts], 'root-001');
            assertSame(0, $status, $stderr);
            assertStringContains('] Finishing an interrupted removal', $stderr);
            assertSame($head, trim(LabelRemoval::git($root, ['rev-parse', 'HEAD'])));
            assertSame('', trim(LabelRemoval::git($root, ['status', '--porcelain'])));
            assertSame(false, is_file($journal));
        } finally {
            $this->remove($project);
        }
    }

    public function testTargetedRemovalMatchesAFullRebuild(): void
    {
        $scenarios = [
            'root with a reply and an owned label' => ['root-001', fn (string $root) => $this->commitLabel($root, 'label-owned', 'root-001', 'root-001')],
            'reply' => ['reply-001', null],
            'root without replies' => ['thread-zenmemes-rules', null],
            'reply with an owned label on its thread' => ['reply-001', fn (string $root) => $this->commitLabel($root, 'label-reply', 'root-001', 'reply-001')],
            'later of two replies' => ['reply-002', fn (string $root) => $this->commitReply($root, 'reply-002', 'root-001', "Later reply.\n")],
            'reply with a reply under it' => ['reply-001', fn (string $root) => $this->commitReply($root, 'reply-002', 'reply-001', "Nested reply.\n")],
            'reply with a reaction on it' => ['reply-001', function (string $root): void {
                @mkdir($root . '/records/post-reactions', 0777, true);
                file_put_contents($root . '/records/post-reactions/post-reaction-20260415153000-ab12cd34.txt',
                    "Record-ID: post-reaction-20260415153000-ab12cd34\nCreated-At: 2026-04-15T15:30:00Z\nPost-ID: reply-001\nOperation: add\nTags: like\nAuthor-Identity-ID: " . self::AUTHOR . "\n\n");
                LabelRemoval::git($root, ['add', 'records']);
                LabelRemoval::git($root, ['commit', '-qm', 'Reaction fixture']);
            }],
            'reply that retitled the thread' => ['reply-title', function (string $root): void {
                $this->commitReply($root, 'reply-title', 'root-001', "title: Renamed\n", self::AUTHOR);
                $source = hash_file('sha256', $root . '/records/posts/reply-title.txt');
                file_put_contents($root . '/records/thread-subjects/thread-subject-20260601000003-aa000001.txt',
                    "Record-ID: thread-subject-20260601000003-aa000001\nCreated-At: 2026-06-01T00:00:03Z\nThread-ID: root-001\nOperation: set\nSubject: Renamed\nAuthor-Identity-ID: " . self::AUTHOR . "\nSource-Post-ID: reply-title\nSource-Post-SHA256: {$source}\nReason: Title directive in reply\n\n");
                LabelRemoval::git($root, ['add', 'records']);
                LabelRemoval::git($root, ['commit', '-qm', 'Retitle fixture']);
            }],
        ];
        $qdbQuote = 'thread-20261101215816-qdb-311681';
        $scenarios['QDB quote with an imported score and votes'] = [$qdbQuote, function (string $root) use ($qdbQuote): void {
            file_put_contents($root . '/records/posts/' . $qdbQuote . '.txt', "Post-ID: {$qdbQuote}\nCreated-At: 2026-11-01T21:58:16Z\nBoard-Tags: general\nImported-Score-Seed: 12\nImported-Vote-Count-Seed: 3\n\n<Geekthras> ekcd is awesome\n");
            foreach (['upvote', 'downvote'] as $n => $vote) {
                file_put_contents($root . "/records/thread-labels/thread-label-2026110200000{$n}-vote{$n}.txt", "Record-ID: thread-label-2026110200000{$n}-vote{$n}\nCreated-At: 2026-11-02T00:00:0{$n}Z\nThread-ID: {$qdbQuote}\nOperation: add\nLabels: {$vote}\nAuthor-Identity-ID: " . self::AUTHOR . "\n\n");
            }
            LabelRemoval::git($root, ['add', 'records']);
            LabelRemoval::git($root, ['commit', '-qm', 'QDB quote fixture']);
        }, 'qdb'];
        foreach ($scenarios as $name => [$id, $setup]) {
            $site = $scenarios[$name][2] ?? null;
            $priorSite = getenv('FORUM_SITE_ID');
            if ($site !== null) putenv('FORUM_SITE_ID=' . $site);
            [$project, $root, $database, $artifacts] = $this->environment();
            try {
                if ($setup !== null) $setup($root);
                $this->rebuild($root, $database);
                $lines = [];
                $result = \ForumRewrite\Write\SourceLabelRemoval::run($root, $database, $id, false, [], $artifacts, static function (string $line) use (&$lines): void { $lines[] = $line; });
                assertSame('ok', $result['status']);
                $thread = str_starts_with($id, 'reply-') ? 'root-001' : $id;
                assertSame([$name, true, false], [$name, in_array('Updating read model for thread ' . $thread, $lines, true), in_array('Rebuilding read model', $lines, true)], implode(' | ', $lines));
                $targeted = ReadModelSnapshot::take($database);
                $this->rebuild($root, $database);
                $rebuilt = ReadModelSnapshot::take($database);
                assertSame([], ReadModelSnapshot::differences($rebuilt, $targeted), $name);
            } finally {
                $this->remove($project);
                if ($site !== null) putenv($priorSite === false ? 'FORUM_SITE_ID' : 'FORUM_SITE_ID=' . $priorSite);
            }
        }
    }

    public function testTargetedRemovalClearsOnlyPagesThatShowThePost(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            $this->rebuild($root, $database);
            $pages = ['/index.html', '/recent/index.html', '/latest.html', '/tags/bug.html', '/tags/general/index.html', '/tags/pinned.html',
                '/threads/root-001.html', '/threads/reply-001.html', '/posts/reply-001.html', '/threads/thread-zenmemes-rules.html', '/docs.html',
                '/profiles/someone-else.html'];
            foreach ($pages as $page) {
                @mkdir(dirname($artifacts . $page), 0777, true);
                file_put_contents($artifacts . $page, 'cached');
            }
            [$status, , $stderr] = $this->run([$root, $database, $artifacts], 'reply-001');
            assertSame(0, $status, $stderr);
            assertStringContains('] Clearing pages for thread root-001', $stderr);
            $kept = array_values(array_filter($pages, static fn (string $page): bool => is_file($artifacts . $page)));
            assertSame(['/tags/pinned.html', '/threads/thread-zenmemes-rules.html', '/docs.html', '/profiles/someone-else.html'], $kept);
        } finally {
            $this->remove($project);
        }
    }

    public function testTargetedUpdateFailureFallsBackToARebuild(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            $this->rebuild($root, $database);
            // A read model missing the thread row cannot take the targeted update.
            (new PDO('sqlite:' . $database))->exec("DELETE FROM threads WHERE root_post_id = 'root-001'");
            [$status, , $stderr] = $this->run([$root, $database, $artifacts], 'reply-001');
            assertSame(0, $status, $stderr);
            $this->assertLinesInOrder($stderr, ['] Updating read model for thread root-001', '] Targeted update failed (Incremental thread-label update could not resolve target thread.)', '] Rebuilding read model']);
            assertSame('', (string) (new PDO('sqlite:' . $database))->query("SELECT body FROM posts WHERE post_id = 'reply-001'")->fetchColumn());
        } finally {
            $this->remove($project);
        }
    }

    /** @return array{int,string,string} */
    private function run(array $arguments, string $id): array
    {
        $command = implode(' ', array_map('escapeshellarg', [dirname(__DIR__) . '/v3', 'delete-record', $id, ...$arguments]));
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), (string) $stdout, (string) $stderr];
    }

    private function assertLinesInOrder(string $output, array $fragments): void
    {
        $offset = 0;
        foreach ($fragments as $fragment) {
            $position = strpos($output, $fragment, $offset);
            assertTrue($position !== false, "Missing or out of order: {$fragment}\n{$output}");
            $offset = $position + strlen($fragment);
        }
    }

    private function rebuild(string $root, string $database): void
    {
        (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
    }

    private function commitReply(string $root, string $postId, string $parentId, string $body, ?string $author = null): void
    {
        file_put_contents($root . '/records/posts/' . $postId . '.txt', "Post-ID: {$postId}\nCreated-At: 2026-06-01T00:00:02Z\nBoard-Tags: general\nThread-ID: root-001\nParent-ID: {$parentId}\n"
            . ($author !== null ? "Author-Identity-ID: {$author}\n" : '') . "\n{$body}");
        LabelRemoval::git($root, ['add', 'records']);
        LabelRemoval::git($root, ['commit', '-qm', 'Reply fixture ' . $postId]);
    }

    /** Commits an unsigned label file; with a source post it carries that post's ownership binding. */
    private function commitLabel(string $root, string $recordId, string $threadId, ?string $sourcePostId): string
    {
        $label = "Record-ID: {$recordId}\nCreated-At: 2026-04-10T12:00:00Z\nThread-ID: {$threadId}\nOperation: add\nLabels: pinned\n";
        if ($sourcePostId !== null) {
            $bytes = (string) file_get_contents($root . '/records/posts/' . $sourcePostId . '.txt');
            $post = (new \ForumRewrite\Canonical\PostRecordParser())->parse($bytes);
            if ($post->authorIdentityId !== null) $label .= "Author-Identity-ID: {$post->authorIdentityId}\n";
            $label .= "Source-Post-ID: {$sourcePostId}\nSource-Post-SHA256: " . hash('sha256', $bytes) . "\n";
        }
        file_put_contents($root . '/records/thread-labels/' . $recordId . '.txt', $label . "\n");
        LabelRemoval::git($root, ['add', 'records']);
        LabelRemoval::git($root, ['commit', '-qm', 'Label fixture ' . $recordId]);

        return trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
    }

    /** @return array{string,string,string,string} */
    private function environment(): array
    {
        $project = sys_get_temp_dir() . '/forum-rewrite-delete-record-' . bin2hex(random_bytes(6));
        $fixture = $project . '/tests/fixtures/parity_minimal_v1';
        mkdir($fixture, 0777, true);
        $this->copy(__DIR__ . '/fixtures/parity_minimal_v1', $fixture);
        LocalRepositoryBootstrap::initializeLocalRepository($project, $project . '/state/local_repository');
        mkdir($project . '/public', 0777, true);

        return [$project, $project . '/state/local_repository', $project . '/state/cache/post_index.sqlite3', $project . '/public'];
    }

    private function copy(string $source, string $destination): void
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $target = $destination . '/' . $items->getSubPathName();
            $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
        }
    }

    private function remove(string $path): void
    {
        exec('rm -rf ' . escapeshellarg($path));
    }
}
