<?php

declare(strict_types=1);

/** Focused public-feed fixtures; independent of profile attribution counts. */
final class RecentPostFixture
{
    public static function seed(PDO $pdo): void
    {
        $insert = $pdo->prepare('INSERT INTO posts (post_id,thread_id,parent_id,created_at,subject,body,board_tags_json,sequence_number,author_label,is_hidden,retracted_at,attribution_counted,agent_response_json) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)');
        foreach (self::rows() as $id => $overrides) {
            $row = $overrides + ['thread' => $id, 'parent' => null, 'date' => '2026-10-30T12:00:00Z', 'subject' => 'Subject ' . $id,
                'body' => 'Body ' . $id, 'tags' => '[]', 'author' => 'guest', 'hidden' => 0, 'retracted' => null, 'counted' => 1, 'agent' => null];
            $insert->execute([$id,$row['thread'],$row['parent'],$row['date'],$row['subject'],$row['body'],$row['tags'],1,$row['author'],$row['hidden'],$row['retracted'],$row['counted'],$row['agent']]);
        }
    }

    public static function rows(): array
    {
        $rows = ['old-thread' => ['date' => '2026-01-01T00:00:00Z'], 'new-thread' => []];
        for ($i = 1; $i <= 5; $i++) $rows['reply-' . $i] = ['thread' => 'old-thread', 'parent' => 'old-thread', 'author' => $i === 1 ? 'reply-agent' : 'guest'];
        return $rows + [
            'unsigned' => ['counted' => 0, 'author' => 'claimed-name'],
            'hidden' => ['hidden' => 1, 'subject' => 'Hidden secret'],
            'removed' => ['retracted' => '2026-10-30T13:00:00Z', 'subject' => 'Removed secret'],
            'bootstrap' => ['tags' => '["identity"]'],
            'expansion' => ['agent' => '{"type":"scripture_expansion"}'],
            'hidden-context' => ['thread' => 'hidden', 'parent' => 'hidden'],
            'missing-context' => ['thread' => 'missing', 'parent' => 'missing'],
            'title-only' => ['body' => ''],
            'image-only' => ['subject' => '', 'body' => 'Image-v1: ' . str_repeat('a',64) . ' png 10 1 1'],
        ];
    }
}
