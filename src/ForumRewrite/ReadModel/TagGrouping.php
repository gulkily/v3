<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

/**
 * Pure array transforms for grouping already-fetched thread rows
 * (ThreadRepository::fetchThreads()) by board tag / thread label. No PDO,
 * no Application state. Used by the tags index, per-tag pages, and Forte's
 * board view. See docs/plans/codebase_cleanup_audit_findings_v1.md,
 * Phase 2 slice 3.
 */
final class TagGrouping
{
    /**
     * Count all tags while retaining only their first previews. Input must
     * contain one row per root, in display order (as iterateThreads yields).
     *
     * @param iterable<array<string, mixed>> $threads
     * @return array<int, array<string, mixed>>
     */
    public static function previewsFromUniqueThreads(iterable $threads, int $limit): array
    {
        $groups = [];
        foreach ($threads as $thread) {
            $tags = [];
            foreach (['board_tags', 'thread_labels'] as $field) {
                foreach ($thread[$field] ?? [] as $tag) {
                    if (is_string($tag) && $tag !== '') {
                        $tags[strtolower($tag)] = true;
                    }
                }
            }
            foreach ($tags as $tag => $_) {
                $tag = (string) $tag;
                $groups[$tag] ??= [
                    'tag' => $tag,
                    'count' => 0,
                    'preview_threads' => [],
                    'href' => '/tags/' . $tag,
                    'has_more' => false,
                ];
                $groups[$tag]['count']++;
                if (count($groups[$tag]['preview_threads']) < $limit) {
                    $preview = $thread;
                    // The tags index uses body_preview for titles, never the full body.
                    unset($preview['root_post_body']);
                    $groups[$tag]['preview_threads'][] = $preview;
                }
                $groups[$tag]['has_more'] = $groups[$tag]['count'] > $limit;
            }
        }
        uasort($groups, static fn (array $left, array $right): int =>
            ($right['count'] <=> $left['count']) ?: ($left['tag'] <=> $right['tag'])
        );
        return array_values($groups);
    }

    /**
     * @param array<int, array<string, mixed>> $threads
     * @return array<int, array{tag:string,count:int,threads:array<int, array<string, mixed>>}>
     */
    public static function byTag(array $threads): array
    {
        $groups = [];

        foreach ($threads as $thread) {
            $tags = [];
            foreach (['board_tags', 'thread_labels'] as $field) {
                $values = $thread[$field] ?? [];
                if (!is_array($values)) {
                    continue;
                }

                foreach ($values as $value) {
                    if (is_string($value) && $value !== '' && !in_array($value, $tags, true)) {
                        $tags[] = $value;
                    }
                }
            }

            foreach ($tags as $tag) {
                if (!is_string($tag) || $tag === '') {
                    continue;
                }

                $tag = strtolower($tag);
                $groups[$tag] ??= [
                    'tag' => $tag,
                    'count' => 0,
                    'threads' => [],
                ];
                if (!in_array($thread['root_post_id'], array_column($groups[$tag]['threads'], 'root_post_id'), true)) {
                    $groups[$tag]['count']++;
                    $groups[$tag]['threads'][] = $thread;
                }
            }
        }

        uasort($groups, static function (array $left, array $right): int {
            if ($left['count'] !== $right['count']) {
                return $right['count'] <=> $left['count'];
            }

            return $left['tag'] <=> $right['tag'];
        });

        return array_values($groups);
    }

    /**
     * @param array<int, array{tag:string,count:int,threads:array<int, array<string, mixed>>}> $groups
     * @return array<int, array{tag:string,count:int,threads:array<int, array<string, mixed>>,preview_threads:array<int, array<string, mixed>>,href:string,has_more:bool}>
     */
    public static function limitPreview(array $groups, int $limit): array
    {
        $limited = [];

        foreach ($groups as $group) {
            $threads = $group['threads'];
            $group['preview_threads'] = array_slice($threads, 0, $limit);
            $group['has_more'] = count($threads) > $limit;
            $group['href'] = '/tags/' . $group['tag'];
            $limited[] = $group;
        }

        return $limited;
    }

    /**
     * @param array<int, array{tag:string,count:int,threads:array<int, array<string, mixed>>}> $groups
     * @return array{tag:string,count:int,threads:array<int, array<string, mixed>>}|null
     */
    public static function find(array $groups, string $tag): ?array
    {
        $tag = strtolower($tag);
        foreach ($groups as $group) {
            if ($group['tag'] === $tag) {
                return $group;
            }
        }

        return null;
    }
}
