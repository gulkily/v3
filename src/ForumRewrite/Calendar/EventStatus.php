<?php

declare(strict_types=1);

namespace ForumRewrite\Calendar;

use ForumRewrite\Security\RetractionAuthority;
use ForumRewrite\Support\EventStatusDirective;
use PDO;

/**
 * The state of event threads, derived when read from their posts: the latest
 * `event:` line written by an active operator decides. A line counts only when its
 * post is unretracted, its attribution is verified, and its author is approved now
 * and is a current operator. Nothing is stored, so a rebuild cannot disagree.
 */
final class EventStatus
{
    /**
     * @param list<string> $threadIds
     * @return array<string, string> thread id => confirmed|declined; plain events are absent
     */
    public static function forThreads(PDO $pdo, string $repositoryRoot, array $threadIds): array
    {
        $threadIds = array_values(array_unique(array_filter($threadIds, static fn (string $id): bool => $id !== '')));
        if ($threadIds === []) {
            return [];
        }

        $states = [];
        $operators = [];
        foreach (array_chunk($threadIds, 400) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare(
                "SELECT posts.thread_id, posts.body, posts.author_identity_id
                 FROM posts
                 JOIN profiles ON profiles.identity_id = posts.author_identity_id AND profiles.is_approved = 1
                 WHERE posts.thread_id IN ({$placeholders})
                   AND posts.retracted_at IS NULL
                   AND posts.attribution_verified = 1
                   AND posts.body LIKE '%event:%'
                 ORDER BY posts.created_at ASC, posts.sequence_number ASC"
            );
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $state = EventStatusDirective::extract((string) $row['body']);
                if ($state === null) {
                    continue;
                }
                $author = (string) $row['author_identity_id'];
                $operators[$author] ??= RetractionAuthority::currentOperator($repositoryRoot, $author);
                if ($operators[$author]) {
                    $states[(string) $row['thread_id']] = $state;
                }
            }
        }

        return $states;
    }

    public static function forThread(PDO $pdo, string $repositoryRoot, string $threadId): ?string
    {
        return self::forThreads($pdo, $repositoryRoot, [$threadId])[$threadId] ?? null;
    }
}
