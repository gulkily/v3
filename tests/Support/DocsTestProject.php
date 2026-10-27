<?php

declare(strict_types=1);

require_once __DIR__ . '/ImportTestWorkspace.php';

/** Isolated real application with a small docs inventory for publication-flow regressions. */
final class DocsTestProject
{
    public static function create(ImportTestWorkspace $workspace): string
    {
        $root = $workspace->root . '/application';
        mkdir($root);
        foreach (['src', 'scripts', 'templates', 'public', 'autoload.php', 'v3'] as $path) {
            [$code, $output] = $workspace->command(['cp', '-R', dirname(__DIR__, 2) . '/' . $path, $root . '/' . $path]);
            if ($code !== 0) {
                throw new RuntimeException($output);
            }
        }
        $workspace->put($root . '/README.md', "# Project\n\nProject guide.\n");
        $workspace->put($root . '/docs/guide.md', "# Guide\n\n[Project](../README.md).\n");
        return $root;
    }
}
