<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use PDO;

/**
 * Shared prompts for /agent/. A prompt is a listed thread tagged #prompt
 * (author's tag or a label): the title names it and the root body is the
 * prompt. It is approved when its author is an approved member (signed post)
 * or an approved member has labeled the thread #prompt; the rest wait.
 * An approved member's #notprompt label takes a thread out of both lists:
 * a hashtag in passing, or a post made through the prefilled composer, can
 * tag a thread that is not a prompt.
 */
final class PromptLibrary
{
    public const TAG = 'prompt';
    public const NOT_TAG = 'notprompt';
    public const LIMIT = 200;

    /**
     * @return array{approved:list<array<string,mixed>>,waiting:list<array<string,mixed>>}
     */
    public static function load(PDO $pdo, string $sort = 'top'): array
    {
        $board = ThreadKeysetPage::listJson('threads.board_tags_json');
        $labels = ThreadKeysetPage::listJson('threads.thread_labels_json');
        $authorIsApproved = '(SELECT CASE WHEN profiles.retired_at IS NOT NULL THEN profiles.was_approved ELSE profiles.is_approved END FROM profiles WHERE profiles.identity_id = posts.author_identity_id)';
        $authorApproved = 'posts.attribution_counted = 1 AND COALESCE(' . $authorIsApproved . ', 0) = 1';
        // viewer_tags keeps every parsed label record, retracted ones too; the
        // tag test above it keeps a fully retracted #prompt label from counting.
        $labelApproved = "EXISTS (SELECT 1 FROM viewer_tags JOIN profiles AS labelers ON labelers.identity_id = viewer_tags.author_identity_id
                WHERE viewer_tags.record_family = 'thread-labels' AND viewer_tags.target_id = threads.root_post_id
                  AND viewer_tags.tag = :tag AND labelers.is_approved = 1 AND labelers.retired_at IS NULL)";
        $order = $sort === 'new'
            ? 'threads.root_post_created_at DESC, threads.root_post_id DESC'
            : 'threads.score_total DESC, threads.root_post_created_at DESC, threads.root_post_id DESC';

        $sql = 'SELECT threads.root_post_id, threads.root_post_created_at, threads.last_activity_at, threads.subject, threads.body_preview,
                    threads.reply_count, threads.score_total, threads.board_tags_json, threads.thread_labels_json, threads.retracted_at,
                    posts.body, posts.body_format, posts.author_label, posts.ported_from, posts.author_profile_slug, posts.attribution_counted,
                    (SELECT profiles.username_token FROM profiles WHERE profiles.identity_id = posts.author_identity_id) AS author_username_token,
                    COALESCE(' . $authorIsApproved . ', 0) AS author_is_approved,
                    CASE WHEN (' . $authorApproved . ') OR ' . $labelApproved . ' THEN 1 ELSE 0 END AS prompt_approved'
            . ThreadKeysetPage::listedRootsSql()
            . " AND (EXISTS (SELECT 1 FROM json_each($board) WHERE type = 'text' AND lower(value) = :tag)
                  OR EXISTS (SELECT 1 FROM json_each($labels) WHERE type = 'text' AND lower(value) = :tag))
              AND NOT (EXISTS (SELECT 1 FROM json_each($labels) WHERE type = 'text' AND lower(value) = :not_tag)
                  AND EXISTS (SELECT 1 FROM viewer_tags JOIN profiles AS removers ON removers.identity_id = viewer_tags.author_identity_id
                      WHERE viewer_tags.record_family = 'thread-labels' AND viewer_tags.target_id = threads.root_post_id
                        AND viewer_tags.tag = :not_tag AND removers.is_approved = 1 AND removers.retired_at IS NULL))
            ORDER BY prompt_approved DESC, $order
            LIMIT " . (self::LIMIT * 2);

        $statement = $pdo->prepare($sql);
        $statement->execute(['tag' => self::TAG, 'not_tag' => self::NOT_TAG]);
        $approved = [];
        $waiting = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $row['prompt_tags'] = self::categories($row);
            if ((int) $row['prompt_approved'] === 1) {
                if (count($approved) < self::LIMIT) $approved[] = $row;
            } elseif (count($waiting) < self::LIMIT) {
                $waiting[] = $row;
            }
        }

        return ['approved' => $approved, 'waiting' => $waiting];
    }

    /**
     * The thread's tags other than #prompt and the board's default, as
     * categories: lower-case, unique, sorted.
     *
     * @param array<string, mixed> $row
     * @return list<string>
     */
    public static function categories(array $row): array
    {
        $tags = [];
        foreach (['board_tags_json', 'thread_labels_json'] as $column) {
            $decoded = json_decode((string) ($row[$column] ?? '[]'), true);
            if (!is_array($decoded)) continue;
            foreach ($decoded as $tag) {
                if (!is_string($tag)) continue;
                $tag = strtolower(trim($tag));
                if ($tag === '' || in_array($tag, [self::TAG, self::NOT_TAG, 'general', 'like', 'upvote', 'downvote', 'flag'], true)) continue;
                $tags[$tag] = true;
            }
        }
        $tags = array_map(strval(...), array_keys($tags));
        sort($tags);

        return $tags;
    }
}
