<?php

declare(strict_types=1);

namespace ForumRewrite\Qdb;

use ForumRewrite\Http\BoardViewOptions;
use ForumRewrite\ReadModel\ViewerTagLookup;

final class QdbBoardPolicy
{
    private const PAGE_SIZE = 25;
    private const PAGINATED_SECTIONS = ['latest', 'top'];

    /**
     * @param \Closure(): (array<string, mixed>|null) $resolveViewerProfile
     */
    public function __construct(
        private readonly string $repositoryRoot,
        private readonly \Closure $resolveViewerProfile,
    ) {
    }

    /**
     * @param array<int, array<string, mixed>> $threads
     * @return array{threads: array<int, array<string, mixed>>, pagination: array<int, array<string, mixed>>|null}
     */
    public function paginate(array $threads, string $activeSection, int $page): array
    {
        if (!in_array($activeSection, self::PAGINATED_SECTIONS, true)) {
            return ['threads' => $threads, 'pagination' => null];
        }

        $totalPages = max(1, (int) ceil(count($threads) / self::PAGE_SIZE));
        $page = max(1, min($page, $totalPages));

        return [
            'threads' => array_slice($threads, ($page - 1) * self::PAGE_SIZE, self::PAGE_SIZE),
            'pagination' => BoardViewOptions::pagination('/' . $activeSection, $page, $totalPages),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $threads
     * @return array{upvoted: array<string, true>, downvoted: array<string, true>, flagged: array<string, true>}
     */
    public function viewerReactionState(array $threads): array
    {
        $empty = ['upvoted' => [], 'downvoted' => [], 'flagged' => []];
        $viewerProfile = ($this->resolveViewerProfile)();
        if ($viewerProfile === null) {
            return $empty;
        }

        $viewerIdentityId = (string) $viewerProfile['identity_id'];
        $rootPostIds = array_column($threads, 'root_post_id');

        return [
            'upvoted' => ViewerTagLookup::threadTags($this->repositoryRoot, $rootPostIds, 'upvote', $viewerIdentityId),
            'downvoted' => ViewerTagLookup::threadTags($this->repositoryRoot, $rootPostIds, 'downvote', $viewerIdentityId),
            'flagged' => ViewerTagLookup::postTags($this->repositoryRoot, $rootPostIds, 'flag', $viewerIdentityId),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $threads
     * @return array<int, array<string, mixed>>
     */
    public function eligibleQuotes(array $threads): array
    {
        return array_values(array_filter(
            $threads,
            static fn (array $thread): bool => QdbQuoteNumbers::fromThreadId((string) ($thread['root_post_id'] ?? '')) !== null,
        ));
    }

    /** @param array<int, array<string, mixed>> $threads */
    public function quoteCount(array $threads): int
    {
        return count($this->eligibleQuotes($threads));
    }

    /**
     * @param array<int, array<string, mixed>> $threads
     * @return array<int, array<string, mixed>>
     */
    public function newsThreads(array $threads): array
    {
        $newsThreads = array_values(array_filter(
            $threads,
            static fn (array $thread): bool => in_array('news', $thread['board_tags'] ?? [], true),
        ));

        usort($newsThreads, static function (array $left, array $right): int {
            $createdCompare = strcmp((string) ($right['root_post_created_at'] ?? ''), (string) ($left['root_post_created_at'] ?? ''));

            return $createdCompare !== 0
                ? $createdCompare
                : strcmp((string) ($right['root_post_id'] ?? ''), (string) ($left['root_post_id'] ?? ''));
        });

        return $newsThreads;
    }

    /**
     * @param array<string, mixed> $left
     * @param array<string, mixed> $right
     */
    public function compareLeetness(array $left, array $right, int $fallback): int
    {
        $comparison = abs(1337 - (int) $left['score_total']) <=> abs(1337 - (int) $right['score_total']);

        return $comparison !== 0 ? $comparison : $fallback;
    }
}
