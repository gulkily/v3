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
        assertStringContains('./v3 task-queue enqueue-offline-snapshot', $stdout);
        assertStringContains('./v3 task-queue reset-recovery', $stdout);
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
        assertStringContains('Executor: not_observed (last completed: none)', $statusOutput);
        assertSame('', $statusError);
        assertSame(0, $dryRunCode);
        assertStringContains('Task queue dry run', $dryRunOutput);
        assertStringContains('Queued tasks: 1', $dryRunOutput);
        assertStringContains('Fastmod provider-call limit: 25', $dryRunOutput);
        assertStringContains('Fastmod examined-work limit: 250', $dryRunOutput);
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
        assertStringNotContains('enqueue-offline-snapshot', $stdout);
        assertStringContains('/tmp/forum-task-queue-test.log', $stdout);
        assertSame('', $stderr);
    }

    public function testTaskQueueCronReferenceDefaultsItsLogToPrivateApplicationState(): void
    {
        [$exitCode, $stdout, $stderr] = $this->runCommand(dirname(__DIR__), './v3 task-queue cron');

        assertSame(0, $exitCode);
        assertStringContains('/state/private/task_queue_cron.log', $stdout);
        assertSame('', $stderr);
    }

    public function testQuietEmptyWorkerRunRecordsExecutorHistory(): void
    {
        $queuePath = sys_get_temp_dir() . '/forum-task-queue-history-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            [$exitCode, $stdout, $stderr] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue run --quiet --queue-database-path=' . escapeshellarg($queuePath),
            );
            $runs = (new \ForumRewrite\TaskQueue\SqliteTaskQueueStore(new \PDO('sqlite:' . $queuePath)))->recentExecutorRuns(1);
        } finally {
            @unlink($queuePath);
        }

        assertSame(0, $exitCode);
        assertSame('', $stdout);
        assertSame('', $stderr);
        assertSame(1, count($runs));
        assertSame('completed', $runs[0]['status']);
        assertSame(0, $runs[0]['summary']['claimed']);
        assertSame([], $runs[0]['task_outcomes']);
    }

    public function testRebuildWorkerRecordsPrivateProgressCheckpoints(): void
    {
        $queuePath = sys_get_temp_dir() . '/forum-task-queue-progress-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $databasePath = sys_get_temp_dir() . '/forum-task-queue-read-model-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            [$enqueueCode] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue enqueue-rebuild --queue-database-path=' . escapeshellarg($queuePath),
            );
            [$runCode, $runOutput, $runError] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue run --queue-database-path=' . escapeshellarg($queuePath)
                . ' --repository-root=' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1')
                . ' --database-path=' . escapeshellarg($databasePath),
            );
            $store = new \ForumRewrite\TaskQueue\SqliteTaskQueueStore(new \PDO('sqlite:' . $queuePath));
            [$statusCode, $statusOutput] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue status --queue-database-path=' . escapeshellarg($queuePath),
            );
        } finally {
            @unlink($queuePath);
            @unlink($databasePath);
        }

        assertSame(0, $enqueueCode);
        assertSame(0, $runCode);
        assertSame('', $runError);
        assertStringContains('Read model: index posts', $runOutput);
        assertTrue(count($store->recentTaskProgress()) > 0);
        assertSame(0, $statusCode);
        assertStringContains('progress=Read model:', $statusOutput);
    }

    public function testTaskQueueRecoveryResetCommandIsOperatorControlled(): void
    {
        $queuePath = sys_get_temp_dir() . '/forum-task-queue-reset-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            $store = new \ForumRewrite\TaskQueue\SqliteTaskQueueStore(new \PDO('sqlite:' . $queuePath));
            $request = $store->requestAutomaticRebuild(\ForumRewrite\TaskQueue\SqliteTaskQueueStore::READ_MODEL_SCHEMA_RECOVERY_REASON, 1);
            $claimed = $store->claimNext()[0];
            $store->markFailed($claimed['id'], 'test_failure', 'Test failure.', true);

            [$exitCode, $stdout, $stderr] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue reset-recovery --queue-database-path=' . escapeshellarg($queuePath),
            );
        } finally {
            @unlink($queuePath);
        }

        assertSame(true, $request['task'] !== null);
        assertSame(0, $exitCode);
        assertStringContains('Automatic rebuild recovery reset.', $stdout);
        assertSame('', $stderr);
    }

    public function testTaskQueueUnknownOptionsShowUsageWithoutAPhpStackTrace(): void
    {
        foreach (['--test', '--unknown'] as $option) {
            [$exitCode, $stdout, $stderr] = $this->runCommand(
                dirname(__DIR__),
                './v3 task-queue run ' . $option,
            );

            assertSame(1, $exitCode);
            assertSame('', $stdout);
            assertStringContains('Error: Unknown option: ' . $option, $stderr);
            assertStringContains('php scripts/task_queue.php run', $stderr);
            assertStringNotContains('Stack trace:', $stderr);
        }
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

    public function testTaskQueuePublishesEnqueuedOfflineSnapshot(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $base = sys_get_temp_dir() . '/forum-task-queue-offline-snapshot-' . $suffix;
        $repositoryRoot = $base . '/repository';
        $databasePath = $base . '/read-model.sqlite3';
        $staticHtmlRoot = $base . '/static_html';
        $queuePath = $base . '/internal_tasks.sqlite3';
        mkdir($repositoryRoot . '/records', 0700, true);
        $this->createOfflineSnapshotSource($databasePath);
        try {
            $arguments = ' --repository-root=' . escapeshellarg($repositoryRoot)
                . ' --database-path=' . escapeshellarg($databasePath)
                . ' --static-html-root=' . escapeshellarg($staticHtmlRoot)
                . ' --queue-database-path=' . escapeshellarg($queuePath);
            [$enqueueCode, $enqueueOutput, $enqueueError] = $this->runCommand(
                dirname(__DIR__),
                'FORUM_APPROVED_MEMBERS_ONLY=false ./v3 task-queue enqueue-offline-snapshot' . $arguments,
            );
            [$runCode, $runOutput, $runError] = $this->runCommand(
                dirname(__DIR__),
                'FORUM_APPROVED_MEMBERS_ONLY=false ./v3 task-queue run' . $arguments,
            );
            $published = is_file($staticHtmlRoot . '/offline/snapshot.sqlite3');
        } finally {
            $this->removeTree($base);
        }

        assertSame(0, $enqueueCode);
        assertStringContains('Task enqueued: id=', $enqueueOutput);
        assertSame('', $enqueueError);
        assertSame(0, $runCode);
        assertStringContains('Finished task id=', $runOutput);
        assertSame('', $runError);
        assertTrue($published);
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
            [$code, $stdout, $stderr] = $this->runCommand(dirname(__DIR__), './v3 task-queue run --verbose --score-limit=3 --work-limit=7 --queue-database-path=' . escapeshellarg($queuePath) . ' --database-path=' . escapeshellarg($readPath) . ' --repository-root=' . escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'));
        } finally {
            $previousSecrets === false ? putenv('FORUM_SECRETS_PATH') : putenv('FORUM_SECRETS_PATH=' . $previousSecrets);
            @unlink($queuePath);
            @unlink($readPath);
            @unlink($scorePath);
            @unlink($secretsPath);
        }

        assertSame(0, $code);
        assertStringContains('Fastmod provider-call limit: 3', $stdout);
        assertStringContains('Fastmod examined-work limit: 7', $stdout);
        assertStringContains('Fastmod progress: examined=1/7 post=post-1 status=disabled', $stdout);
        assertStringContains('Fastmod sweep: examined=1 processed=1 provider_calls=0', $stdout);
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

    private function createOfflineSnapshotSource(string $path): void
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE threads (root_post_id TEXT PRIMARY KEY, root_post_created_at TEXT, last_activity_at TEXT, subject TEXT, body_preview TEXT, board_tags_json TEXT, thread_labels_json TEXT, score_total INTEGER)');
        $pdo->exec('CREATE TABLE posts (post_id TEXT PRIMARY KEY, created_at TEXT, thread_id TEXT, parent_id TEXT, subject TEXT, body TEXT, board_tags_json TEXT, thread_type TEXT, author_label TEXT, author_profile_slug TEXT, sequence_number INTEGER, is_hidden INTEGER)');
        $pdo->exec("INSERT INTO threads VALUES ('root', '2026-01-01T00:00:00Z', '2026-01-01T00:00:00Z', 'Subject', 'Preview', '[]', '[]', 0)");
        $pdo->exec("INSERT INTO posts VALUES ('root', '2026-01-01T00:00:00Z', 'root', NULL, 'Subject', 'Body', '[]', NULL, 'Author', NULL, 1, 0)");
    }

    private function removeTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        foreach (scandir($path) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $entryPath = $path . '/' . $entry;
            is_dir($entryPath) ? $this->removeTree($entryPath) : @unlink($entryPath);
        }
        @rmdir($path);
    }
}
