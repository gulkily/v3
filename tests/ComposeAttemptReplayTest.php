<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Canonical\PostRecordParser;
use ForumRewrite\ReadModel\ReadModelConnection;
use ForumRewrite\Write\LocalWriteService;

final class ComposeAttemptReplayTest
{
    private const ATTEMPT = '0123456789abcdef0123456789abcdef';

    public function testRetriedThreadPublishesOnceWithServerTime(): void
    {
        [$repository, $service] = $this->environment();
        $commits = $this->commitCount($repository);
        $before = time();

        $first = $service->createThread($this->thread());
        $second = $service->createThread($this->thread());

        assertSame('thread-' . self::ATTEMPT, $first['post_id']);
        assertSame($first['post_id'], $second['post_id']);
        assertSame($first['commit_sha'], $second['commit_sha']);
        assertSame($commits + 1, $this->commitCount($repository));
        assertSame(true, $first['fastmod_new_post']);
        assertSame(false, $second['fastmod_new_post']);
        assertTrue(strtotime($this->record($repository, $first['post_id'])->createdAt) >= $before);
    }

    public function testRetriedReplyPublishesOnce(): void
    {
        [$repository, $service] = $this->environment();
        $commits = $this->commitCount($repository);

        $first = $service->createReply($this->reply());
        $second = $service->createReply($this->reply());

        assertSame('reply-' . self::ATTEMPT, $first['post_id']);
        assertSame($first['commit_sha'], $second['commit_sha']);
        assertSame('root-001', $second['thread_id']);
        assertSame($commits + 1, $this->commitCount($repository));
    }

    public function testDatedOrMalformedAttemptIsRefused(): void
    {
        [, $service] = $this->environment();
        foreach (['20010101000000-' . self::ATTEMPT, 'short', '', ['array']] as $attempt) {
            $this->assertRefused('Invalid compose attempt.', fn () => $service->createThread(['compose_attempt' => $attempt] + $this->thread()));
        }
    }

    public function testAttemptWithDifferentContentIsRefused(): void
    {
        [, $service] = $this->environment();
        $service->createThread($this->thread());

        $this->assertRefused('does not match its earlier send', fn () => $service->createThread(['body' => 'Other text'] + $this->thread()));
    }

    public function testRemovedPostIsNotRestored(): void
    {
        [$repository, $service] = $this->environment();
        $postId = 'thread-' . self::ATTEMPT;
        $path = $repository . '/' . LabelRemoval::path($postId);
        mkdir(dirname($path), 0777, true);
        file_put_contents($path, LabelRemoval::encode([
            'acceptance_version' => 2,
            'source_id' => $postId,
            'source_hash' => str_repeat('a', 64),
            'operation' => 'delete',
            'accepted_at' => '2026-11-04T00:00:00Z',
            'actor' => 'local-operator',
            'labels' => [],
            'post' => ['post_id' => $postId, 'thread_id' => $postId, 'parent_id' => null, 'created_at' => '2026-11-04T00:00:00Z', 'author_identity_id' => null, 'thread_type' => null],
        ]));

        $this->assertRefused('was removed', fn () => $service->createThread($this->thread()));
    }

    public function testConflictPageRetiresTheAttemptAndKeepsTheText(): void
    {
        [$repository, $service, , $application] = $this->environment();
        $service->createThread($this->thread());
        $commits = $this->commitCount($repository);

        $page = $this->post($application, '/compose/thread', ['body' => 'Other text'] + $this->thread());

        assertStringContains('data-compose-attempt-retired="' . self::ATTEMPT . '"', $page);
        assertStringContains('Other text', $page);
        assertSame($commits, $this->commitCount($repository));

        // The browser's next send carries a fresh ID and publishes.
        $this->post($application, '/compose/thread', ['body' => 'Other text', 'compose_attempt' => str_repeat('f', 32)] + $this->thread());
        assertSame($commits + 1, $this->commitCount($repository));
    }

    public function testReplyConflictPageRetiresTheAttempt(): void
    {
        [, $service, , $application] = $this->environment();
        $service->createReply($this->reply());

        $page = $this->post($application, '/compose/reply', ['body' => 'Other reply'] + $this->reply());

        assertStringContains('data-compose-attempt-retired="' . self::ATTEMPT . '"', $page);
        assertStringContains('Other reply', $page);
    }

    public function testRemovedPostPageRetiresTheAttempt(): void
    {
        [$repository, , , $application] = $this->environment();
        $this->post($application, '/compose/thread', $this->thread());
        $postId = 'thread-' . self::ATTEMPT;
        $path = $repository . '/' . LabelRemoval::path($postId);
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, LabelRemoval::encode([
            'acceptance_version' => 2,
            'source_id' => $postId,
            'source_hash' => str_repeat('a', 64),
            'operation' => 'delete',
            'accepted_at' => '2026-11-04T00:00:00Z',
            'actor' => 'local-operator',
            'labels' => [],
            'post' => ['post_id' => $postId, 'thread_id' => $postId, 'parent_id' => null, 'created_at' => '2026-11-04T00:00:00Z', 'author_identity_id' => null, 'thread_type' => null],
        ]));

        $page = $this->post($application, '/compose/thread', $this->thread());

        assertStringContains('data-compose-attempt-retired="' . self::ATTEMPT . '"', $page);
        assertStringContains('Replay body', $page);
    }

    public function testOtherErrorsKeepTheAttemptForRetry(): void
    {
        [$repository, , , $application] = $this->environment();
        $commits = $this->commitCount($repository);

        $page = $this->post($application, '/compose/thread', ['body' => ''] + $this->thread());

        assertStringContains('feedback-error', $page);
        assertSame($commits, $this->commitCount($repository));
        assertTrue(!str_contains($page, 'data-compose-attempt-retired'));
    }

    public function testUncommittedRecordFromFailedCommitIsFinishedOnRetry(): void
    {
        foreach ([false, true] as $staged) {
            [$repository, $service] = $this->environment();
            $service->createThread($this->thread());
            $postId = 'thread-' . self::ATTEMPT;
            $relative = 'records/posts/' . $postId . '.txt';
            $contents = (string) file_get_contents($repository . '/' . $relative);
            $this->git($repository, 'reset --hard HEAD~1');
            $backdated = preg_replace('/^Created-At: .*$/m', 'Created-At: 2026-01-02T03:04:05Z', $contents);
            file_put_contents($repository . '/' . $relative, $backdated);
            if ($staged) {
                $this->git($repository, 'add -- ' . $relative);
            }

            $retried = $service->createThread($this->thread());

            assertSame($postId, $retried['post_id']);
            assertSame(true, $retried['fastmod_new_post']);
            assertSame('', $this->git($repository, 'status --porcelain -- ' . $relative));
            assertSame('2026-01-02T03:04:05Z', $this->record($repository, $postId)->createdAt);
        }
    }

    public function testUnreadablePartialWriteIsReplaced(): void
    {
        [$repository, $service] = $this->environment();
        $relative = 'records/posts/thread-' . self::ATTEMPT . '.txt';
        file_put_contents($repository . '/' . $relative, 'Post-I');

        $result = $service->createThread($this->thread());

        assertSame('', $this->git($repository, 'status --porcelain -- ' . $relative));
        assertSame('Replay body', trim($this->record($repository, $result['post_id'])->body));
    }

    public function testReplayRepairsReadModelAfterRequestDiedPostCommit(): void
    {
        [, $service, $databasePath] = $this->environment();
        $postId = $service->createThread($this->thread())['post_id'];
        (new ReadModelConnection($databasePath))->open()->prepare('DELETE FROM posts WHERE post_id = ?')->execute([$postId]);

        $retried = $service->createThread($this->thread());

        assertSame(true, $retried['fastmod_new_post']);
        $query = (new ReadModelConnection($databasePath))->open()->prepare('SELECT COUNT(*) FROM posts WHERE post_id = ?');
        $query->execute([$postId]);
        assertSame(1, (int) $query->fetchColumn());
    }

    public function testComposeFormRetryRedirectsToTheSamePost(): void
    {
        [$repository, , , $application] = $this->environment();
        $commits = $this->commitCount($repository);
        $previous = $_POST;
        try {
            $_POST = $this->thread();
            foreach ([1, 2] as $_) {
                ob_start();
                $application->handle('POST', '/compose/thread');
                ob_end_clean();
            }
        } finally {
            $_POST = $previous;
        }

        assertSame($commits + 1, $this->commitCount($repository));
        assertTrue(is_file($repository . '/records/posts/thread-' . self::ATTEMPT . '.txt'));
    }

    public function testRequestsWithoutAttemptStillMintDatedIds(): void
    {
        [$repository, $service] = $this->environment();
        $input = $this->thread();
        unset($input['compose_attempt']);

        $first = $service->createThread($input);
        $second = $service->createThread($input);

        assertTrue($first['post_id'] !== $second['post_id']);
        assertTrue(preg_match('/^thread-\d{14}-[a-f0-9]{8}$/', $first['post_id']) === 1);
        assertTrue(count(glob($repository . '/records/posts/*/*/*/' . $first['post_id'] . '.txt') ?: []) === 1);
    }

    /** @param array<string, mixed> $fields */
    private function post(Application $application, string $path, array $fields): string
    {
        $previous = $_POST;
        try {
            $_POST = $fields;
            ob_start();
            $application->handle('POST', $path);

            return (string) ob_get_clean();
        } finally {
            $_POST = $previous;
        }
    }

    private function assertRefused(string $message, callable $action): void
    {
        try {
            $action();
        } catch (RuntimeException $error) {
            assertStringContains($message, $error->getMessage());
            return;
        }
        throw new RuntimeException('Expected a refusal containing "' . $message . '".');
    }

    /** @return array<string, string> */
    private function thread(): array
    {
        return ['board_tags' => 'general', 'subject' => 'Replay subject', 'body' => 'Replay body', 'compose_attempt' => self::ATTEMPT];
    }

    /** @return array<string, string> */
    private function reply(): array
    {
        return ['thread_id' => 'root-001', 'parent_id' => 'root-001', 'board_tags' => 'general', 'body' => 'Replay body', 'compose_attempt' => self::ATTEMPT];
    }

    private function record(string $repository, string $postId): \ForumRewrite\Canonical\PostRecord
    {
        return (new PostRecordParser())->parse((string) file_get_contents($repository . '/records/posts/' . $postId . '.txt'));
    }

    private function commitCount(string $repository): int
    {
        return (int) $this->git($repository, 'rev-list --count HEAD');
    }

    private function git(string $repository, string $arguments): string
    {
        $output = [];
        $exitCode = 0;
        exec('git -C ' . escapeshellarg($repository) . ' ' . $arguments . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('git ' . $arguments . ': ' . implode("\n", $output));
        }

        return implode("\n", $output);
    }

    /** @return array{string, LocalWriteService, string, Application} */
    private function environment(): array
    {
        $base = sys_get_temp_dir() . '/forum-rewrite-compose-attempt-' . bin2hex(random_bytes(6));
        $repository = $base . '/repo';
        mkdir($repository, 0777, true);
        $source = __DIR__ . '/fixtures/parity_minimal_v1';
        exec('cp -R ' . escapeshellarg($source . '/.') . ' ' . escapeshellarg($repository));
        $this->git($repository, 'init');
        $this->git($repository, 'config user.name "Forum Rewrite"');
        $this->git($repository, 'config user.email "forum-rewrite@example.invalid"');
        $this->git($repository, 'add .');
        $this->git($repository, 'commit -m "Initialize test repository"');
        $databasePath = $base . '/read.sqlite3';
        $artifactRoot = $base . '/public';
        mkdir($artifactRoot, 0777, true);
        $application = new Application(dirname(__DIR__), $repository, $databasePath, $artifactRoot);
        ob_start();
        $application->handle('GET', '/');
        ob_end_clean();

        return [$repository, new LocalWriteService($repository, $databasePath, $artifactRoot, new CanonicalRecordRepository($repository)), $databasePath, $application];
    }
}
