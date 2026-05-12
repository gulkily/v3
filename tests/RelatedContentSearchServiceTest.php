<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Analysis\RelatedContentSearchService;

final class RelatedContentSearchServiceTest
{
    public function testFindRelatedContentReturnsRankedCrossThreadMatches(): void
    {
        $pdo = $this->pdoWithContent();
        $service = new RelatedContentSearchService($pdo);

        $matches = $service->findRelatedContent([
            'post_id' => 'new-post',
            'thread_id' => 'new-post',
            'subject' => 'How should reply agent context work?',
            'body' => 'Could the reply agent use prior context and answer repeated questions?',
        ], 2);

        assertSame('root-related', $matches[0]['post_id']);
        assertSame('/posts/root-related', $matches[0]['post_url']);
        assertSame('/threads/root-related', $matches[0]['thread_url']);
        assertSame('Reply agent context', $matches[0]['subject']);
        assertSame(true, str_contains($matches[0]['excerpt'], 'prior context'));
    }

    public function testFindRelatedContentExcludesTargetAndSameThreadPosts(): void
    {
        $pdo = $this->pdoWithContent();
        $service = new RelatedContentSearchService($pdo);

        $matches = $service->findRelatedContent([
            'post_id' => 'reply-target',
            'thread_id' => 'root-same',
            'subject' => '',
            'body' => 'reply agent context repeated question',
        ], 5);

        $postIds = array_map(static fn (array $match): string => (string) $match['post_id'], $matches);

        assertSame(false, in_array('reply-target', $postIds, true));
        assertSame(false, in_array('root-same', $postIds, true));
        assertSame(false, in_array('reply-same', $postIds, true));
        assertSame(true, in_array('root-related', $postIds, true));
    }

    public function testFindRelatedContentReturnsEmptyForNoUsefulTokensOrMatches(): void
    {
        $pdo = $this->pdoWithContent();
        $service = new RelatedContentSearchService($pdo);

        assertSame([], $service->findRelatedContent([
            'post_id' => 'new-post',
            'thread_id' => 'new-post',
            'subject' => 'What is this?',
            'body' => 'That and this with what',
        ]));

        assertSame([], $service->findRelatedContent([
            'post_id' => 'new-post',
            'thread_id' => 'new-post',
            'subject' => 'Unmatched zygomorphic phrasing',
            'body' => 'No overlap should exist here.',
        ]));
    }

    private function pdoWithContent(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec(
            'CREATE TABLE posts (
                post_id TEXT PRIMARY KEY,
                created_at TEXT NOT NULL,
                thread_id TEXT NOT NULL,
                parent_id TEXT NULL,
                subject TEXT NULL,
                body TEXT NOT NULL,
                board_tags_json TEXT NOT NULL,
                thread_type TEXT NULL,
                author_identity_id TEXT NULL,
                author_profile_slug TEXT NULL,
                author_label TEXT NOT NULL DEFAULT \'guest\',
                sequence_number INTEGER NOT NULL
            )'
        );
        $pdo->exec(
            'CREATE TABLE threads (
                root_post_id TEXT PRIMARY KEY,
                root_post_created_at TEXT NOT NULL,
                last_activity_at TEXT NOT NULL,
                subject TEXT NULL,
                body_preview TEXT NOT NULL,
                reply_count INTEGER NOT NULL,
                last_post_id TEXT NOT NULL,
                board_tags_json TEXT NOT NULL,
                thread_labels_json TEXT NOT NULL,
                score_total INTEGER NOT NULL DEFAULT 0
            )'
        );

        $this->insertPost($pdo, 'root-related', 'root-related', null, 'Reply agent context', 'The reply agent should use prior context before answering repeated questions.', 1);
        $this->insertThread($pdo, 'root-related', 'Reply agent context');
        $this->insertPost($pdo, 'reply-related', 'root-related', 'root-related', null, 'A comment about context windows and useful prior answers.', 2);
        $this->insertPost($pdo, 'root-same', 'root-same', null, 'Same thread context', 'Same thread material is handled by thread comments.', 3);
        $this->insertThread($pdo, 'root-same', 'Same thread context');
        $this->insertPost($pdo, 'reply-target', 'root-same', 'root-same', null, 'The target asks about reply agent context.', 4);
        $this->insertPost($pdo, 'reply-same', 'root-same', 'reply-target', null, 'Same thread reply agent context should not appear here.', 5);
        $this->insertPost($pdo, 'root-unrelated', 'root-unrelated', null, 'Garden planning', 'Tomatoes need stakes and water.', 6);
        $this->insertThread($pdo, 'root-unrelated', 'Garden planning');

        return $pdo;
    }

    private function insertPost(PDO $pdo, string $postId, string $threadId, ?string $parentId, ?string $subject, string $body, int $sequenceNumber): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO posts (post_id, created_at, thread_id, parent_id, subject, body, board_tags_json, thread_type, author_label, sequence_number)
             VALUES (:post_id, :created_at, :thread_id, :parent_id, :subject, :body, :board_tags_json, :thread_type, :author_label, :sequence_number)'
        );
        $stmt->execute([
            'post_id' => $postId,
            'created_at' => '2026-05-12T12:00:00+00:00',
            'thread_id' => $threadId,
            'parent_id' => $parentId,
            'subject' => $subject,
            'body' => $body,
            'board_tags_json' => '[]',
            'thread_type' => null,
            'author_label' => 'guest',
            'sequence_number' => $sequenceNumber,
        ]);
    }

    private function insertThread(PDO $pdo, string $threadId, string $subject): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO threads (root_post_id, root_post_created_at, last_activity_at, subject, body_preview, reply_count, last_post_id, board_tags_json, thread_labels_json, score_total)
             VALUES (:root_post_id, :root_post_created_at, :last_activity_at, :subject, :body_preview, :reply_count, :last_post_id, :board_tags_json, :thread_labels_json, :score_total)'
        );
        $stmt->execute([
            'root_post_id' => $threadId,
            'root_post_created_at' => '2026-05-12T12:00:00+00:00',
            'last_activity_at' => '2026-05-12T12:00:00+00:00',
            'subject' => $subject,
            'body_preview' => '',
            'reply_count' => 0,
            'last_post_id' => $threadId,
            'board_tags_json' => '[]',
            'thread_labels_json' => '[]',
            'score_total' => 0,
        ]);
    }
}
