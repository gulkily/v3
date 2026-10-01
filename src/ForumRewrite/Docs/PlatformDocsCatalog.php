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
                'title' => 'Extension and Improvement Cookbook',
                'category' => 'Extending the Platform',
                'path' => 'docs/examples/extension_improvement_cookbook.md',
                'description' => 'Worked patterns for safely extending the site, including agentic workflows.',
            ],
            [
                'title' => 'Agent Reply Analyze/Publish Contract',
                'category' => 'Extending the Platform',
                'path' => 'docs/specs/agent_reply_one_step_analyze_publish_contract_v1.md',
                'description' => 'The durable contract for bounded agent analysis and guarded publication.',
            ],
            [
                'title' => 'Theme Development Guide',
                'category' => 'Architecture',
                'path' => 'docs/runbooks/theme_development_guide.md',
                'description' => 'How themes fit into the application and how to add one safely.',
            ],
            [
                'title' => 'v3 CLI Reference',
                'category' => 'CLI Reference',
                'path' => 'docs/reference/v3_cli.md',
                'description' => 'Commands, options, and error-handling conventions for the v3 command-line interface.',
            ],
            [
                'title' => 'Production Deploy Runbook',
                'category' => 'Operations',
                'path' => 'docs/runbooks/production_deploy.md',
                'description' => 'The intended deployment model and shared-host production requirements.',
            ],
            [
                'title' => 'Operator Recovery Runbook',
                'category' => 'Operations',
                'path' => 'docs/runbooks/operator_recovery.md',
                'description' => 'How to inspect and recover production read-model and worker health.',
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
