<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\ReadModel\ReadModelCapabilityInspector;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

final class ForteActivityReadModelRecoveryTest
{
    public function testCapabilityInspectorRequiresCompleteCommitsShape(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $inspector = new ReadModelCapabilityInspector();
        $pdo->exec('CREATE TABLE commits (id INTEGER PRIMARY KEY)');
        assertSame(false, $inspector->commitsAvailable($pdo));

        $pdo->exec('DROP TABLE commits');
        $pdo->exec(
            'CREATE TABLE commits (
                id INTEGER PRIMARY KEY,
                sha TEXT NOT NULL,
                author_name TEXT NOT NULL,
                author_email TEXT NOT NULL,
                committed_at TEXT NOT NULL,
                subject TEXT NOT NULL,
                file_count INTEGER NOT NULL
            )'
        );
        assertSame(true, $inspector->commitsAvailable($pdo));
    }

    public function testForteActivityDegradesAndQueuesOneRebuildWhenCommitsAreMissing(): void
    {
        [$application, $databasePath, $queuePath, $previousQueuePath] = $this->applicationWithoutCommits();
        try {
            $controller = $this->invokePrivate($application, 'forteActivityController', [])['return'];
            $page = $this->invokePrivate($controller, 'board', ['', '', '', ''])['return'];
            $api = $this->invokePrivate($controller, 'paginationPage', [['view' => 'commits']]);
            $taskStore = new SqliteTaskQueueStore(new PDO('sqlite:' . $queuePath));

            assertStringContains('Commit history is temporarily unavailable while site data updates.', $page);
            assertStringNotContains('data-paned-activity-view="commits"', $page);
            assertStringNotContains('SQLSTATE', $page);
            assertSame(null, $api['return']);
            $payload = json_decode($api['output'], true, 512, JSON_THROW_ON_ERROR);
            assertSame('read_model_capability_unavailable', $payload['error']);
            assertSame(1, $taskStore->counts()['queued']);
        } finally {
            $this->restoreQueuePath($previousQueuePath);
            @unlink($databasePath);
            @unlink($queuePath);
        }
    }

    /**
     * @return array{0:Application,1:string,2:string,3:string|false}
     */
    private function applicationWithoutCommits(): array
    {
        $databasePath = sys_get_temp_dir() . '/forum-activity-read-model-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $queuePath = sys_get_temp_dir() . '/forum-activity-queue-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $previousQueuePath = getenv('FORUM_TASK_QUEUE_DATABASE_PATH');
        putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $queuePath);

        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->exec('CREATE TABLE posts (post_id TEXT PRIMARY KEY, is_hidden INTEGER NOT NULL DEFAULT 0)');
        $pdo->exec(
            'CREATE TABLE activity (
                id INTEGER PRIMARY KEY,
                created_at TEXT NOT NULL,
                kind TEXT NOT NULL,
                record_family TEXT NOT NULL,
                action_key TEXT NULL,
                post_id TEXT NULL,
                thread_id TEXT NULL,
                label TEXT NOT NULL,
                board_tags_json TEXT NOT NULL,
                author_identity_id TEXT NULL,
                source_path TEXT NULL,
                source_commit_sha TEXT NULL,
                author_label TEXT NOT NULL,
                author_profile_slug TEXT NULL,
                author_username_token TEXT NULL,
                author_is_approved INTEGER NOT NULL
            )'
        );

        return [
            new Application(dirname(__DIR__), dirname(__DIR__) . '/state/local_repository', $databasePath),
            $databasePath,
            $queuePath,
            $previousQueuePath,
        ];
    }

    /**
     * @param list<mixed> $arguments
     * @return array{return:mixed,output:string}
     */
    private function invokePrivate(object $target, string $method, array $arguments): array
    {
        $reflection = new ReflectionMethod($target, $method);
        $reflection->setAccessible(true);
        ob_start();
        try {
            $result = $reflection->invokeArgs($target, $arguments);
            $output = (string) ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        return ['return' => $result, 'output' => $output];
    }

    private function restoreQueuePath(string|false $previousQueuePath): void
    {
        if ($previousQueuePath === false) {
            putenv('FORUM_TASK_QUEUE_DATABASE_PATH');
            return;
        }

        putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $previousQueuePath);
    }
}
