<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

use ForumRewrite\ReadModel\ProfileRepository;
use PDO;

/**
 * Eleventh Phase 2 slice (see docs/plans/codebase_cleanup_audit_plan_v1.md
 * and docs/plans/codebase_cleanup_audit_findings_v1.md): /forte/users/.
 * Every dependency (activity-bounds query, category flags/counts, pending
 * users, sort resolution) was confirmed single-caller - only reachable
 * through this one route - before moving here wholesale.
 */
final class ForteUserDirectoryController
{
    public function __construct(
        private readonly RouteServices $routeServices,
    ) {
    }

    public function directory(
        string $requestedView = '',
        string $requestedSelected = '',
        string $requestedSortColumn = '',
        string $requestedSortDir = '',
    ): string {
        $pdo = $this->routeServices->pdo();
        $users = ProfileRepository::approvedDirectoryUsers($pdo);
        $activityBoundsByToken = $this->fetchActivityBoundsByToken($pdo);
        foreach ($users as &$user) {
            $bounds = $activityBoundsByToken[$user['username_token']] ?? ['earliest' => '', 'latest' => ''];
            $user['active_at'] = $bounds['latest'];
            $user['joined_at'] = $bounds['earliest'];
        }
        unset($user);

        $flagsByToken = $this->buildCategoryFlags($users);
        $pendingUsers = $this->fetchNeverApprovedPendingUsers($pdo);
        // Pending users have no per-token activity-bounds query (a separate,
        // secondary population - see fetchNeverApprovedPendingUsers()'s own
        // doc comment) - the Active/Joined columns render blank for these
        // rows rather than adding a second bounds query for them.
        foreach ($pendingUsers as &$pendingUser) {
            $pendingUser['active_at'] = '';
            $pendingUser['joined_at'] = '';
        }
        unset($pendingUser);

        $categoryCounts = $this->buildCategoryCounts(count($users), $flagsByToken, count($pendingUsers));

        $sort = $this->resolveSort($requestedSortColumn, $requestedSortDir);
        $users = $this->applySort($users, $sort['column'], $sort['dir']);
        $pendingUsers = $this->applySort($pendingUsers, $sort['column'], $sort['dir']);

        return $this->routeServices->renderStandalonePage(
            'forte_users.php',
            [
                'users' => $users,
                'flagsByToken' => $flagsByToken,
                'pendingUsers' => $pendingUsers,
                'categoryCounts' => $categoryCounts,
                'selectedCategory' => $this->normalizeCategory($requestedView),
                'selectedUserToken' => strtolower(trim($requestedSelected)),
                'sortColumn' => $sort['column'],
                'sortDir' => $sort['dir'],
            ],
            'Users - Forte',
            'paned-reader-body',
            ['/assets/paned_users_reader.js'],
            ['/assets/forte.css'],
        );
    }

    /**
     * @return array<string, array{earliest: string, latest: string}>
     */
    private function fetchActivityBoundsByToken(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT profiles.username_token,
                    MIN(posts.created_at) AS earliest_activity_at,
                    MAX(posts.created_at) AS latest_activity_at
             FROM posts
             JOIN profiles ON profiles.identity_id = posts.author_identity_id
             WHERE profiles.is_approved = 1 AND posts.is_hidden = 0
             GROUP BY profiles.username_token'
        );

        $boundsByToken = [];
        foreach ($stmt->fetchAll() as $row) {
            $boundsByToken[(string) $row['username_token']] = [
                'earliest' => (string) $row['earliest_activity_at'],
                'latest' => (string) $row['latest_activity_at'],
            ];
        }

        return $boundsByToken;
    }

    /**
     * Per-user semantic filter flags for the Users pane's category filter:
     * "established" needs at least one thread AND at least one reply, since
     * post_count already includes every reply plus every thread's root
     * post - a thread with no replies from anyone else still leaves
     * post_count - thread_count at 0. A user can carry multiple flags at
     * once (e.g. no threads yet but active this week), so these are
     * independent membership flags, not a mutually-exclusive partition.
     *
     * @param array<int, array<string, mixed>> $users
     * @return array<string, array{new: bool, established: bool, no_threads: bool, recently_active: bool}>
     */
    private function buildCategoryFlags(array $users): array
    {
        $recentActivityThreshold = (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify('-7 days')
            ->format('Y-m-d\TH:i:s\Z');

        $flagsByToken = [];
        foreach ($users as $user) {
            $token = (string) $user['username_token'];
            $threadCount = (int) $user['thread_count'];
            $replyCount = ((int) $user['post_count']) - $threadCount;
            $established = $threadCount >= 1 && $replyCount >= 1;
            $activeAt = (string) ($user['active_at'] ?? '');

            $flagsByToken[$token] = [
                'new' => !$established,
                'established' => $established,
                'no_threads' => $threadCount === 0,
                'recently_active' => $activeAt !== '' && $activeAt >= $recentActivityThreshold,
            ];
        }

        return $flagsByToken;
    }

    /**
     * Fixed-category counts for the Users pane's filter list. Category keys
     * are hyphenated for the URL/DOM (no-threads, recently-active,
     * not-approved) even though buildCategoryFlags()'s internal flag keys
     * use underscores - only two need translating.
     *
     * @param array<string, array{new: bool, established: bool, no_threads: bool, recently_active: bool}> $flagsByToken
     * @return list<array{key: string, label: string, count: int}>
     */
    private function buildCategoryCounts(int $totalApprovedCount, array $flagsByToken, int $pendingCount): array
    {
        $counts = ['new' => 0, 'established' => 0, 'no_threads' => 0, 'recently_active' => 0];
        foreach ($flagsByToken as $flags) {
            foreach ($flags as $key => $value) {
                if ($value) {
                    $counts[$key]++;
                }
            }
        }

        return [
            ['key' => 'all', 'label' => 'All Users', 'count' => $totalApprovedCount],
            ['key' => 'new', 'label' => 'New', 'count' => $counts['new']],
            ['key' => 'established', 'label' => 'Established', 'count' => $counts['established']],
            ['key' => 'no-threads', 'label' => 'No Threads', 'count' => $counts['no_threads']],
            ['key' => 'recently-active', 'label' => 'Recently Active', 'count' => $counts['recently_active']],
            ['key' => 'not-approved', 'label' => 'Not Approved', 'count' => $pendingCount],
        ];
    }

    /**
     * Pending profiles for the Forte Users pane's "Not Approved" category,
     * rolled up by username_token like ProfileRepository::approvedDirectoryUsers()
     * - but excluding any token that already has an approved profile.
     * Real data has both: a username_token can carry several duplicate
     * pending submissions, and an already-approved user can independently
     * accumulate further pending profiles under their own name. Without the
     * exclusion, an approved, already-listed user would also turn up under
     * Not Approved as if they were a second, different pending user.
     *
     * @return array<int, array<string, mixed>>
     */
    private function fetchNeverApprovedPendingUsers(PDO $pdo): array
    {
        $stmt = $pdo->query(
            'SELECT username_token, MIN(username) AS username,
                    COUNT(*) AS pending_profile_count,
                    SUM(thread_count) AS thread_count,
                    SUM(post_count) AS post_count
             FROM profiles
             WHERE is_approved = 0
               AND username_token NOT IN (SELECT username_token FROM profiles WHERE is_approved = 1)
             GROUP BY username_token
             ORDER BY SUM(thread_count) DESC, SUM(post_count) DESC, username_token ASC'
        );

        return $stmt->fetchAll();
    }

    /**
     * @return array{column: string, dir: string}
     */
    private function resolveSort(string $requestedColumn, string $requestedDir): array
    {
        $validColumns = ['username', 'threads', 'posts', 'active', 'joined'];
        if (!in_array($requestedColumn, $validColumns, true)) {
            return ['column' => '', 'dir' => ''];
        }

        $defaultDir = $requestedColumn === 'username' ? 'asc' : 'desc';
        $dir = in_array($requestedDir, ['asc', 'desc'], true) ? $requestedDir : $defaultDir;

        return ['column' => $requestedColumn, 'dir' => $dir];
    }

    /**
     * @param array<int, array<string, mixed>> $users
     * @return array<int, array<string, mixed>>
     */
    private function applySort(array $users, string $column, string $dir): array
    {
        if ($column === '') {
            return $users;
        }

        $sorted = $users;
        usort($sorted, function (array $left, array $right) use ($column): int {
            return $this->sortValue($left, $column) <=> $this->sortValue($right, $column);
        });

        return $dir === 'desc' ? array_reverse($sorted) : $sorted;
    }

    private function sortValue(array $user, string $column): string|int
    {
        return match ($column) {
            'username' => mb_strtolower((string) ($user['username'] ?? '')),
            'threads' => (int) ($user['thread_count'] ?? 0),
            'posts' => (int) ($user['post_count'] ?? 0),
            'active' => (string) ($user['active_at'] ?? ''),
            'joined' => (string) ($user['joined_at'] ?? ''),
            default => '',
        };
    }

    private function normalizeCategory(string $category): string
    {
        return in_array($category, ['all', 'new', 'established', 'no-threads', 'recently-active', 'not-approved'], true)
            ? $category
            : 'all';
    }
}
