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
