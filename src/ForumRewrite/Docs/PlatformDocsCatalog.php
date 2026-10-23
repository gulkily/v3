<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

final class PlatformDocsCatalog
{
    private static array $snapshots = [];

    public const DOCUMENT_ROOT = 'docs';
    public const MAX_DOCUMENT_BYTES = 1048576;
    public const ROOT_DOCUMENTS = ['README.md', 'CONTRIBUTING.md', 'AGENTS.md', 'BLESSING.md'];
    public const PAGE_SIZE = 40;

    /**
     * The catalog improves discovery; it does not determine which safe
     * Markdown files under docs/ may be read.
     *
     * @return list<array{title:string,category:string,path:string,description:string}>
     */
    public static function entries(): array
    {
        return [
            [
                'title' => 'Public Architecture and Trust Model',
                'category' => 'Architecture',
                'path' => 'docs/architecture/public_architecture_and_trust.md',
                'description' => 'How the Git records, the views built from them, OpenPGP identity, and private data relate.',
            ],
            [
                'title' => 'Canonical Post Record',
                'category' => 'Architecture',
                'path' => 'docs/specs/canonical_post_record_v1.md',
                'description' => 'The file format for posts and replies: one text file per post, headers then body.',
            ],
            [
                'title' => 'Identity Bootstrap Record',
                'category' => 'Trust and Identity',
                'path' => 'docs/specs/identity_bootstrap_record_v1.md',
                'description' => 'The file that records a new identity: its OpenPGP public key, fingerprint, and username.',
            ],
            [
                'title' => 'v3 CLI Reference',
                'category' => 'CLI Reference',
                'path' => 'docs/reference/v3_cli.md',
                'description' => 'Commands, options, and error handling for the v3 CLI.',
            ],
            [
                'title' => 'Extension and Improvement Cookbook',
                'category' => 'Extending the Platform',
                'path' => 'docs/examples/extension_improvement_cookbook.md',
                'description' => 'Worked examples of extending the site, including with coding agents.',
            ],
            [
                'title' => 'Agent API Reference',
                'category' => 'Extending the Platform',
                'path' => 'docs/reference/agent_api.md',
                'description' => 'How an agent creates an OpenPGP identity and publishes signed posts over HTTP.',
            ],
            [
                'title' => 'Agent Reply Analyze/Publish Contract',
                'category' => 'Extending the Platform',
                'path' => 'docs/specs/agent_reply_one_step_analyze_publish_contract_v1.md',
                'description' => 'How POST /api/analyze_post stores a post\'s analysis and, when agent replies are enabled and the reply gates pass, posts the suggested reply as reply-agent.',
            ],
            [
                'title' => 'Theme Development Guide',
                'category' => 'Architecture',
                'path' => 'docs/runbooks/theme_development_guide.md',
                'description' => 'How themes work and how to add one.',
            ],
            [
                'title' => 'Post Retraction: Complete Workflow',
                'category' => 'Operations',
                'path' => 'docs/reference/author_post_retraction.md',
                'description' => 'Retract a post, verify cleanup, and recover from failures.',
            ],
            [
                'title' => 'Production Deploy Runbook',
                'category' => 'Operations',
                'path' => 'docs/runbooks/production_deploy.md',
                'description' => 'How to deploy to production, and what a shared host needs.',
            ],
            [
                'title' => 'Operator Recovery Runbook',
                'category' => 'Operations',
                'path' => 'docs/runbooks/operator_recovery.md',
                'description' => 'How to check and repair the production read model and workers.',
            ],
        ];
    }

    /** Discover from the deployed source tree, without a separately maintained catalog. */
    public static function inventory(string $projectRoot): array
    {
        if (isset(self::$snapshots[$projectRoot])) {
            return self::$snapshots[$projectRoot];
        }
        $paths = self::ROOT_DOCUMENTS;
        $docs = rtrim($projectRoot, '/') . '/docs';
        if (is_dir($docs) && !is_link($docs)) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($docs, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if ($file->isFile() && !$file->isLink()) {
                    $paths[] = 'docs/' . substr($file->getPathname(), strlen($docs) + 1);
                }
            }
        }
        $curated = array_column(self::entries(), null, 'path');
        $labels = [];
        foreach (self::entries() as $entry) {
            $labels[self::categorySlug($entry['category'])] = $entry['category'];
        }
        $entries = [];
        foreach ($paths as $path) {
            $resolved = self::resolveSource($projectRoot, $path);
            if ($resolved === null) {
                continue;
            }
            $size = filesize($resolved);
            $readable = $size !== false && $size <= self::MAX_DOCUMENT_BYTES;
            $text = $readable && strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'pdf' ? (string) file_get_contents($resolved) : '';
            $text = mb_scrub($text, 'UTF-8');
            preg_match('/^#\s+(.+)$/m', $text, $heading);
            $group = str_starts_with($path, 'docs/plans/') ? 'Plans and history'
                : (str_starts_with($path, 'docs/railspike/') ? 'Workflow' : 'Current guidance');
            $category = self::categorySlug($curated[$path]['category'] ?? (str_contains($path, '/') ? explode('/', $path)[1] : 'Project'));
            $entries[] = [
                'path' => $path,
                'title' => $curated[$path]['title'] ?? trim($heading[1] ?? basename($path)),
                'category' => $category,
                'categoryLabel' => $labels[$category] ?? ucfirst(str_replace('-', ' ', $category)),
                'description' => $curated[$path]['description'] ?? '',
                'summary' => $curated[$path]['description'] ?? (in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), ['md', 'markdown'], true) ? self::summary($text) : ''),
                'folder' => dirname($path) === '.' ? 'Project' : dirname($path),
                'group' => $group,
                'href' => self::routeForPath($path),
                'available' => $readable,
                'text' => $text,
                'hash' => hash_file('sha256', $resolved),
                'format' => strtolower(pathinfo($path, PATHINFO_EXTENSION)),
            ];
        }
        usort($entries, static fn (array $a, array $b): int =>
            (($a['group'] === 'Current guidance' ? 0 : 1) <=> ($b['group'] === 'Current guidance' ? 0 : 1))
            ?: strnatcasecmp($a['path'], $b['path']));
        return $entries;
    }

    /**
     * Published file for a browse query (no search text, at most one filter), relative to the
     * artifact root, or null when the query needs the browser search shell.
     */
    public static function browsePath(array $query): ?string
    {
        $values = [];
        foreach (['q', 'folder', 'category', 'group', 'page'] as $key) {
            $value = is_string($query[$key] ?? null) ? trim($query[$key]) : '';
            if ($value !== '') {
                $values[$key] = $key === 'category' ? self::categorySlug($value) : $value;
            }
        }
        $page = $values['page'] ?? '1';
        unset($values['page']);
        if (isset($values['q']) || count($values) > 1 || preg_match('/^[1-9][0-9]{0,4}$/', $page) !== 1) {
            return null;
        }
        $filter = $values === [] ? 'all' : array_key_first($values) . '/' . rawurlencode((string) reset($values));
        return '/docs/_browse/' . $filter . '/' . $page . '.html';
    }

    /**
     * Every browse query a static release prebuilds: all documents, then each collection,
     * category and folder, one query per results page.
     *
     * @return list<array<string, string>>
     */
    public static function browseQueries(array $inventory, int $pageSize): array
    {
        $counts = self::counts($inventory);
        $filters = [[[], count($inventory)]];
        foreach (['group' => $counts['group'], 'category' => array_map(static fn (array $category): int => $category['count'], $counts['category']), 'folder' => $counts['folder']] as $key => $values) {
            foreach ($values as $value => $count) {
                $filters[] = [[$key => (string) $value], $count];
            }
        }
        $queries = [];
        foreach ($filters as [$filter, $count]) {
            for ($page = 1; $page <= max(1, (int) ceil($count / $pageSize)); $page++) {
                $queries[] = $filter + ['page' => (string) $page];
            }
        }
        return $queries;
    }

    /** First prose sentence of a Markdown body, as plain text, for result rows. */
    public static function summary(string $markdown): string
    {
        $fenced = false;
        foreach (preg_split('/\R/', $markdown) ?: [] as $line) {
            $line = trim($line);
            if (str_starts_with($line, '```') || str_starts_with($line, '~~~')) {
                $fenced = !$fenced;
                continue;
            }
            // Skip structure (headings, quotes, lists, tables, rules) and short "Key: value" status lines.
            if ($fenced || $line === '' || preg_match('/^([#>|*+-]|\d+[.)]\s|<|---|===)/', $line)
                || (strlen($line) < 48 && preg_match('/^[A-Z][\w ]*:\s+\S/', $line))) {
                continue;
            }
            $text = preg_replace(['/!?\[([^\]]*)\]\([^)]*\)/', '/[*_`]+/'], ['$1', ''], $line) ?? $line;
            $sentence = preg_match('/^.+?[.!?](?=\s|$)/u', $text, $match) ? $match[0] : $text;
            return mb_strlen($sentence) > 160 ? rtrim(mb_substr($sentence, 0, 159)) . '…' : $sentence;
        }
        return '';
    }

    /** Case-insensitive category key, so folder and curated names share one filter value. */
    public static function categorySlug(string $category): string
    {
        return trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($category)), '-');
    }

    /**
     * Curated guides grouped by topic, in first-listed order. Single-guide topics
     * listed next to each other share a row on wide screens.
     *
     * @return array<string, list<array{title:string,category:string,path:string,description:string}>>
     */
    public static function topics(): array
    {
        $topics = [];
        foreach (self::entries() as $entry) {
            $topics[$entry['category']][] = $entry;
        }
        return $topics;
    }

    /**
     * Document counts per collection, category and folder (folders include subfolders).
     *
     * @return array{group: array<string, int>, category: array<string, array{label:string,count:int}>, folder: array<string, int>}
     */
    public static function counts(array $inventory): array
    {
        $counts = ['group' => [], 'category' => [], 'folder' => []];
        foreach ($inventory as $entry) {
            $counts['group'][$entry['group']] = ($counts['group'][$entry['group']] ?? 0) + 1;
            $counts['category'][$entry['category']] ??= ['label' => $entry['categoryLabel'], 'count' => 0];
            $counts['category'][$entry['category']]['count']++;
            for ($folder = $entry['folder']; $folder !== '.'; $folder = $folder === 'Project' ? '.' : dirname($folder)) {
                $counts['folder'][$folder] = ($counts['folder'][$folder] ?? 0) + 1;
            }
        }
        ksort($counts['folder'], SORT_NATURAL | SORT_FLAG_CASE);
        uasort($counts['category'], static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));
        return $counts;
    }

    /** Scope one build's metadata/search reads to the same source inventory. */
    public static function withSnapshot(string $projectRoot, callable $work): mixed
    {
        $previous = self::$snapshots[$projectRoot] ?? null;
        self::$snapshots[$projectRoot] = self::inventory($projectRoot);
        try {
            return $work();
        } finally {
            if ($previous === null) {
                unset(self::$snapshots[$projectRoot]);
            } else {
                self::$snapshots[$projectRoot] = $previous;
            }
        }
    }

    public static function search(array $inventory, array $query): array
    {
        $value = static fn (string $key): string => is_string($query[$key] ?? null) ? $query[$key] : '';
        $terms = preg_split('/\s+/u', mb_strtolower(trim($value('q')), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_filter($inventory, static function (array $entry) use ($value, $terms): bool {
            if (($value('folder') !== '' && $entry['folder'] !== $value('folder') && !str_starts_with($entry['folder'], $value('folder') . '/'))
                || ($value('category') !== '' && $entry['category'] !== self::categorySlug($value('category')))
                || ($value('group') !== '' && $entry['group'] !== $value('group'))) {
                return false;
            }
            if ($terms === []) {
                return true;
            }
            $text = mb_strtolower($entry['title'] . ' ' . $entry['path'] . ' ' . $entry['text'], 'UTF-8');
            foreach ($terms as $term) {
                if (!str_contains($text, $term)) {
                    return false;
                }
            }
            return true;
        }));
    }

    /** Safe source resolution, independent of the renderer's size limit. */
    public static function resolveSource(string $projectRoot, string $encodedPath): ?string
    {
        $path = self::normalizePath($encodedPath);
        $root = realpath($projectRoot);
        if ($path === null || $root === false) {
            return null;
        }
        $resolved = realpath($root . '/' . $path);
        if ($resolved === false || !is_file($resolved) || $resolved !== $root . '/' . $path) {
            return null;
        }
        if (in_array($path, self::ROOT_DOCUMENTS, true)) {
            return $resolved === $root . '/' . $path ? $resolved : null;
        }
        $docs = realpath($root . '/docs');
        return $docs === $root . '/docs' && str_starts_with($resolved, $docs . '/') ? $resolved : null;
    }

    public static function sourceForRoute(string $encodedPath): ?string
    {
        return str_starts_with($encodedPath, '_project/')
            ? self::normalizePath(substr($encodedPath, 9))
            : self::normalizePath('docs/' . $encodedPath);
    }

    /**
     * Resolve a Markdown destination to the existing public document route.
     * Local URLs never grant access beyond resolvePath's realpath/size boundary.
     */
    public static function resolveLink(string $url, ?string $sourcePath, string $projectRoot): ?string
    {
        if ($url === '' || preg_match('/[\x00-\x20\x7f\\\\]/', $url) === 1 || str_starts_with($url, '//')) {
            return null;
        }
        if (preg_match('~^https?://~i', $url) === 1) {
            return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
        }
        if (preg_match('~^mailto:~i', $url) === 1) {
            return filter_var(substr($url, 7), FILTER_VALIDATE_EMAIL) !== false ? $url : null;
        }
        if (str_starts_with($url, '#')) {
            return preg_match('/^#[^%<>"]+$/u', $url) === 1 ? $url : null;
        }
        if (preg_match('~^[a-z][a-z0-9+.-]*:~i', $url) === 1 || str_contains($url, '?')) {
            return null;
        }
        [$path, $fragment] = array_pad(explode('#', $url, 2), 2, null);
        $decoded = rawurldecode($path);
        // Reject encoded path syntax, repeated encoding and control characters.
        if (preg_match('~%(?:2e|2f|5c|25|0[0-9a-f]|1[0-9a-f]|7f)~i', $path) === 1
            || preg_match('/[\x00-\x1f\x7f\\\\]/', $decoded) === 1) {
            return null;
        }
        if (str_starts_with($decoded, '/')) {
            if (!str_starts_with($decoded, '/docs/') && !in_array(ltrim($decoded, '/'), self::ROOT_DOCUMENTS, true)) {
                return null;
            }
            $candidate = substr($decoded, 1);
        } else {
            if ($sourcePath === null || self::normalizePath($sourcePath) !== $sourcePath) {
                return null;
            }
            $candidate = dirname($sourcePath) . '/' . $decoded;
        }
        $segments = [];
        foreach (explode('/', $candidate) as $segment) {
            if ($segment === '.' || $segment === '') {
                continue;
            }
            if ($segment === '..') {
                if (count($segments) === 0) {
                    return null;
                }
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }
        $target = implode('/', $segments);
        if ($target === 'docs' || str_starts_with($target, 'docs/')) {
            $directory = realpath($projectRoot . '/' . $target);
            $docsRoot = realpath($projectRoot . '/docs');
            if ($directory !== false && is_dir($directory) && $docsRoot !== false
                && ($directory === $docsRoot || str_starts_with($directory, $docsRoot . '/'))) {
                return '/docs/?folder=' . rawurlencode($target) . '#docs-library-heading';
            }
        }
        if (self::resolveSource($projectRoot, $target) === null) {
            return null;
        }
        if ($fragment !== null && self::resolveLink('#' . $fragment, null, $projectRoot) === null) {
            return null;
        }

        return self::routeForPath($target) . ($fragment === null ? '' : '#' . $fragment);
    }

    public static function normalizePath(string $encodedPath): ?string
    {
        if (preg_match('~%(?:2e|2f|5c|25|0[0-9a-f]|1[0-9a-f]|7f)~i', $encodedPath) === 1) {
            return null;
        }
        $path = rawurldecode($encodedPath);

        if ($path === '' || !mb_check_encoding($path, 'UTF-8') || str_starts_with($path, '/') || preg_match('/[\x00-\x1f\x7f\\\\]/', $path) === 1) {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || str_starts_with($segment, '.')) {
                return null;
            }
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($path, self::ROOT_DOCUMENTS, true)
            && (!str_starts_with($path, 'docs/') || !(in_array($extension, ['md', 'txt', 'csv', 'json', 'html', 'pdf'], true)
                || ($path === 'docs/railspike/LICENSE')
                || (str_starts_with($path, 'docs/examples/') && in_array($extension, ['conf', 'example'], true))))) {
            return null;
        }

        return $path;
    }

    public static function routeForPath(string $sourcePath): ?string
    {
        if (self::normalizePath($sourcePath) !== $sourcePath) {
            return null;
        }

        $relativePath = in_array($sourcePath, self::ROOT_DOCUMENTS, true)
            ? '_project/' . $sourcePath : substr($sourcePath, strlen(self::DOCUMENT_ROOT . '/'));

        return '/docs/' . implode('/', array_map(rawurlencode(...), explode('/', $relativePath)));
    }

    public static function resolvePath(string $projectRoot, string $encodedPath): ?string
    {
        $resolvedPath = self::resolveSource($projectRoot, $encodedPath);
        if ($resolvedPath === null) {
            return null;
        }
        $size = filesize($resolvedPath);
        return $size !== false && $size <= self::MAX_DOCUMENT_BYTES ? $resolvedPath : null;
    }
}
