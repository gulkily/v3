<?php

declare(strict_types=1);

namespace ForumRewrite\Activity;

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\SourcePathValidator;
use ForumRewrite\ReadModel\ThreadRowSupport;
use PDO;
use RuntimeException;

/**
 * The activity/commit-manifest data layer, extracted from Application.php
 * (see docs/plans/codebase_cleanup_audit_plan_v1.md and
 * docs/plans/activity_subsystem_extraction_plan_v1.md) - fetchActivity()
 * and its full collaborator graph, moved wholesale rather than in slices
 * since almost every method here calls several siblings in the same
 * cluster. Everything needed beyond pdo()/repositoryRoot/databasePath is
 * either a pure static call (SourcePathValidator, CanonicalPathResolver,
 * ThreadRowSupport) or internal to this class - confirmed via a full
 * dependency-graph investigation before extraction (see the plan doc).
 *
 * Instance-lifetime memoization for the two request-scoped caches
 * (activityCommitManifestCache, sourceCommitFileManifestCache) and the
 * lazily-opened persistent manifest cache store works the same way it did
 * on Application: this object is built once per request (memoized by
 * RouteServices::activityService(), mirroring how RouteServices itself is
 * memoized by Application::routeServices()), so a fresh instance never
 * appears mid-request.
 */
final class ActivityService
{
    private const ACTIVITY_ITEM_LIMIT = 100;

    /** @var array<string, list<array{status:string,path:string,previous_path:string}>|null> */
    private array $sourceCommitFileManifestCache = [];

    /** @var array<string, array<int, array<string, mixed>>|null> */
    private array $activityCommitManifestCache = [];

    private ?SqliteActivityCommitManifestCache $activityCommitManifestCacheStore = null;
    private bool $activityCommitManifestCacheStoreInitialized = false;
    private ?PDO $pdo = null;

    /**
     * @param \Closure(): PDO $pdoFactory Lazy, like RouteServices::pdo() -
     *        methods here that never touch the main read-model (e.g.
     *        activityCommitManifest(), which is git-exec plus a separate
     *        cache file) must not force that connection open just because
     *        this service was constructed.
     */
    public function __construct(
        private readonly \Closure $pdoFactory,
        private readonly string $repositoryRoot,
        private readonly string $databasePath,
    ) {
    }

    private function pdo(): PDO
    {
        return $this->pdo ??= ($this->pdoFactory)();
    }

    /**
     * @param array{sort_value: string, id: int}|null $afterCursor
     *        Keyset cursor identifying the last item of the previous page,
     *        matching the ORDER BY below. Pass null for the first page.
     * @return array{items: array<int, array<string, mixed>>, has_more: bool}
     */
    public function fetchActivity(string $view, string $sortColumn, string $sortDirection, ?array $afterCursor = null): array
    {
        $view = $this->normalizeActivityView($view);
        ['column' => $sortColumn, 'direction' => $sortDirection] = $this->resolveActivitySort($sortColumn, $sortDirection);
        $sortColumnSql = $this->activitySortSql($sortColumn);
        $sortDirectionSql = $sortDirection === 'desc' ? 'DESC' : 'ASC';
        [$viewWhere, $viewParameters] = $this->activityViewSql($view);

        $cursorWhere = '';
        if ($afterCursor !== null) {
            // `id` is the sole tiebreaker (rather than also comparing
            // post_id, as the old date-only cursor did): id is already
            // unique, so it alone guarantees a stable, gapless order
            // regardless of which column is being sorted on.
            $comparisonOperator = $sortDirection === 'desc' ? '<' : '>';
            $cursorWhere = 'AND (
                ' . $sortColumnSql . ' ' . $comparisonOperator . ' :cursor_sort_value
                OR (' . $sortColumnSql . ' = :cursor_sort_value AND activity.id ' . $comparisonOperator . ' :cursor_id)
            )';
            $viewParameters['cursor_sort_value'] = $afterCursor['sort_value'];
            $viewParameters['cursor_id'] = $afterCursor['id'];
        }

        $stmt = $this->pdo()->prepare(
            'SELECT activity.created_at, activity.kind, activity.record_family, activity.action_key,
                    activity.post_id, activity.thread_id, activity.label, activity.board_tags_json,
                    activity.author_identity_id,
                    activity.source_path, activity.source_commit_sha,
                    activity.id, activity.author_label, activity.author_profile_slug,
                    activity.author_username_token, activity.author_is_approved
             FROM activity
             LEFT JOIN posts ON posts.post_id = activity.post_id
             WHERE 1 = 1
             ' . $viewWhere . '
             ' . $cursorWhere . '
             ORDER BY ' . $sortColumnSql . ' ' . $sortDirectionSql . ', activity.id ' . $sortDirectionSql . '
             LIMIT :limit'
        );
        foreach ($viewParameters as $parameter => $value) {
            $stmt->bindValue($parameter, $value);
        }
        // Fetch one extra row to detect whether a next page exists, then
        // trim it back off before building the returned item set.
        $stmt->bindValue('limit', self::ACTIVITY_ITEM_LIMIT + 1, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $hasMore = count($rows) > self::ACTIVITY_ITEM_LIMIT;
        if ($hasMore) {
            $rows = array_slice($rows, 0, self::ACTIVITY_ITEM_LIMIT);
        }

        $items = array_map(function (array $post): array {
            $sourcePath = $post['source_path'] !== null ? (string) $post['source_path'] : '';
            $sourceCommitSha = $post['source_commit_sha'] !== null ? (string) $post['source_commit_sha'] : '';
            $signature = $this->sourceSignatureLink($sourcePath);
            $item = [
                'created_at' => $post['created_at'],
                'kind' => $post['kind'],
                'record_family' => $post['record_family'],
                'action_key' => $post['action_key'],
                'post_id' => $post['post_id'],
                'thread_id' => $post['thread_id'],
                'label' => $post['label'],
                'board_tags_json' => $post['board_tags_json'],
                'source_path' => $sourcePath,
                'source_commit_sha' => $sourceCommitSha,
                'source_path_href' => $this->sourcePathHref($sourcePath, $sourceCommitSha),
                'source_commit_href' => $this->sourceCommitHref($sourceCommitSha),
                'source_commit_files' => $this->activityCommitManifest($sourceCommitSha) ?? [],
                'source_signature_path' => $signature['path'],
                'source_signature_href' => $signature['href'],
                'source_signature_status' => $this->sourceSignatureStatus(
                    $sourcePath,
                    (string) ($post['author_identity_id'] ?? ''),
                    $signature['path'],
                    true
                ),
                'id' => (int) $post['id'],
                'author_label' => $post['author_label'],
                'author_profile_slug' => $post['author_profile_slug'],
                'author_username_token' => $post['author_username_token'],
                'author_is_approved' => (int) $post['author_is_approved'],
            ];
            $item['relevant_files'] = $this->activityItemRelevantFiles($item);

            return $item;
        }, $rows);

        $items = array_values(array_filter($items, function (array $item) use ($view): bool {
            $boardTagsJson = (string) $item['board_tags_json'];
            $hidden = $this->isHiddenBootstrapBoardTagsJson($boardTagsJson);

            return match ($view) {
                'all' => true,
                'content' => !$hidden,
                'identity' => $this->hasBoardTag($boardTagsJson, 'identity'),
                'bootstrap' => $this->hasBoardTag($boardTagsJson, 'identity') && $this->hasBoardTag($boardTagsJson, 'internal'),
                'approval' => $this->hasBoardTag($boardTagsJson, 'identity') && $this->hasBoardTag($boardTagsJson, 'approval'),
                default => true,
            };
        }));

        return ['items' => $items, 'has_more' => $hasMore];
    }

    /**
     * Counts the full number of activity rows matching a view, independent
     * of `ACTIVITY_ITEM_LIMIT`/pagination - for the left-pane folder counts,
     * which should show real totals rather than "however many happen to be
     * loaded so far". Mirrors `fetchActivity()`'s exact two-stage filter
     * (the SQL `WHERE` from `activityViewSql()`, then the same tag-based
     * PHP re-check) but selects only the columns that check needs, and
     * skips the per-item transform `fetchActivity()` does for rendering
     * (signature checks, link building, etc.) - unneeded and expensive
     * across a potentially large, unlimited row set.
     */
    public function countActivityViewTotal(string $view): int
    {
        $view = $this->normalizeActivityView($view);
        [$viewWhere, $viewParameters] = $this->activityViewSql($view);
        $stmt = $this->pdo()->prepare(
            'SELECT activity.board_tags_json
             FROM activity
             LEFT JOIN posts ON posts.post_id = activity.post_id
             WHERE 1 = 1
             ' . $viewWhere
        );
        foreach ($viewParameters as $parameter => $value) {
            $stmt->bindValue($parameter, $value);
        }
        $stmt->execute();

        $count = 0;
        while (($row = $stmt->fetch()) !== false) {
            $boardTagsJson = (string) $row['board_tags_json'];
            $hidden = $this->isHiddenBootstrapBoardTagsJson($boardTagsJson);
            $matches = match ($view) {
                'all' => true,
                'content' => !$hidden,
                'identity' => $this->hasBoardTag($boardTagsJson, 'identity'),
                'bootstrap' => $this->hasBoardTag($boardTagsJson, 'identity') && $this->hasBoardTag($boardTagsJson, 'internal'),
                'approval' => $this->hasBoardTag($boardTagsJson, 'identity') && $this->hasBoardTag($boardTagsJson, 'approval'),
                default => true,
            };
            if ($matches) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * @return array{0:string,1:array<string, string>}
     */
    private function activityViewSql(string $view): array
    {
        $quotedHiddenTag = '%"' . ThreadRowSupport::HIDDEN_BOOTSTRAP_TAG . '"%';

        return match ($view) {
            'identity' => ['AND activity.board_tags_json LIKE :identity_tag', ['identity_tag' => $quotedHiddenTag]],
            'bootstrap' => [
                'AND activity.board_tags_json LIKE :identity_tag AND activity.board_tags_json LIKE :internal_tag',
                ['identity_tag' => $quotedHiddenTag, 'internal_tag' => '%"internal"%'],
            ],
            'approval' => [
                'AND activity.board_tags_json LIKE :identity_tag AND activity.board_tags_json LIKE :approval_tag',
                ['identity_tag' => $quotedHiddenTag, 'approval_tag' => '%"approval"%'],
            ],
            'content' => [
                'AND activity.board_tags_json NOT LIKE :hidden_tag
                 AND (activity.post_id IS NULL OR COALESCE(posts.is_hidden, 0) = 0)',
                ['hidden_tag' => $quotedHiddenTag],
            ],
            default => ['', []],
        };
    }

    /**
     * Resolves requested ?sort=/?dir= values for the Activity list against
     * its three sortable columns, falling back to 'date' when the column is
     * missing or unrecognized (today's default order). An unrecognized
     * direction falls back to a per-column default: descending for date,
     * ascending for the text columns - mirroring
     * `ForteBoardController::resolveSort()`'s pattern, though Activity
     * always resolves to a real column (never an
     * empty-string sentinel) since its cursor needs one to key off.
     *
     * @return array{column: string, direction: string}
     */
    public function resolveActivitySort(string $requestedColumn, string $requestedDirection): array
    {
        $validColumns = ['date', 'kind', 'label'];
        $column = in_array($requestedColumn, $validColumns, true) ? $requestedColumn : 'date';

        $defaultDirection = $column === 'date' ? 'desc' : 'asc';
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : $defaultDirection;

        return ['column' => $column, 'direction' => $direction];
    }

    /**
     * Maps a column key already validated by `resolveActivitySort()` to its
     * SQL expression. Not parameterized/bound (like `activityViewSql()`'s
     * fragments) since it only ever returns one of these fixed literals.
     */
    private function activitySortSql(string $column): string
    {
        return match ($column) {
            'kind' => 'activity.kind',
            'label' => 'activity.label',
            default => 'activity.created_at',
        };
    }

    /**
     * Reads the value of whichever column is currently the active sort key
     * out of an already-built `fetchActivity()` item, for constructing that
     * item's keyset cursor (`{sort_value, id}`). Keeps the column-to-field
     * mapping in one place alongside `activitySortSql()`'s column-to-SQL
     * mapping, rather than duplicating a match() at each call site.
     *
     * @param array<string, mixed> $item
     */
    public function activitySortValueFromItem(array $item, string $column): string
    {
        return match ($column) {
            'kind' => (string) $item['kind'],
            'label' => (string) $item['label'],
            default => (string) $item['created_at'],
        };
    }

    public function normalizeActivityView(string $view): string
    {
        return in_array($view, ['all', 'content', 'identity', 'bootstrap', 'approval', 'commits'], true) ? $view : 'all';
    }

    private function hasBoardTag(string $boardTagsJson, string $tag): bool
    {
        $boardTags = json_decode($boardTagsJson, true);
        if (!is_array($boardTags)) {
            return false;
        }

        return in_array($tag, $boardTags, true);
    }

    private function isHiddenBootstrapBoardTagsJson(string $boardTagsJson): bool
    {
        return ThreadRowSupport::isHiddenBootstrapBoardTagsJson($boardTagsJson);
    }

    /**
     * Mirrors `fetchActivity()`'s keyset-cursor pagination shape, over the
     * `commits` table instead of `activity` - a commit has no "view" to
     * filter by, so this is simpler than `fetchActivity()` (no WHERE
     * fragment beyond the cursor itself).
     *
     * @param array{sort_value: string, id: int}|null $afterCursor
     * @return array{items: array<int, array<string, mixed>>, has_more: bool}
     */
    public function fetchCommits(string $sortColumn, string $sortDirection, ?array $afterCursor = null): array
    {
        ['column' => $sortColumn, 'direction' => $sortDirection] = $this->resolveCommitSort($sortColumn, $sortDirection);
        $sortColumnSql = $this->commitSortSql($sortColumn);
        $sortDirectionSql = $sortDirection === 'desc' ? 'DESC' : 'ASC';

        $cursorWhere = '';
        $parameters = [];
        if ($afterCursor !== null) {
            $comparisonOperator = $sortDirection === 'desc' ? '<' : '>';
            $cursorWhere = 'WHERE (
                ' . $sortColumnSql . ' ' . $comparisonOperator . ' :cursor_sort_value
                OR (' . $sortColumnSql . ' = :cursor_sort_value AND commits.id ' . $comparisonOperator . ' :cursor_id)
            )';
            $parameters['cursor_sort_value'] = $afterCursor['sort_value'];
            $parameters['cursor_id'] = $afterCursor['id'];
        }

        $stmt = $this->pdo()->prepare(
            'SELECT commits.sha, commits.author_name, commits.author_email, commits.committed_at,
                    commits.subject, commits.file_count, commits.id
             FROM commits
             ' . $cursorWhere . '
             ORDER BY ' . $sortColumnSql . ' ' . $sortDirectionSql . ', commits.id ' . $sortDirectionSql . '
             LIMIT :limit'
        );
        foreach ($parameters as $parameter => $value) {
            $stmt->bindValue($parameter, $value);
        }
        // Reuses the same page size as fetchActivity(): fetch one extra row
        // to detect whether a next page exists, then trim it back off.
        $stmt->bindValue('limit', self::ACTIVITY_ITEM_LIMIT + 1, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll();

        $hasMore = count($rows) > self::ACTIVITY_ITEM_LIMIT;
        if ($hasMore) {
            $rows = array_slice($rows, 0, self::ACTIVITY_ITEM_LIMIT);
        }

        $items = array_map(static fn (array $row): array => [
            'sha' => (string) $row['sha'],
            'author_name' => (string) $row['author_name'],
            'author_email' => (string) $row['author_email'],
            'committed_at' => (string) $row['committed_at'],
            'subject' => (string) $row['subject'],
            'file_count' => (int) $row['file_count'],
            'id' => (int) $row['id'],
        ], $rows);

        return ['items' => $items, 'has_more' => $hasMore];
    }

    /**
     * Full commit count for the Commits view's left-pane folder count -
     * mirrors `countActivityViewTotal()`'s purpose, but every commit
     * counts (no per-view filter to apply).
     */
    public function countCommitsTotal(): int
    {
        return (int) $this->pdo()->query('SELECT COUNT(*) FROM commits')->fetchColumn();
    }

    /**
     * Mirrors `resolveActivitySort()`'s shape for the Commits view. Only
     * `date` is sortable for now - kept as its own small resolver (not
     * folded into `resolveActivitySort()`) since the two views' valid
     * column sets are unrelated.
     *
     * @return array{column: string, direction: string}
     */
    public function resolveCommitSort(string $requestedColumn, string $requestedDirection): array
    {
        $validColumns = ['date'];
        $column = in_array($requestedColumn, $validColumns, true) ? $requestedColumn : 'date';
        $direction = in_array($requestedDirection, ['asc', 'desc'], true) ? $requestedDirection : 'desc';

        return ['column' => $column, 'direction' => $direction];
    }

    private function commitSortSql(string $column): string
    {
        return match ($column) {
            default => 'commits.committed_at',
        };
    }

    /**
     * Mirrors `activitySortValueFromItem()`, for building a commit's
     * keyset cursor from the last item on a page.
     *
     * @param array<string, mixed> $item
     */
    public function commitSortValueFromItem(array $item, string $column): string
    {
        return match ($column) {
            default => (string) $item['committed_at'],
        };
    }

    public function sourcePathHref(string $sourcePath, string $sourceCommitSha): ?string
    {
        if ($sourcePath === '' || !SourcePathValidator::isValidCanonicalPath($sourcePath)) {
            return null;
        }

        $encodedPath = $this->encodeSourcePathForUrl($sourcePath);
        if (SourcePathValidator::isValidCommitSha($sourceCommitSha)) {
            return '/source/blob/' . $sourceCommitSha . '/' . $encodedPath;
        }

        return '/source/current/' . $encodedPath;
    }

    public function sourceCommitHref(string $sourceCommitSha): ?string
    {
        if (!SourcePathValidator::isValidCommitSha($sourceCommitSha)) {
            return null;
        }

        return '/source/commits/' . $sourceCommitSha;
    }

    /**
     * @return array{path:string,href:string}
     */
    public function sourceSignatureLink(string $sourcePath): array
    {
        foreach ($this->sourceSignaturePathCandidates($sourcePath) as $signaturePath) {
            if (SourcePathValidator::currentPathExists($this->repositoryRoot, $signaturePath)) {
                return [
                    'path' => $signaturePath,
                    'href' => '/source/current/' . $this->encodeSourcePathForUrl($signaturePath),
                ];
            }
        }

        return ['path' => '', 'href' => ''];
    }

    public function sourceSignatureStatus(
        string $sourcePath,
        string $authorIdentityId,
        string $sourceSignaturePath,
        bool $authorIdentityIdIsCanonical = false
    ): string
    {
        if ($sourceSignaturePath !== '' || !str_starts_with($sourcePath, 'records/posts/')) {
            return '';
        }

        if (!$authorIdentityIdIsCanonical) {
            $canonicalAuthorIdentityId = $this->canonicalPostAuthorIdentityId($sourcePath);
            if ($canonicalAuthorIdentityId !== null) {
                return $canonicalAuthorIdentityId === '' ? 'anonymous unsigned' : 'legacy unsigned';
            }
        }

        return $authorIdentityId === '' ? 'anonymous unsigned' : 'legacy unsigned';
    }

    private function canonicalPostAuthorIdentityId(string $sourcePath): ?string
    {
        try {
            $post = (new CanonicalRecordRepository($this->repositoryRoot))->loadPost($sourcePath);
        } catch (RuntimeException) {
            return null;
        }

        return $post->authorIdentityId ?? '';
    }

    /**
     * @return list<string>
     */
    private function sourceSignaturePathCandidates(string $sourcePath): array
    {
        if (!SourcePathValidator::isValidCanonicalRecordPath($sourcePath)) {
            return [];
        }

        return [
            $sourcePath . '.asc',
            $sourcePath . '.sig',
        ];
    }

    private function encodeSourcePathForUrl(string $sourcePath): string
    {
        return implode('/', array_map('rawurlencode', explode('/', $sourcePath)));
    }

    /**
     * @return list<array{status:string,path:string,previous_path:string,role:string,href:string,signature_signer_identity:string,signature_public_key_path:string,signature_public_key_href:string,signature_key_status:string}>|null
     */
    public function activityCommitManifest(string $commitSha): ?array
    {
        if (array_key_exists($commitSha, $this->activityCommitManifestCache)) {
            return $this->activityCommitManifestCache[$commitSha];
        }

        // The expensive part - a `git diff-tree` exec plus a signature/
        // OpenPGP lookup per file - is cached persistently by commit sha
        // (see SqliteActivityCommitManifestCache), since a commit's file
        // list and each file's role/signer never change. Only the
        // request-specific hrefs below are always recomputed - they're
        // cheap string formatting, not worth persisting.
        $rawFiles = $this->activityCommitManifestCacheStore()?->get($commitSha);
        if ($rawFiles === null) {
            $files = $this->sourceCommitFiles($commitSha);
            if ($files === null) {
                $this->activityCommitManifestCache[$commitSha] = null;
                return null;
            }

            $rawFiles = array_map(function (array $file): array {
                $signature = $this->activityCommitSignatureMetadata($file['path']);

                return [
                    'status' => $file['status'],
                    'path' => $file['path'],
                    'previous_path' => $file['previous_path'],
                    'role' => $this->sourceCommitFileRole($file['path']),
                    'signature_signer_identity' => $signature['signer_identity'],
                    'signature_public_key_path' => $signature['public_key_path'],
                    'signature_key_status' => $signature['status'],
                ];
            }, $files);

            $this->activityCommitManifestCacheStore()?->put($commitSha, $rawFiles);
        }

        $manifest = array_map(function (array $file) use ($commitSha): array {
            return [
                'status' => $file['status'],
                'path' => $file['path'],
                'previous_path' => $file['previous_path'],
                'role' => $file['role'],
                'href' => $this->sourceCommitFileHref($file['path'], $file['status'], $commitSha),
                'signature_signer_identity' => $file['signature_signer_identity'],
                'signature_public_key_path' => $file['signature_public_key_path'],
                'signature_public_key_href' => $file['signature_public_key_path'] !== ''
                    ? '/source/current/' . $this->encodeSourcePathForUrl($file['signature_public_key_path'])
                    : '',
                'signature_key_status' => $file['signature_key_status'],
            ];
        }, $rawFiles);

        $this->activityCommitManifestCache[$commitSha] = $manifest;

        return $manifest;
    }

    /**
     * Lazily opens the persistent commit-manifest cache alongside the main
     * read-model database. Returns null (never throws) on any failure to
     * open/create it, since this is purely a performance optimization -
     * activityCommitManifest() must still work correctly, just slower,
     * when this is unavailable.
     */
    private function activityCommitManifestCacheStore(): ?SqliteActivityCommitManifestCache
    {
        if ($this->activityCommitManifestCacheStoreInitialized) {
            return $this->activityCommitManifestCacheStore;
        }

        $this->activityCommitManifestCacheStoreInitialized = true;
        $path = dirname($this->databasePath) . '/activity_commit_manifest_cache.sqlite3';
        try {
            $this->activityCommitManifestCacheStore = new SqliteActivityCommitManifestCache(new PDO('sqlite:' . $path));
        } catch (\Throwable) {
            $this->activityCommitManifestCacheStore = null;
        }

        return $this->activityCommitManifestCacheStore;
    }

    /**
     * @return list<array{status:string,path:string,previous_path:string}>|null
     */
    public function sourceCommitFiles(string $commitSha): ?array
    {
        if (!SourcePathValidator::isValidCommitSha($commitSha) || !is_dir($this->repositoryRoot . '/.git')) {
            return null;
        }

        if (array_key_exists($commitSha, $this->sourceCommitFileManifestCache)) {
            return $this->sourceCommitFileManifestCache[$commitSha];
        }

        $command = sprintf(
            'git -C %s diff-tree --root --no-commit-id --name-status -r -M %s 2>/dev/null',
            escapeshellarg($this->repositoryRoot),
            escapeshellarg($commitSha)
        );
        $output = [];
        $exitCode = 0;
        exec($command, $output, $exitCode);
        if ($exitCode !== 0) {
            $this->sourceCommitFileManifestCache[$commitSha] = null;
            return null;
        }

        $files = [];
        foreach ($output as $line) {
            $parts = explode("\t", $line);
            $rawStatus = trim((string) array_shift($parts));
            $status = match ($rawStatus[0] ?? '') {
                'A' => 'added',
                'M' => 'modified',
                'D' => 'deleted',
                'R' => 'renamed',
                default => 'changed',
            };
            $previousPath = '';
            if ($status === 'renamed') {
                $previousPath = trim((string) ($parts[0] ?? ''));
                $path = trim((string) ($parts[1] ?? ''));
            } else {
                $path = trim((string) ($parts[0] ?? ''));
            }

            if ($path === '') {
                continue;
            }

            $files[] = [
                'status' => $status,
                'path' => $path,
                'previous_path' => $previousPath,
            ];
        }

        $this->sourceCommitFileManifestCache[$commitSha] = $files;

        return $files;
    }

    /**
     * Narrows a commit's full file manifest down to the files one action's
     * detail view should show: its own record, and that record's detached
     * signature (if any) - never the rest of the commit, which can run to
     * thousands of files for actions that happen to share a large
     * historical commit (e.g. the original archive import). identity_
     * bootstrap is the one compound action that also establishes a separate
     * identity record in the same commit.
     *
     * The signer's public key only gets its own row when this action's own
     * commit actually introduced it (e.g. a fresh identity_bootstrap, which
     * adds the key alongside the record and signature it authenticates) -
     * genuinely one of the files this action added, not just referenced.
     * When the key instead already existed from some earlier, unrelated
     * commit (the common case: a key is normally established once and
     * reused for everything it later signs), it isn't a file this action
     * added, and it's already named and linked on the signature's own entry
     * ("Public key:"), so a standalone row for it would just repeat the
     * same file a second time without saying anything new.
     *
     * The signature itself is resolved independent of whether it's actually
     * part of *this* item's own commit - it can occasionally live in a
     * different commit than the record it signs. Only the item's own record
     * - and, for identity_bootstrap, its paired identity record - is
     * guaranteed to be part of the item's own commit (that's precisely the
     * commit source_commit_sha names); everything else falls back to a
     * standalone entry built the same way activityCommitManifest() would
     * build it, just not sourced from that one commit's diff.
     *
     * @param array<string, mixed> $item
     * @return list<array{status:string,path:string,previous_path:string,role:string,href:string,signature_signer_identity:string,signature_public_key_path:string,signature_public_key_href:string,signature_key_status:string}>
     */
    private function activityItemRelevantFiles(array $item): array
    {
        $sourcePath = (string) ($item['source_path'] ?? '');
        if ($sourcePath === '') {
            return [];
        }

        $byPath = [];
        foreach (($item['source_commit_files'] ?? []) as $file) {
            $byPath[$file['path']] = $file;
        }

        $files = [];

        $files[] = $byPath[$sourcePath] ?? $this->standaloneRelevantFile(
            $sourcePath,
            (string) ($item['source_path_href'] ?? ''),
            $this->sourceCommitFileRole($sourcePath),
        );

        if ((string) ($item['record_family'] ?? '') === 'identity_bootstrap') {
            $identityId = $this->signatureSignerIdentityId($sourcePath);
            $fingerprint = $identityId !== null ? $this->openPgpFingerprintFromIdentityId($identityId) : null;
            if ($fingerprint !== null) {
                $identityRecordPath = CanonicalPathResolver::identity(strtolower($fingerprint));
                $identityRecordHref = SourcePathValidator::currentPathExists($this->repositoryRoot, $identityRecordPath)
                    ? '/source/current/' . $this->encodeSourcePathForUrl($identityRecordPath)
                    : '';
                $files[] = $byPath[$identityRecordPath] ?? $this->standaloneRelevantFile(
                    $identityRecordPath,
                    $identityRecordHref,
                    'identity record',
                );
            }
        }

        $signaturePath = (string) ($item['source_signature_path'] ?? '');
        if ($signaturePath !== '') {
            $signatureEntry = $byPath[$signaturePath] ?? $this->standaloneSignatureRelevantFile(
                $signaturePath,
                (string) ($item['source_signature_href'] ?? ''),
            );
            $files[] = $signatureEntry;

            // The public key only gets its own row when this action's own
            // commit actually added/touched it - e.g. a fresh
            // identity_bootstrap, which introduces the key alongside the
            // record and signature it authenticates. When the key instead
            // already existed from some earlier, unrelated commit (the
            // common case for an ordinary signed post - a key is normally
            // established once and reused for everything it later signs),
            // it isn't really a file *this* action added, and it's already
            // named and linked on the signature entry itself ("Public
            // key:"), so a standalone row for it would just repeat the same
            // file a second time without saying anything new.
            $publicKeyPath = $signatureEntry['signature_public_key_path'];
            if ($publicKeyPath !== '' && isset($byPath[$publicKeyPath])) {
                $files[] = $byPath[$publicKeyPath];
            }
        }

        return $files;
    }

    /**
     * @return array{status:string,path:string,previous_path:string,role:string,href:string,signature_signer_identity:string,signature_public_key_path:string,signature_public_key_href:string,signature_key_status:string}
     */
    private function standaloneRelevantFile(string $path, string $href, string $role): array
    {
        return [
            'status' => 'current',
            'path' => $path,
            'previous_path' => '',
            'role' => $role,
            'href' => $href,
            'signature_signer_identity' => '',
            'signature_public_key_path' => '',
            'signature_public_key_href' => '',
            'signature_key_status' => '',
        ];
    }

    /**
     * @return array{status:string,path:string,previous_path:string,role:string,href:string,signature_signer_identity:string,signature_public_key_path:string,signature_public_key_href:string,signature_key_status:string}
     */
    private function standaloneSignatureRelevantFile(string $signaturePath, string $href): array
    {
        $signature = $this->activityCommitSignatureMetadata($signaturePath);

        return [
            'status' => 'current',
            'path' => $signaturePath,
            'previous_path' => '',
            'role' => 'detached signature',
            'href' => $href,
            'signature_signer_identity' => $signature['signer_identity'],
            'signature_public_key_path' => $signature['public_key_path'],
            'signature_public_key_href' => $signature['public_key_href'],
            'signature_key_status' => $signature['status'],
        ];
    }

    private function sourceCommitFileRole(string $path): string
    {
        if (SourcePathValidator::isValidCanonicalDetachedSignaturePath($path)) {
            return 'detached signature';
        }

        return match (true) {
            str_starts_with($path, 'records/posts/') => 'post record',
            str_starts_with($path, 'records/thread-labels/') => 'thread label record',
            str_starts_with($path, 'records/post-reactions/') => 'post reaction record',
            str_starts_with($path, 'records/identity/') => 'identity record',
            str_starts_with($path, 'records/approval-seeds/') => 'approval seed record',
            str_starts_with($path, 'records/public-keys/') => 'public key',
            $path === 'records/instance/public.txt' => 'instance record',
            $path === 'records/instance/feature-flags.txt' => 'feature flags record',
            SourcePathValidator::isValidCanonicalRecordPath($path) => 'canonical record',
            default => 'other committed file',
        };
    }

    private function sourceCommitFileHref(string $path, string $status, string $commitSha): string
    {
        if ($status === 'deleted' || !SourcePathValidator::isValidCanonicalPath($path)) {
            return '';
        }

        return $this->sourcePathHref($path, $commitSha) ?? '';
    }

    /**
     * @return array{signer_identity:string,public_key_path:string,public_key_href:string,status:string}
     */
    private function activityCommitSignatureMetadata(string $signaturePath): array
    {
        $empty = [
            'signer_identity' => '',
            'public_key_path' => '',
            'public_key_href' => '',
            'status' => '',
        ];
        $recordPath = $this->sourceSignatureRecordPath($signaturePath);
        if ($recordPath === null) {
            return $empty;
        }

        $identityId = $this->signatureSignerIdentityId($recordPath);
        if ($identityId === null) {
            return [...$empty, 'status' => 'signing identity unavailable'];
        }

        $fingerprint = $this->openPgpFingerprintFromIdentityId($identityId);
        if ($fingerprint === null) {
            return [...$empty, 'signer_identity' => $identityId, 'status' => 'signing key unavailable'];
        }

        foreach ([CanonicalPathResolver::publicKey($fingerprint), 'records/public-keys/openpgp-' . strtolower($fingerprint) . '.asc'] as $path) {
            if (!SourcePathValidator::currentPathExists($this->repositoryRoot, $path)) {
                continue;
            }

            return [
                'signer_identity' => $identityId,
                'public_key_path' => $path,
                'public_key_href' => '/source/current/' . $this->encodeSourcePathForUrl($path),
                'status' => 'ok',
            ];
        }

        return [...$empty, 'signer_identity' => $identityId, 'status' => 'signing key unavailable'];
    }

    private function sourceSignatureRecordPath(string $signaturePath): ?string
    {
        foreach (['.asc', '.sig'] as $suffix) {
            if (str_ends_with($signaturePath, $suffix)) {
                $recordPath = substr($signaturePath, 0, -strlen($suffix));
                return SourcePathValidator::isValidCanonicalRecordPath($recordPath) ? $recordPath : null;
            }
        }

        return null;
    }

    private function signatureSignerIdentityId(string $recordPath): ?string
    {
        try {
            $repository = new CanonicalRecordRepository($this->repositoryRoot);
            if (str_starts_with($recordPath, 'records/posts/')) {
                return $repository->loadPost($recordPath)->authorIdentityId;
            }
            if (str_starts_with($recordPath, 'records/identity/')) {
                return $repository->loadIdentity($recordPath)->identityId;
            }
            if (str_starts_with($recordPath, 'records/thread-labels/')) {
                return $repository->loadThreadLabel($recordPath)->authorIdentityId;
            }
            if (str_starts_with($recordPath, 'records/post-reactions/')) {
                return $repository->loadPostReaction($recordPath)->authorIdentityId;
            }
        } catch (RuntimeException) {
            return null;
        }

        return null;
    }

    private function openPgpFingerprintFromIdentityId(string $identityId): ?string
    {
        if (preg_match('/^openpgp:([a-f0-9]{40})$/i', trim($identityId), $matches) !== 1) {
            return null;
        }

        return strtoupper($matches[1]);
    }
}
