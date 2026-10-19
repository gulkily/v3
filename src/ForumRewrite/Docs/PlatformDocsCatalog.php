<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

final class PlatformDocsCatalog
{
    public const DOCUMENT_ROOT = 'docs';
    public const MAX_DOCUMENT_BYTES = 1048576;

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

        if (!str_starts_with($path, self::DOCUMENT_ROOT . '/') || !str_ends_with($path, '.md')) {
            return null;
        }

        return $path;
    }

    public static function routeForPath(string $sourcePath): ?string
    {
        if (self::normalizePath($sourcePath) !== $sourcePath) {
            return null;
        }

        $relativePath = substr($sourcePath, strlen(self::DOCUMENT_ROOT . '/'));

        return '/docs/' . implode('/', array_map(rawurlencode(...), explode('/', $relativePath)));
    }

    public static function resolvePath(string $projectRoot, string $encodedPath): ?string
    {
        $path = self::normalizePath($encodedPath);
        $docsRoot = realpath(rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . self::DOCUMENT_ROOT);

        if ($path === null || $docsRoot === false) {
            return null;
        }

        $resolvedPath = realpath(rtrim($projectRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $path);
        $docsPrefix = rtrim($docsRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        if ($resolvedPath === false || !is_file($resolvedPath) || !str_starts_with($resolvedPath, $docsPrefix)) {
            return null;
        }

        $size = filesize($resolvedPath);

        return $size !== false && $size <= self::MAX_DOCUMENT_BYTES ? $resolvedPath : null;
    }
}
