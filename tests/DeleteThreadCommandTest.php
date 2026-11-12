<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/ReadModelSnapshot.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Images\ImageReference;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Support\LocalRepositoryBootstrap;

/** `./v3 delete-thread <thread-id>`: removes every reply and the root in one batch removal. */
final class DeleteThreadCommandTest
{
    public function testInspectListsRepliesNewestFirstThenRootAndChangesNothing(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            $this->commitReply($root, 'reply-002', 'reply-001', '2026-06-01T00:00:05Z');
            $head = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
            [$status, $stdout, $stderr] = $this->run([$root, $database, $artifacts, '--inspect', '--json'], 'root-001');
            assertSame(0, $status, $stderr);
            $result = json_decode($stdout, true);
            assertSame(['reply-002', 'reply-001'], $result['replies']);
            assertSame(3, $result['posts_to_delete']);
            assertSame($head, trim(LabelRemoval::git($root, ['rev-parse', 'HEAD'])));
        } finally {
            $this->remove($project);
        }
    }

    public function testDeletesEveryPostInTheThreadAndLeavesOthers(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            $this->commitReply($root, 'reply-002', 'reply-001', '2026-06-01T00:00:05Z');
            // Body text that looks like a header must not pull an unrelated post in.
            file_put_contents($root . '/records/posts/other-001.txt', "Post-ID: other-001\nCreated-At: 2026-06-01T00:00:06Z\nBoard-Tags: general\n\nThread-ID: root-001\n");
            LabelRemoval::git($root, ['add', 'records']);
            LabelRemoval::git($root, ['commit', '-qm', 'Other thread fixture']);
            $this->rebuild($root, $database);

            [$status, $stdout, $stderr] = $this->run([$root, $database, $artifacts], 'root-001');
            assertSame(0, $status, $stderr);
            assertStringContains('Thread deleted: root-001 (3 post(s))', $stdout);
            assertStringContains('] Committing removal (3 post(s), ', $stderr);
            assertStringContains('] Updating read model', $stderr);
            assertSame(false, str_contains($stderr, 'Rebuilding read model'), $stderr);
            // One commit for the whole thread, with a receipt per post.
            assertSame('Delete thread root-001 (3 post(s))', trim(LabelRemoval::git($root, ['log', '-1', '--format=%s'])));
            $changed = explode("\n", trim(LabelRemoval::git($root, ['diff', '--name-status', 'HEAD~1', 'HEAD', '--', 'records/posts', 'records/post-removals'])));
            sort($changed);
            assertSame(["A\trecords/post-removals/reply-001.txt", "A\trecords/post-removals/reply-002.txt", "A\trecords/post-removals/root-001.txt",
                "D\trecords/posts/reply-001.txt", "D\trecords/posts/reply-002.txt", "D\trecords/posts/root-001.txt"], $changed);
            assertTrue(preg_match('/^Deleted thread root-001 in \d+\.\d\ds$/m', $stderr) === 1, $stderr);
            foreach (['root-001', 'reply-001', 'reply-002'] as $id) assertSame(false, is_file($root . '/records/posts/' . $id . '.txt'), $id);
            assertSame(true, is_file($root . '/records/posts/other-001.txt'));
            assertSame(true, is_file($root . '/records/posts/thread-zenmemes-rules.txt'));
            assertSame('', trim(LabelRemoval::git($root, ['status', '--porcelain'])));
            $pdo = new PDO('sqlite:' . $database);
            assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE thread_id = 'root-001' AND body != ''")->fetchColumn());

            // A rerun finds nothing left to do.
            $head = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
            [$status, $stdout, $stderr] = $this->run([$root, $database, $artifacts, '-q'], 'root-001');
            assertSame(0, $status, $stderr);
            assertStringContains('(0 post(s))', $stdout);
            assertSame($head, trim(LabelRemoval::git($root, ['rev-parse', 'HEAD'])));
        } finally {
            $this->remove($project);
        }
    }

    public function testResumesAfterThePartialDeletionOfAThread(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            $this->rebuild($root, $database);
            \ForumRewrite\Write\SourceLabelRemoval::run($root, $database, 'reply-001', false, [], $artifacts);
            [$status, $stdout, $stderr] = $this->run([$root, $database, $artifacts], 'root-001');
            assertSame(0, $status, $stderr);
            assertStringContains('(1 post(s))', $stdout);
            assertSame(false, is_file($root . '/records/posts/root-001.txt'));
        } finally {
            $this->remove($project);
        }
    }

    public function testBatchRemovalMatchesAFullRebuild(): void
    {
        $scenarios = [
            'plain thread' => null,
            'nested replies and an owned label' => function (string $root): void {
                $this->commitReply($root, 'reply-002', 'reply-001', '2026-06-01T00:00:05Z');
                $this->commitLabel($root, 'label-owned', 'root-001', 'reply-001');
            },
            'reply that retitled the thread' => function (string $root): void {
                $this->commitReply($root, 'reply-title', 'root-001', '2026-06-01T00:00:03Z', "title: Renamed\n");
                $source = hash_file('sha256', $root . '/records/posts/reply-title.txt');
                file_put_contents($root . '/records/thread-subjects/thread-subject-20260601000003-aa000001.txt',
                    "Record-ID: thread-subject-20260601000003-aa000001\nCreated-At: 2026-06-01T00:00:03Z\nThread-ID: root-001\nOperation: set\nSubject: Renamed\nSource-Post-ID: reply-title\nSource-Post-SHA256: {$source}\nReason: Title directive in reply\n\n");
                LabelRemoval::git($root, ['add', 'records']);
                LabelRemoval::git($root, ['commit', '-qm', 'Retitle fixture']);
            },
        ];
        foreach ($scenarios as $name => $setup) {
            [$project, $root, $database, $artifacts] = $this->environment();
            try {
                if ($setup !== null) $setup($root);
                $this->rebuild($root, $database);
                [$status, , $stderr] = $this->run([$root, $database, $artifacts], 'root-001');
                assertSame(0, $status, $stderr);
                assertSame([$name, false], [$name, str_contains($stderr, 'Rebuilding read model')], $stderr);
                $targeted = ReadModelSnapshot::take($database);
                $this->rebuild($root, $database);
                assertSame([], ReadModelSnapshot::differences(ReadModelSnapshot::take($database), $targeted), $name);
            } finally {
                $this->remove($project);
            }
        }
    }

    public function testRemovesAttachedImagesUnlessAnotherPostUsesThem(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            $store = $root . '-images';
            $only = $this->storeImage($store, 1);
            $shared = $this->storeImage($store, 2);
            @mkdir($store . '-thumbs', 0700, true);
            file_put_contents($store . '-thumbs/' . $only['sha256'] . '-sq.png', 'thumb');
            $this->commitReply($root, 'reply-img', 'root-001', '2026-06-01T00:00:04Z', ImageReference::line($only) . "\n");
            $this->commitReply($root, 'reply-shared', 'root-001', '2026-06-01T00:00:05Z', ImageReference::line($shared) . "\n");
            file_put_contents($root . '/records/posts/other-img.txt', "Post-ID: other-img\nCreated-At: 2026-06-01T00:00:06Z\nBoard-Tags: general\n\n" . ImageReference::line($shared) . "\n");
            LabelRemoval::git($root, ['add', 'records']);
            LabelRemoval::git($root, ['commit', '-qm', 'Other image post']);
            $this->rebuild($root, $database);

            [$status, $stdout, $stderr] = $this->run([$root, $database, $artifacts], 'root-001');
            assertSame(0, $status, $stderr);
            assertStringContains('removed_images: 1', $stdout);
            assertStringContains('Keeping image ' . $shared['sha256'] . ' (still referenced)', $stderr);
            assertSame(false, is_file($store . '/' . ImageReference::filename($only)));
            assertSame(false, is_file($store . '-thumbs/' . $only['sha256'] . '-sq.png'));
            assertSame(true, is_file($store . '/' . ImageReference::filename($shared)));
        } finally {
            $this->remove($project);
        }
    }

    public function testDeleteRecordAlsoRemovesItsImage(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            $image = $this->storeImage($root . '-images', 3);
            $this->commitReply($root, 'reply-img', 'root-001', '2026-06-01T00:00:04Z', ImageReference::line($image) . "\n");
            $this->rebuild($root, $database);
            $command = implode(' ', array_map('escapeshellarg', [dirname(__DIR__) . '/v3', 'delete-record', 'reply-img', $root, $database, $artifacts]));
            exec($command . ' 2>&1', $output, $status);
            assertSame(0, $status, implode("\n", $output));
            assertStringContains('removed_images: ["' . ImageReference::filename($image) . '"]', implode("\n", $output));
            assertSame(false, is_file($root . '-images/' . ImageReference::filename($image)));
        } finally {
            $this->remove($project);
        }
    }

    public function testRefusesAReplyId(): void
    {
        [$project, $root, $database, $artifacts] = $this->environment();
        try {
            [$status, , $stderr] = $this->run([$root, $database, $artifacts], 'reply-001');
            assertTrue($status !== 0);
            assertStringContains('reply-001 is a reply in thread root-001', $stderr);
            assertSame(true, is_file($root . '/records/posts/reply-001.txt'));
        } finally {
            $this->remove($project);
        }
    }

    /** @return array{int,string,string} */
    private function run(array $arguments, string $id): array
    {
        $command = implode(' ', array_map('escapeshellarg', [dirname(__DIR__) . '/v3', 'delete-thread', $id, ...$arguments]));
        $process = proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), (string) $stdout, (string) $stderr];
    }

    private function rebuild(string $root, string $database): void
    {
        (new ReadModelBuilder($root, $database, new CanonicalRecordRepository($root)))->rebuild();
    }

    private function commitReply(string $root, string $postId, string $parentId, string $createdAt, string $body = "Nested reply.\n"): void
    {
        file_put_contents($root . '/records/posts/' . $postId . '.txt', "Post-ID: {$postId}\nCreated-At: {$createdAt}\nBoard-Tags: general\nThread-ID: root-001\nParent-ID: {$parentId}\n\n{$body}");
        LabelRemoval::git($root, ['add', 'records']);
        LabelRemoval::git($root, ['commit', '-qm', 'Reply fixture ' . $postId]);
    }

    private function commitLabel(string $root, string $recordId, string $threadId, string $sourcePostId): void
    {
        $bytes = (string) file_get_contents($root . '/records/posts/' . $sourcePostId . '.txt');
        file_put_contents($root . '/records/thread-labels/' . $recordId . '.txt', "Record-ID: {$recordId}\nCreated-At: 2026-04-10T12:00:00Z\nThread-ID: {$threadId}\nOperation: add\nLabels: pinned\n"
            . "Source-Post-ID: {$sourcePostId}\nSource-Post-SHA256: " . hash('sha256', $bytes) . "\n\n");
        LabelRemoval::git($root, ['add', 'records']);
        LabelRemoval::git($root, ['commit', '-qm', 'Label fixture ' . $recordId]);
    }

    /** Stores a distinct 1x1 PNG in the image store and returns its reference. */
    private function storeImage(string $store, int $seed): array
    {
        @mkdir($store, 0700, true);
        $image = imagecreatetruecolor(1, 1);
        imagesetpixel($image, 0, 0, $seed);
        $file = tempnam(sys_get_temp_dir(), 'img');
        imagepng($image, $file);
        $reference = \ForumRewrite\Images\ImageStore::inspect($file, 'Picture ' . $seed);
        rename($file, $store . '/' . ImageReference::filename($reference));

        return $reference;
    }

    /** @return array{string,string,string,string} */
    private function environment(): array
    {
        $project = sys_get_temp_dir() . '/forum-rewrite-delete-thread-' . bin2hex(random_bytes(6));
        $fixture = $project . '/tests/fixtures/parity_minimal_v1';
        mkdir($fixture, 0777, true);
        exec('cp -R ' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1/.') . ' ' . escapeshellarg($fixture));
        LocalRepositoryBootstrap::initializeLocalRepository($project, $project . '/state/local_repository');
        mkdir($project . '/public', 0777, true);

        return [$project, $project . '/state/local_repository', $project . '/state/cache/post_index.sqlite3', $project . '/public'];
    }

    private function remove(string $path): void
    {
        exec('rm -rf ' . escapeshellarg($path));
    }
}
