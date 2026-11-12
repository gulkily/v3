<?php

declare(strict_types=1);

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Support\LocalRepositoryBootstrap;

/** `./v3 delete-thread <thread-id>`: removes every reply, then the root, one removal per post. */
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
            assertStringContains('] [1/3] Deleting reply-002', $stderr);
            assertStringContains('] [3/3] Deleting root-001', $stderr);
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

    private function commitReply(string $root, string $postId, string $parentId, string $createdAt): void
    {
        file_put_contents($root . '/records/posts/' . $postId . '.txt', "Post-ID: {$postId}\nCreated-At: {$createdAt}\nBoard-Tags: general\nThread-ID: root-001\nParent-ID: {$parentId}\n\nNested reply.\n");
        LabelRemoval::git($root, ['add', 'records']);
        LabelRemoval::git($root, ['commit', '-qm', 'Reply fixture ' . $postId]);
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
