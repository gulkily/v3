<?php

declare(strict_types=1);

namespace ForumRewrite\Analysis;

use PDO;

final class RelatedContentSearchService
{
    private const MIN_TOKEN_LENGTH = 4;
    private const MAX_QUERY_TOKENS = 12;
    private const EXCERPT_LENGTH = 280;

    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    /**
     * @param array<string, mixed> $targetPost
     * @return list<array<string, mixed>>
     */
    public function findRelatedContent(array $targetPost, int $limit = 5): array
    {
        $limit = max(0, $limit);
        if ($limit === 0) {
            return [];
        }

        $tokens = $this->queryTokens($targetPost);
        if ($tokens === []) {
            return [];
        }

        $stmt = $this->pdo->query(
            'SELECT p.post_id, p.thread_id, p.parent_id, p.subject, p.body, p.author_label, p.created_at,
                    t.subject AS thread_subject
             FROM posts p
             LEFT JOIN threads t ON t.root_post_id = p.thread_id
             ORDER BY p.sequence_number ASC'
        );
        if ($stmt === false) {
            return [];
        }

        $targetPostId = (string) ($targetPost['post_id'] ?? '');
        $targetThreadId = (string) ($targetPost['thread_id'] ?? '');
        $matches = [];

        foreach ($stmt->fetchAll() as $row) {
            $postId = (string) ($row['post_id'] ?? '');
            $threadId = (string) ($row['thread_id'] ?? '');
            if ($postId === '' || $postId === $targetPostId || ($targetThreadId !== '' && $threadId === $targetThreadId)) {
                continue;
            }

            $score = $this->scoreRow($row, $tokens);
            if ($score <= 0) {
                continue;
            }

            $subject = (string) ($row['subject'] ?? '');
            $threadSubject = (string) ($row['thread_subject'] ?? '');
            $matches[] = [
                'score' => $score,
                'post_id' => $postId,
                'thread_id' => $threadId,
                'parent_id' => isset($row['parent_id']) ? (string) $row['parent_id'] : null,
                'post_url' => '/posts/' . rawurlencode($postId),
                'thread_url' => '/threads/' . rawurlencode($threadId),
                'subject' => $subject !== '' ? $subject : $threadSubject,
                'author_label' => (string) ($row['author_label'] ?? 'guest'),
                'created_at' => (string) ($row['created_at'] ?? ''),
                'excerpt' => $this->excerpt((string) ($row['body'] ?? '')),
            ];
        }

        usort($matches, static function (array $left, array $right): int {
            if ($left['score'] !== $right['score']) {
                return $right['score'] <=> $left['score'];
            }

            return strcmp((string) $left['post_id'], (string) $right['post_id']);
        });

        return array_slice($matches, 0, $limit);
    }

    /**
     * @param array<string, mixed> $targetPost
     * @return list<string>
     */
    private function queryTokens(array $targetPost): array
    {
        $text = (string) ($targetPost['subject'] ?? '') . ' ' . (string) ($targetPost['body'] ?? '');
        preg_match_all('/[a-z0-9]+/i', strtolower($text), $matches);

        $tokens = [];
        foreach ($matches[0] ?? [] as $token) {
            if (strlen($token) < self::MIN_TOKEN_LENGTH || $this->isStopWord($token)) {
                continue;
            }

            $tokens[$token] = ($tokens[$token] ?? 0) + 1;
        }

        arsort($tokens);

        return array_slice(array_keys($tokens), 0, self::MAX_QUERY_TOKENS);
    }

    /**
     * @param array<string, mixed> $row
     * @param list<string> $tokens
     */
    private function scoreRow(array $row, array $tokens): int
    {
        $subject = strtolower((string) ($row['subject'] ?? '') . ' ' . (string) ($row['thread_subject'] ?? ''));
        $body = strtolower((string) ($row['body'] ?? ''));
        $score = 0;

        foreach ($tokens as $token) {
            if ($subject !== '' && str_contains($subject, $token)) {
                $score += 4;
            }
            if ($body !== '' && str_contains($body, $token)) {
                $score += 1;
            }
        }

        return $score;
    }

    private function excerpt(string $body): string
    {
        $body = trim(preg_replace('/\s+/', ' ', $body) ?? $body);
        if (strlen($body) <= self::EXCERPT_LENGTH) {
            return $body;
        }

        return substr($body, 0, self::EXCERPT_LENGTH - 12) . ' [truncated]';
    }

    private function isStopWord(string $token): bool
    {
        static $stopWords = [
            'about' => true,
            'after' => true,
            'already' => true,
            'another' => true,
            'because' => true,
            'before' => true,
            'being' => true,
            'between' => true,
            'could' => true,
            'doing' => true,
            'elsewhere' => true,
            'from' => true,
            'have' => true,
            'here' => true,
            'into' => true,
            'more' => true,
            'question' => true,
            'should' => true,
            'that' => true,
            'their' => true,
            'there' => true,
            'this' => true,
            'thread' => true,
            'what' => true,
            'when' => true,
            'where' => true,
            'with' => true,
            'would' => true,
        ];

        return isset($stopWords[$token]);
    }
}
