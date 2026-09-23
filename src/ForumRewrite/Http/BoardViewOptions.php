<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

/**
 * Pure view/sort toggle option builders shared by the board and the tags
 * pages (both show the same "All/Liked" and "Newest/Oldest/Top" controls).
 * See docs/plans/codebase_cleanup_audit_findings_v1.md, Phase 2 slice 3.
 */
final class BoardViewOptions
{
    public static function normalizeView(string $view): string
    {
        return in_array($view, ['all', 'liked'], true) ? $view : 'all';
    }

    public static function normalizeSort(string $sort): string
    {
        return in_array($sort, ['newest', 'oldest', 'top'], true) ? $sort : 'newest';
    }

    /**
     * @return array<int, array{label:string,href:string,is_active:bool,key:string}>
     */
    public static function viewOptions(string $activeView, string $activeSort): array
    {
        return [
            [
                'key' => 'all',
                'label' => 'All',
                'href' => '/threads/?view=all&sort=' . rawurlencode($activeSort),
                'is_active' => $activeView === 'all',
            ],
            [
                'key' => 'liked',
                'label' => 'Liked',
                'href' => '/threads/?view=liked&sort=' . rawurlencode($activeSort),
                'is_active' => $activeView === 'liked',
            ],
        ];
    }

    /**
     * @return array<int, array{label:string,href:string,is_active:bool,key:string}>
     */
    public static function sortOptions(string $activeView, string $activeSort): array
    {
        return [
            [
                'key' => 'newest',
                'label' => 'Newest',
                'href' => '/threads/?view=' . rawurlencode($activeView) . '&sort=newest',
                'is_active' => $activeSort === 'newest',
            ],
            [
                'key' => 'oldest',
                'label' => 'Oldest',
                'href' => '/threads/?view=' . rawurlencode($activeView) . '&sort=oldest',
                'is_active' => $activeSort === 'oldest',
            ],
            [
                'key' => 'top',
                'label' => 'Top',
                'href' => '/threads/?view=' . rawurlencode($activeView) . '&sort=top',
                'is_active' => $activeSort === 'top',
            ],
        ];
    }

    /**
     * @param array<int, array{label:string,href:string,is_active:bool,key:string}> $options
     */
    public static function activeLabel(array $options, string $activeKey): string
    {
        foreach ($options as $option) {
            if ($option['key'] === $activeKey) {
                return $option['label'];
            }
        }

        return $activeKey;
    }
}
