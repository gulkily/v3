<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

/**
 * An approved member's `#approved` (or `#approve`) label approves that one thread,
 * whoever wrote it, for checks that ask at read time whether a thread's author is
 * approved. It is how a guest's unsigned thread gets the same standing. Like the
 * Prompts library's `#prompt` rule, the label must still be on the thread (a fully
 * retracted label is dropped from thread_labels_json) and an approved, unretired
 * member must have written one (viewer_tags keeps every parsed label record).
 */
final class ApprovalLabel
{
    public const TAGS = ['approved', 'approve'];

    /** SQL condition, true when the thread whose root post ID is $threadColumn carries an approval label. */
    public static function threadSql(string $threadColumn = 'threads.root_post_id', string $labelsColumn = 'threads.thread_labels_json'): string
    {
        $labels = ThreadKeysetPage::listJson($labelsColumn);
        $tags = "('" . implode("','", self::TAGS) . "')";

        return "(EXISTS (SELECT 1 FROM json_each($labels) WHERE type = 'text' AND lower(value) IN $tags)
            AND EXISTS (SELECT 1 FROM viewer_tags JOIN profiles AS approvers ON approvers.identity_id = viewer_tags.author_identity_id
                WHERE viewer_tags.record_family = 'thread-labels' AND viewer_tags.target_id = $threadColumn
                  AND viewer_tags.tag IN $tags AND approvers.is_approved = 1 AND approvers.retired_at IS NULL))";
    }
}
