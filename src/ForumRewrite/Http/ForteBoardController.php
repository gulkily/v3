<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\TagGrouping;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\ReadModel\ViewerTagLookup;
use ForumRewrite\Support\ThreadTitle;

/**
 * Tenth Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md and
 * docs/plans/codebase_cleanup_audit_findings_v1.md): the /forte board view
 * itself - the most complex slice so far, but every dependency turned out
 * pure/single-caller once traced (the same lesson as the board slice:
 * "meaningfully more complex" doesn't mean "not extractable", just "needs a
 * closer look before assuming it isn't").
 *
 * viewerCanInspect-style viewer-identity resolution stays on Application
 * (session-bound) and is passed in as a bound closure; everything else -
 * selection/sort resolution, reply-tree building, tag grouping - was
 * confirmed single-caller (only reachable through this controller) or
 * already-shared-and-extracted (ThreadRepository, TagGrouping,
 * ViewerTagLookup) before moving.
 *
 * /forte/activity/, /forte/users/, and the /api/forte_*, /api/get_forte_*
 * AJAX endpoints are separate, still-un-extracted pieces of the /forte
 * route group - left for future slices.
 */
final class ForteBoardController
{
    /**
     * @param \Closure(): (array<string, mixed>|null) $resolveViewerProfile
     */
    public function __construct(
        private readonly RouteServices $routeServices,
        private readonly string $repositoryRoot,
        private readonly \Closure $resolveViewerProfile,
    ) {
    }

    public function board(
        string $requestedTag = '',
        string $requestedSortColumn = '',
        string $requestedSortDir = '',
        string $requestedSelected = '',
        string $requestedCreatedPostId = '',
    ): string {
        $pdo = $this->routeServices->pdo();
        $threads = ThreadRepository::fetchThreads($pdo);
        $tagGroups = TagGrouping::byTag($threads);
        $selection = $this->resolveSelection($threads, $tagGroups, $requestedTag, $requestedSelected);
        $selectedTag = $selection['tag'];
        $selectedThreadId = $selection['selectedThreadId'];
        $sort = $this->resolveSort($requestedSortColumn, $requestedSortDir);
        $threads = $this->applySort($threads, $sort['column'], $sort['dir']);

        // A thread excluded from the board's own listing (identity/
        // bootstrap/approval-only) still resolves when linked to directly -
        // fetched on its own here, kept out of $threads/$tagGroups/counts
        // entirely, and folded only into $contentThreads below so just the
        // content pane (never the row list or folder tree) can render it.
        $extraThread = null;
        if ($selectedThreadId === '' && $requestedSelected !== '') {
            $extraThread = ThreadRepository::byId($pdo, $requestedSelected);
            if ($extraThread !== null) {
                $selectedThreadId = $requestedSelected;
            }
        }
        $contentThreads = $extraThread !== null ? array_merge($threads, [$extraThread]) : $threads;

        $replyPostsByThreadId = ThreadRepository::allReplyPostsByThreadId($pdo);
        $replyTreesByThreadId = [];
        $allPostIds = [];
        $highlightedPostId = '';
        foreach ($contentThreads as $thread) {
            $threadId = (string) $thread['root_post_id'];
            $replyTreesByThreadId[$threadId] = $this->buildReplyTree($replyPostsByThreadId[$threadId] ?? []);
            $allPostIds[] = $threadId;
            foreach ($replyPostsByThreadId[$threadId] ?? [] as $replyPost) {
                $postId = (string) $replyPost['post_id'];
                $allPostIds[] = $postId;
                if ($threadId === $selectedThreadId && $postId === $requestedCreatedPostId) {
                    $highlightedPostId = $postId;
                }
            }
        }

        $viewerProfile = ($this->resolveViewerProfile)();
        $viewerIdentityId = $viewerProfile !== null ? (string) $viewerProfile['identity_id'] : '';
        $viewerLikedThreadIds = $viewerProfile !== null
            ? ViewerTagLookup::threadTags($this->repositoryRoot, array_column($contentThreads, 'root_post_id'), 'like', $viewerIdentityId)
            : [];
        $viewerFlaggedPostIds = $viewerProfile !== null
            ? ViewerTagLookup::postTags($this->repositoryRoot, $allPostIds, 'flag', $viewerIdentityId)
            : [];

        return $this->routeServices->renderStandalonePage(
            'forte_board.php',
            [
                'threads' => $threads,
                'contentThreads' => $contentThreads,
                'tagGroups' => $tagGroups,
                'selectedTag' => $selectedTag,
                'selectedThreadId' => $selectedThreadId,
                'sortColumn' => $sort['column'],
                'sortDir' => $sort['dir'],
                'replyTreesByThreadId' => $replyTreesByThreadId,
                'viewerLikedThreadIds' => $viewerLikedThreadIds,
                'viewerFlaggedPostIds' => $viewerFlaggedPostIds,
                'highlightedPostId' => $highlightedPostId,
            ],
            'Forte',
            'paned-reader-body',
            ['/assets/paned_board_reader.js', '/assets/lazy_compose_signing.js', '/assets/thread_reactions.js'],
            ['/assets/forte.css'],
        );
    }

    /**
     * @param array<int, array<string, mixed>> $threads
     * @param array<int, array{tag: string, count: int, threads: array}> $tagGroups
     * @return array{tag: string, selectedThreadId: string}
     */
    private function resolveSelection(array $threads, array $tagGroups, string $requestedTag, string $requestedSelected): array
    {
        $resolvedTag = $this->resolveTag($requestedTag, $tagGroups);

        $selectedThreadId = '';
        foreach ($threads as $thread) {
            if ((string) $thread['root_post_id'] === $requestedSelected) {
                $selectedThreadId = $requestedSelected;
                break;
            }
        }

        if ($selectedThreadId === '') {
            return ['tag' => $resolvedTag, 'selectedThreadId' => ''];
        }

        if ($resolvedTag !== '') {
            $group = TagGrouping::find($tagGroups, $resolvedTag);
            $threadIdsInGroup = $group !== null ? array_column($group['threads'], 'root_post_id') : [];
            if (!in_array($selectedThreadId, $threadIdsInGroup, true)) {
                $resolvedTag = '';
            }
        }

        return ['tag' => $resolvedTag, 'selectedThreadId' => $selectedThreadId];
    }

    private function resolveTag(string $requestedTag, array $tagGroups): string
    {
        if ($requestedTag === '') {
            return '';
        }

        foreach ($tagGroups as $group) {
            if ($group['tag'] === $requestedTag) {
                return $requestedTag;
            }
        }

        return '';
    }

    /**
     * @return array{column: string, dir: string}
     */
    private function resolveSort(string $requestedColumn, string $requestedDir): array
    {
        $validColumns = ['subject', 'from', 'date', 'replies'];
        if (!in_array($requestedColumn, $validColumns, true)) {
            return ['column' => '', 'dir' => ''];
        }

        $defaultDir = in_array($requestedColumn, ['date', 'replies'], true) ? 'desc' : 'asc';
        $dir = in_array($requestedDir, ['asc', 'desc'], true) ? $requestedDir : $defaultDir;

        return ['column' => $requestedColumn, 'dir' => $dir];
    }

    /**
     * @param array<int, array<string, mixed>> $threads
     * @return array<int, array<string, mixed>>
     */
    private function applySort(array $threads, string $column, string $dir): array
    {
        if ($column === '') {
            return $threads;
        }

        $sorted = $threads;
        usort($sorted, function (array $left, array $right) use ($column): int {
            return $this->sortValue($left, $column) <=> $this->sortValue($right, $column);
        });

        return $dir === 'desc' ? array_reverse($sorted) : $sorted;
    }

    private function sortValue(array $thread, string $column): string|int
    {
        return match ($column) {
            'subject' => mb_strtolower(ThreadTitle::displayTitle(
                (string) ($thread['subject'] ?? ''),
                (string) ($thread['body_preview'] ?? ''),
                (string) $thread['root_post_id'],
            )),
            'from' => mb_strtolower(trim((string) ($thread['author_label'] ?? '')) ?: 'guest'),
            'date' => (string) ($thread['root_post_created_at'] ?? ''),
            'replies' => (int) ($thread['reply_count'] ?? 0),
            default => '',
        };
    }

    /**
     * Nests a flat, sequence_number-ordered post list into a reply tree using
     * each post's parent_id. A post whose parent_id is missing or not present
     * in the fetched set (e.g. hidden/deleted) is treated as a root.
     *
     * @param array<int, array<string, mixed>> $posts
     * @return array<int, array{post: array<string, mixed>, children: array}>
     */
    private function buildReplyTree(array $posts): array
    {
        $nodesByPostId = [];
        foreach ($posts as $post) {
            $nodesByPostId[(string) $post['post_id']] = [
                'post' => $post,
                'children' => [],
            ];
        }

        $roots = [];
        foreach ($nodesByPostId as $postId => &$node) {
            $parentId = $node['post']['parent_id'] !== null ? (string) $node['post']['parent_id'] : null;
            if ($parentId !== null && $parentId !== $postId && isset($nodesByPostId[$parentId])) {
                $nodesByPostId[$parentId]['children'][] = &$node;
            } else {
                $roots[] = &$node;
            }
        }
        unset($node);

        return $roots;
    }
}
