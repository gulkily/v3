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
        // 'leetness' is a qdb-instance-only sort (routed directly, never
        // surfaced in sortOptions()'s pills) - allowed through here so
        // BoardPageController::board() doesn't coerce it back to 'newest'.
        return in_array($sort, ['newest', 'oldest', 'top', 'leetness'], true) ? $sort : 'newest';
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

    /**
     * Classic qdb.us-style numbered pagination: page 1 and the last page
     * always shown, a window of nearby pages around the current one (two
     * before, four after - matches the shape of the archived qdb.us "..."
     * truncation), with "<Prev"/"Next>" at the ends. Page 1's href is the
     * bare base path (e.g. "/latest"), every other page appends "/<n>".
     *
     * @return list<array{type:string, href?:string, label?:string, is_active?:bool}>
     */
    public static function pagination(string $basePath, int $currentPage, int $totalPages): array
    {
        if ($totalPages <= 1) {
            return [];
        }

        $href = static fn (int $page): string => $page <= 1 ? $basePath : $basePath . '/' . $page;
        $pageItem = static fn (int $page): array => [
            'type' => 'page',
            'href' => $href($page),
            'label' => (string) $page,
            'is_active' => $page === $currentPage,
        ];

        $items = [];

        if ($currentPage > 1) {
            $items[] = ['type' => 'prev', 'href' => $href($currentPage - 1), 'label' => '<Prev'];
        }

        $items[] = $pageItem(1);

        $windowStart = max(2, $currentPage - 2);
        $windowEnd = min($totalPages - 1, $currentPage + 4);

        if ($windowStart > 2) {
            $items[] = ['type' => 'ellipsis'];
        }

        for ($page = $windowStart; $page <= $windowEnd; $page++) {
            $items[] = $pageItem($page);
        }

        if ($windowEnd < $totalPages - 1) {
            $items[] = ['type' => 'ellipsis'];
        }

        $items[] = $pageItem($totalPages);

        if ($currentPage < $totalPages) {
            $items[] = ['type' => 'next', 'href' => $href($currentPage + 1), 'label' => 'Next>'];
        }

        return $items;
    }
}
