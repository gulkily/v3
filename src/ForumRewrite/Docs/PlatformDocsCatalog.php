<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

final class PlatformDocsCatalog
{
    public const DOCUMENT_ROOT = 'docs';
    public const MAX_DOCUMENT_BYTES = 1048576;
    public const ROOT_DOCUMENTS = ['README.md', 'CONTRIBUTING.md', 'AGENTS.md', 'BLESSING.md'];

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
                'title' => 'Extension and Improvement Cookbook',
                'category' => 'Extending the Platform',
                'path' => 'docs/examples/extension_improvement_cookbook.md',
                'description' => 'Worked examples of extending the site, including with coding agents.',
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
                'title' => 'v3 CLI Reference',
                'category' => 'CLI Reference',
                'path' => 'docs/reference/v3_cli.md',
                'description' => 'Commands, options, and error handling for the v3 CLI.',
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
        $entries = [];
        foreach ($paths as $path) {
            $resolved = self::resolveSource($projectRoot, $path);
            if ($resolved === null) {
                continue;
            }
            $size = filesize($resolved);
            $readable = $size !== false && $size <= self::MAX_DOCUMENT_BYTES;
            $text = $readable ? (string) file_get_contents($resolved) : '';
            preg_match('/^#\s+(.+)$/m', $text, $heading);
            $group = str_starts_with($path, 'docs/plans/') ? 'Plans and history'
                : (str_starts_with($path, 'docs/railspike/') ? 'Workflow' : 'Current guidance');
            $entries[] = [
                'path' => $path,
                'title' => $curated[$path]['title'] ?? trim($heading[1] ?? basename($path)),
                'category' => $curated[$path]['category'] ?? (str_contains($path, '/') ? explode('/', $path)[1] : 'Project'),
                'description' => $curated[$path]['description'] ?? '',
                'folder' => dirname($path) === '.' ? 'Project' : dirname($path),
                'group' => $group,
                'href' => self::routeForPath($path),
                'available' => $readable,
                'text' => $text,
            ];
        }
        usort($entries, static fn (array $a, array $b): int =>
            (($a['group'] === 'Current guidance' ? 0 : 1) <=> ($b['group'] === 'Current guidance' ? 0 : 1))
            ?: strnatcasecmp($a['path'], $b['path']));
        return $entries;
    }

    public static function search(array $inventory, array $query): array
    {
        $value = static fn (string $key): string => is_string($query[$key] ?? null) ? $query[$key] : '';
        $terms = preg_split('/\s+/u', mb_strtolower(trim($value('q')), 'UTF-8'), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        return array_values(array_filter($inventory, static function (array $entry) use ($value, $terms): bool {
            if (($value('folder') !== '' && $entry['folder'] !== $value('folder'))
                || ($value('category') !== '' && $entry['category'] !== $value('category'))
                || ($value('group') !== '' && $entry['group'] !== $value('group'))) {
                return false;
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
        if ($resolved === false || !is_file($resolved)) {
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
            if (!str_starts_with($decoded, '/docs/')) {
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
                if (count($segments) <= 1) {
                    return null;
                }
                array_pop($segments);
            } else {
                $segments[] = $segment;
            }
        }
        $target = implode('/', $segments);
        if (self::resolvePath($projectRoot, $target) === null) {
            return null;
        }
        if ($fragment !== null && self::resolveLink('#' . $fragment, null, $projectRoot) === null) {
            return null;
        }

        return self::routeForPath($target) . ($fragment === null ? '' : '#' . $fragment);
    }

    public static function normalizePath(string $encodedPath): ?string
    {
        $path = rawurldecode($encodedPath);
        $path = str_replace('\\', '/', $path);

        if ($path === '' || str_starts_with($path, '/') || str_contains($path, "\0")) {
            return null;
        }

        foreach (explode('/', $path) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return null;
            }
        }

        if (!in_array($path, self::ROOT_DOCUMENTS, true)
            && (!str_starts_with($path, self::DOCUMENT_ROOT . '/') || !str_ends_with($path, '.md'))) {
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
