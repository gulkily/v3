<?php

declare(strict_types=1);

namespace ForumRewrite\Calendar;

use ForumRewrite\ReadModel\ThreadKeysetPage;
use PDO;

/**
 * Listed event threads whose span touches a date range, optionally limited to one tag.
 * A tag matches a board tag or a label, as /tags/<tag> counts them. Rows carry the
 * board row fields the title and author helpers read.
 *
 * Only approved members put events on the calendar: the thread's author must be approved
 * (approved when retired, for retired keys) and the post must count toward that author, so
 * an unverified post naming an approved identity stays off. Other events still show on
 * their thread. Checked here at read time, so it covers every write and sync path and
 * follows approval as it changes.
 */
final class CalendarQuery
{
    private const AUTHOR_IS_APPROVED_SQL = 'COALESCE((SELECT CASE WHEN profiles.retired_at IS NOT NULL THEN profiles.was_approved ELSE profiles.is_approved END
                        FROM profiles WHERE profiles.identity_id = posts.author_identity_id), 0)';

    /** @return list<array<string, mixed>> ordered by first day, then creation */
    public static function eventsBetween(PDO $pdo, string $from, string $to, ?string $tag = null): array
    {
        $sql = 'SELECT threads.root_post_id, threads.root_post_created_at, threads.subject, threads.body_preview, threads.retracted_at,
                    threads.event_date, threads.event_end_date, threads.event_time, threads.event_location,
                    posts.author_label, posts.author_profile_slug, posts.attribution_counted, posts.body_format,
                    (SELECT profiles.username_token FROM profiles WHERE profiles.identity_id = posts.author_identity_id) AS author_username_token,
                    ' . self::AUTHOR_IS_APPROVED_SQL . ' AS author_is_approved'
            . ThreadKeysetPage::listedRootsSql()
            . ' AND posts.attribution_counted = 1
                AND ' . self::AUTHOR_IS_APPROVED_SQL . ' = 1
                AND threads.event_date IS NOT NULL
                AND threads.event_date <= :to
                AND COALESCE(threads.event_end_date, threads.event_date) >= :from';
        $parameters = ['from' => $from, 'to' => $to];
        if ($tag !== null) {
            $board = ThreadKeysetPage::listJson('threads.board_tags_json');
            $labels = ThreadKeysetPage::listJson('threads.thread_labels_json');
            $sql .= " AND (EXISTS (SELECT 1 FROM json_each($board) WHERE type = 'text' AND lower(value) = :tag)
                OR EXISTS (SELECT 1 FROM json_each($labels) WHERE type = 'text' AND lower(value) = :tag))";
            $parameters['tag'] = strtolower($tag);
        }
        $sql .= ' ORDER BY threads.event_date ASC, threads.root_post_created_at ASC, threads.root_post_id ASC';

        $stmt = $pdo->prepare($sql);
        $stmt->execute($parameters);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
