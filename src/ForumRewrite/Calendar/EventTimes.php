<?php

declare(strict_types=1);

namespace ForumRewrite\Calendar;

use ForumRewrite\Security\RetractionAuthority;
use ForumRewrite\Support\EventTimeDirective;
use PDO;

/** Resolve the latest verified event-time reply from the verified root author or a current operator. */
final class EventTimes
{
    /** @param list<string> $threadIds
     * @return array<string, array{event_time:string, event_end_time:?string}>
     */
    public static function forThreads(PDO $pdo, string $repositoryRoot, array $threadIds): array
    {
        $times = [];
        $operators = [];
        foreach (array_chunk($threadIds, 400) as $chunk) {
            $placeholders = implode(', ', array_fill(0, count($chunk), '?'));
            $stmt = $pdo->prepare(
                "SELECT reply.thread_id, reply.body, reply.author_identity_id,
                        CASE WHEN root.attribution_verified = 1 THEN root.author_identity_id END AS root_author
                 FROM posts AS reply
                 JOIN posts AS root ON root.post_id = reply.thread_id
                 WHERE reply.thread_id IN ({$placeholders})
                   AND reply.parent_id IS NOT NULL
                   AND reply.retracted_at IS NULL
                   AND reply.is_hidden = 0
                   AND reply.attribution_verified = 1
                   AND reply.author_identity_id IS NOT NULL
                   AND reply.body LIKE '%event-time:%'
                 ORDER BY reply.created_at ASC, reply.sequence_number ASC"
            );
            $stmt->execute($chunk);
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                $correction = EventTimeDirective::extract((string) $row['body']);
                if ($correction === null) {
                    continue;
                }
                $author = (string) $row['author_identity_id'];
                if ($author === $row['root_author'] || ($operators[$author] ??= RetractionAuthority::currentOperator($repositoryRoot, $author))) {
                    $times[(string) $row['thread_id']] = $correction;
                }
            }
        }

        return $times;
    }

    /** @param list<array<string, mixed>> $events
     * @return list<array<string, mixed>>
     */
    public static function apply(PDO $pdo, string $repositoryRoot, array $events): array
    {
        $times = self::forThreads($pdo, $repositoryRoot, array_column($events, 'root_post_id'));
        return array_map(static fn (array $event): array => ($times[$event['root_post_id']] ?? []) + $event, $events);
    }

    /** @param array<string, mixed> $event */
    public static function display(array $event): string
    {
        $start = (string) ($event['event_time'] ?? '');
        $end = (string) ($event['event_end_time'] ?? '');

        return $start !== '' && $end !== '' ? $start . '–' . $end : $start;
    }
}
