<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\TaskQueue\TaskQueueDatabaseConfig;
use ForumRewrite\Scoring\FastScoreContextFactory;
use ForumRewrite\Scoring\SqliteFastScoreStore;

final class TaskQueueCommandTest
{
    public function testDefaultQueuePathIsPrivateProjectState(): void
    {
        assertSame(
            '/tmp/forum/state/private/internal_tasks.sqlite3',
            TaskQueueDatabaseConfig::path('/tmp/forum', '')
        );
    }

    public function testRootUsageShowsTaskQueueCommands(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(dirname(__DIR__), './v3');

        assertSame(1, $exitCode);
        assertStringContains('./v3 task-queue enqueue-rebuild', $stdout);
        assertStringContains('./v3 task-queue enqueue-fast-score', $stdout);
        assertStringContains('./v3 task-queue run', $stdout);
        assertStringContains('./v3 task-queue status', $stdout);
        assertStringContains('./v3 task-queue cron', $stdout);
        assertSame('', $stderr);
    }

    public function testTaskQueueCommandsEnqueueInspectAndDryRun(): void
    {
        $queuePath = sys_get_temp_dir() . '/forum-task-queue-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $progressQueuePath = sys_get_temp_dir() . '/forum-task-queue-progress-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            [$firstCode, $firstOutput, $firstError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue enqueue-rebuild --queue-database-path=' . escapeshellarg($queuePath)
            );
            [$secondCode, $secondOutput, $secondError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue enqueue-rebuild --queue-database-path=' . escapeshellarg($queuePath)
            );
            [$statusCode, $statusOutput, $statusError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue status --queue-database-path=' . escapeshellarg($queuePath)
            );
            [$dryRunCode, $dryRunOutput, $dryRunError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue run --dry-run --queue-database-path=' . escapeshellarg($queuePath)
            );
            $progressPdo = new PDO('sqlite:' . $progressQueuePath);
            new \ForumRewrite\TaskQueue\SqliteTaskQueueStore($progressPdo);
            $progressPdo->exec(
                "INSERT INTO internal_tasks (type, deduplication_key, status, attempts, max_attempts, requested_at)
                 VALUES ('unknown', 'progress-task', 'queued', 0, 1, '2026-01-01T00:00:00+00:00')"
            );
            [$runCode, $runOutput, $runError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue run --queue-database-path=' . escapeshellarg($progressQueuePath)
            );
        } finally {
            @unlink($queuePath);
            @unlink($progressQueuePath);
        }

        assertSame(0, $firstCode);
        assertStringContains('Task enqueued: id=', $firstOutput);
        assertSame('', $firstError);
        assertSame(0, $secondCode);
        assertStringContains('Task already outstanding: id=', $secondOutput);
        assertSame('', $secondError);
        assertSame(0, $statusCode);
        assertStringContains('Queued: 1, running: 0, completed: 0, failed: 0', $statusOutput);
        assertSame('', $statusError);
        assertSame(0, $dryRunCode);
        assertStringContains('Task queue dry run', $dryRunOutput);
        assertStringContains('Queued tasks: 1', $dryRunOutput);
        assertSame('', $dryRunError);
        assertSame(0, $runCode);
        assertStringContains('Task queue worker starting', $runOutput);
        assertStringContains('Starting task id=', $runOutput);
        assertStringContains('Finished task id=', $runOutput);
        assertStringContains('Queue after:', $runOutput);
        assertStringContains('Elapsed:', $runOutput);
        assertSame('', $runError);
    }

    public function testTaskQueueCronReferenceUsesWorkerCommand(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(
            dirname(__DIR__),
            './v3 task-queue cron --log=/tmp/forum-task-queue-test.log'
        );

        assertSame(0, $exitCode);
        assertStringContains('Task queue cron reference', $stdout);
        assertStringContains('php scripts/task_queue.php run --quiet --limit=1', $stdout);
        assertStringContains('/tmp/forum-task-queue-test.log', $stdout);
        assertSame('', $stderr);
    }

    public function testTaskQueueFastScoreEnqueueCoalesces(): void
    {
        $queuePath = sys_get_temp_dir() . '/forum-fast-score-queue-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            [$firstCode, $firstOutput, $firstError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue enqueue-fast-score --queue-database-path=' . escapeshellarg($queuePath)
            );
            [$secondCode, $secondOutput, $secondError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue enqueue-fast-score --queue-database-path=' . escapeshellarg($queuePath)
            );
        } finally {
            @unlink($queuePath);
        }

        assertSame(0, $firstCode);
        assertStringContains('Task enqueued: id=', $firstOutput);
        assertSame('', $firstError);
        assertSame(0, $secondCode);
        assertStringContains('Task already outstanding: id=', $secondOutput);
        assertSame('', $secondError);
    }

    public function testFastScoreWorkerCapturesRepositoryForFeatureFlagEvaluation(): void
    {
        $queuePath = sys_get_temp_dir() . '/forum-fast-score-worker-queue-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $readPath = sys_get_temp_dir() . '/forum-fast-score-worker-read-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $scorePath = sys_get_temp_dir() . '/forum-fast-score-worker-score-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $secretsPath = tempnam(sys_get_temp_dir(), 'forum-fast-score-secrets-');
        $previousSecrets = getenv('FORUM_SECRETS_PATH');
        file_put_contents($secretsPath, "<?php return ['FAST_SCORING_ENABLED' => false, 'FAST_SCORING_DATABASE_PATH' => " . var_export($scorePath, true) . ", 'LLM_CONVERSATION_RECORDING_ENABLED' => false];\n");
        putenv('FORUM_SECRETS_PATH=' . $secretsPath);
        try {
            $read = new PDO('sqlite:' . $readPath);
            $read->exec('CREATE TABLE posts (post_id TEXT PRIMARY KEY, thread_id TEXT NOT NULL, parent_id TEXT NULL, subject TEXT NULL, body TEXT NOT NULL, sequence_number INTEGER NOT NULL)');
            $read->exec("INSERT INTO posts VALUES ('post-1', 'post-1', NULL, 'Subject', 'Body', 1)");
            $fetchPost = static function (string $postId) use ($read): ?array {
                $stmt = $read->prepare('SELECT post_id, thread_id, parent_id, subject, body FROM posts WHERE post_id = :post_id');
                $stmt->execute(['post_id' => $postId]);
                $post = $stmt->fetch(PDO::FETCH_ASSOC);
                return $post === false ? null : $post;
            };
            $context = (new FastScoreContextFactory($fetchPost))->forPost($fetchPost('post-1'));
            (new SqliteFastScoreStore(new PDO('sqlite:' . $scorePath)))->enqueueWork('post-1', (string) $context['content_hash'], 'rubric-a');
            (new \ForumRewrite\TaskQueue\SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath)))->enqueue(\ForumRewrite\TaskQueue\SqliteTaskQueueStore::FAST_SCORE_SWEEP, 'fast-score-sweep');
            [$code, $stdout, $stderr] = $this->runCommand(dirname(__DIR__), './v3 task-queue run --queue-database-path=' . escapeshellarg($queuePath) . ' --database-path=' . escapeshellarg($readPath) . ' --repository-root=' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'));
        } finally {
            $previousSecrets === false ? putenv('FORUM_SECRETS_PATH') : putenv('FORUM_SECRETS_PATH=' . $previousSecrets);
            @unlink($queuePath);
            @unlink($readPath);
            @unlink($scorePath);
            @unlink($secretsPath);
        }

        assertSame(0, $code);
        assertStringContains('Fastmod sweep: processed=1', $stdout);
        assertSame('', $stderr);
    }

    /**
     * @return array{0:int, 1:string, 2:string}
     */
    private function runCommand(string $cwd, string $command): array
    {
        $descriptor = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];
        $process = proc_open($command, $descriptor, $pipes, $cwd);
        if (!is_resource($process)) {
            throw new RuntimeException('Unable to run command.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), (string) $stdout, (string) $stderr];
    }
}
