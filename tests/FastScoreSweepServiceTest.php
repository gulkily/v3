<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Llm\LlmProviderConfig;
use ForumRewrite\Scoring\DeterministicFastScoreEvaluator;
use ForumRewrite\Scoring\FastScoreContextFactory;
use ForumRewrite\Scoring\FastScoreProvider;
use ForumRewrite\Scoring\FastScoreSweepService;
use ForumRewrite\Scoring\FastScoreWorkflowService;
use ForumRewrite\Scoring\FastScoringConfig;
use ForumRewrite\Scoring\SqliteFastScoreStore;

final class FastScoreSweepServiceTest
{
    public function testScoresRootsAndRepliesOnceInStableBoundedBatches(): void
    {
        $pdo = $this->postsDatabase();
        $this->insertPost($pdo, 'root', 'root', null, 'Root', 'Root text', 1);
        $this->insertPost($pdo, 'reply', 'root', 'root', '', 'Reply text', 2);
        $this->insertPost($pdo, 'other', 'other', null, '', 'Other text', 3);
        $provider = new SweepFakeProvider();
        $service = $this->service($pdo, $provider, 'rubric-a');
        $this->enqueue($pdo, 'root', 'rubric-a');
        $this->enqueue($pdo, 'reply', 'rubric-a');
        $this->enqueue($pdo, 'other', 'rubric-a');

        $first = $service->run(2);
        $second = $service->run(2);
        $third = $service->run(2);

        assertSame(['root', 'reply', 'other'], $provider->postIds);
        assertSame(['processed' => 2, 'scored' => 2, 'excluded' => 0, 'failed' => 0, 'remaining' => true], $first);
        assertSame(['processed' => 1, 'scored' => 1, 'excluded' => 0, 'failed' => 0, 'remaining' => false], $second);
        assertSame(['processed' => 0, 'scored' => 0, 'excluded' => 0, 'failed' => 0, 'remaining' => false], $third);
        assertSame("Root\n\nRoot text", $provider->contexts['reply']['reply_context']['parent_text']);
    }

    public function testChangedContentAndRubricRevisionAreRatedAgain(): void
    {
        $pdo = $this->postsDatabase();
        $this->insertPost($pdo, 'post', 'post', null, '', 'Original', 1);
        $provider = new SweepFakeProvider();
        $this->enqueue($pdo, 'post', 'rubric-a');
        $this->service($pdo, $provider, 'rubric-a')->run(10);
        $pdo->exec("UPDATE posts SET body = 'Edited' WHERE post_id = 'post'");
        $this->service($pdo, $provider, 'rubric-a')->run(10);
        $this->service($pdo, $provider, 'rubric-b')->run(10);

        assertSame(['post'], $provider->postIds);
    }

    public function testRecordsIndividualProviderFailuresAndContinuesTheBatch(): void
    {
        $pdo = $this->postsDatabase();
        $this->insertPost($pdo, 'bad', 'bad', null, '', 'Bad', 1);
        $this->insertPost($pdo, 'good', 'good', null, '', 'Good', 2);
        $provider = new SweepFakeProvider(['bad']);
        $service = $this->service($pdo, $provider, 'rubric-a');
        $this->enqueue($pdo, 'bad', 'rubric-a');
        $this->enqueue($pdo, 'good', 'rubric-a');

        $summary = $service->run(10);

        assertSame(['processed' => 2, 'scored' => 1, 'excluded' => 0, 'failed' => 1, 'remaining' => true], $summary);
        assertSame(['bad', 'good'], $provider->postIds);
    }

    public function testProcessesAuthorizedBackfillWithinTheReservedCostBound(): void
    {
        $pdo = $this->postsDatabase();
        $this->insertPost($pdo, 'post-1', 'post-1', null, '', 'First', 1);
        $this->insertPost($pdo, 'post-2', 'post-2', null, '', 'Second', 2);
        $provider = new SweepFakeProvider();
        $store = new SqliteFastScoreStore($pdo);
        $fetchPost = static function (string $id) use ($pdo): ?array {
            $statement = $pdo->prepare('SELECT post_id, thread_id, parent_id, subject, body FROM posts WHERE post_id = :post_id');
            $statement->execute(['post_id' => $id]);
            $post = $statement->fetch();
            return $post === false ? null : $post;
        };
        $contexts = new FastScoreContextFactory($fetchPost);
        $store->createBackfillBatch('rubric-a', 2, 0.02, 0.01, [
            ['post_id' => 'post-1', 'content_hash' => $contexts->contentHashForPost($fetchPost('post-1'))],
            ['post_id' => 'post-2', 'content_hash' => $contexts->contentHashForPost($fetchPost('post-2'))],
        ]);
        $service = $this->service($pdo, $provider, 'rubric-a');

        $first = $service->run(1);
        $second = $service->run(1);

        assertSame(['post-1', 'post-2'], $provider->postIds);
        assertSame(true, $first['remaining']);
        assertSame(false, $second['remaining']);
        assertSame('completed', $pdo->query('SELECT status FROM fastmod_backfill_batches')->fetchColumn());
        assertSame(0.02, (float) $pdo->query('SELECT reserved_cost_usd FROM fastmod_backfill_batches')->fetchColumn());
    }

    private function service(PDO $pdo, SweepFakeProvider $provider, string $rubricRevision): FastScoreSweepService
    {
        $fetchPost = static function (string $postId) use ($pdo): ?array {
            $statement = $pdo->prepare('SELECT post_id, thread_id, parent_id, subject, body FROM posts WHERE post_id = :post_id');
            $statement->execute(['post_id' => $postId]);
            $post = $statement->fetch();
            return $post === false ? null : $post;
        };
        $contextFactory = new FastScoreContextFactory($fetchPost);
        $workflow = new FastScoreWorkflowService(
            new FastScoringConfig(true, new LlmProviderConfig('stub', '', '', '', 1, ''), ''),
            $contextFactory,
            new DeterministicFastScoreEvaluator(),
            $provider,
        );

        return new FastScoreSweepService($pdo, $contextFactory, $workflow, new SqliteFastScoreStore($pdo), $rubricRevision);
    }

    private function postsDatabase(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE posts (post_id TEXT PRIMARY KEY, thread_id TEXT NOT NULL, parent_id TEXT NULL, subject TEXT NULL, body TEXT NOT NULL, sequence_number INTEGER NOT NULL)');
        return $pdo;
    }

    private function insertPost(PDO $pdo, string $postId, string $threadId, ?string $parentId, string $subject, string $body, int $sequenceNumber): void
    {
        $statement = $pdo->prepare('INSERT INTO posts (post_id, thread_id, parent_id, subject, body, sequence_number) VALUES (:post_id, :thread_id, :parent_id, :subject, :body, :sequence_number)');
        $statement->execute([
            'post_id' => $postId,
            'thread_id' => $threadId,
            'parent_id' => $parentId,
            'subject' => $subject,
            'body' => $body,
            'sequence_number' => $sequenceNumber,
        ]);
    }

    private function enqueue(PDO $pdo, string $postId, string $rubricRevision): void
    {
        $fetchPost = static function (string $id) use ($pdo): ?array {
            $statement = $pdo->prepare('SELECT post_id, thread_id, parent_id, subject, body FROM posts WHERE post_id = :post_id');
            $statement->execute(['post_id' => $id]);
            $post = $statement->fetch();
            return $post === false ? null : $post;
        };
        $post = $fetchPost($postId);
        assertTrue($post !== null);
        $context = (new FastScoreContextFactory($fetchPost))->forPost($post);
        (new SqliteFastScoreStore($pdo))->enqueueWork($postId, (string) $context['content_hash'], $rubricRevision);
    }
}

final class SweepFakeProvider implements FastScoreProvider
{
    /** @var list<string> */
    public array $postIds = [];
    /** @var array<string, array<string, mixed>> */
    public array $contexts = [];

    /** @param list<string> $failingPostIds */
    public function __construct(private readonly array $failingPostIds = [])
    {
    }

    public function score(array $context): array
    {
        $postId = (string) $context['post_id'];
        $this->postIds[] = $postId;
        $this->contexts[$postId] = $context;
        if (in_array($postId, $this->failingPostIds, true)) {
            return ['status' => 'provider_error', 'probability' => null, 'source' => 'none', 'signals' => ['provider_error']];
        }

        return ['status' => 'scored', 'probability' => 0.6, 'source' => 'llm', 'signals' => []];
    }
}
