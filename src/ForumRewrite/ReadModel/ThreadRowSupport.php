<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

/**
 * Pure helpers for hydrating and filtering thread/post rows fetched from the
 * read model - no PDO, no session, no Application state. Used by the board
 * query, the activity feed, profile pages, and agent-reply flows alike, so
 * it's foundational enough to be worth its own home rather than living on
 * Application. See docs/plans/codebase_cleanup_audit_findings_v1.md, Phase 2
 * slice 3.
 */
final class ThreadRowSupport
{
    public const HIDDEN_BOOTSTRAP_TAG = 'identity';

    /**
     * @return list<string>
     */
    public static function decodeStringList(string $json): array
    {
        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            return [];
        }

        $values = [];
        foreach ($decoded as $value) {
            if (is_string($value)) {
                $values[] = $value;
            }
        }

        return $values;
    }

    public static function isHiddenBootstrapBoardTagsJson(string $boardTagsJson): bool
    {
        $boardTags = json_decode($boardTagsJson, true);
        if (!is_array($boardTags)) {
            return false;
        }

        return in_array(self::HIDDEN_BOOTSTRAP_TAG, $boardTags, true);
    }

    /**
     * @param array<string, mixed> $thread
     * @return array<string, mixed>
     */
    public static function hydrateThreadRow(array $thread): array
    {
        $thread['score_total'] = (int) ($thread['score_total'] ?? 0);
        $thread['root_post_score_total'] = (int) ($thread['root_post_score_total'] ?? 0);
        $thread['board_tags'] = self::decodeStringList((string) ($thread['board_tags_json'] ?? '[]'));
        $thread['thread_labels'] = self::decodeStringList((string) ($thread['thread_labels_json'] ?? '[]'));

        return $thread;
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<int, array<string, mixed>>
     */
    public static function hydrateThreadRows(array $rows): array
    {
        return array_map(static fn (array $thread): array => self::hydrateThreadRow($thread), $rows);
    }
}
