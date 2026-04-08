<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class LocalRepositoryBootstrap
{
    public static function defaultRepositoryRoot(string $projectRoot): string
    {
        $localRepositoryRoot = $projectRoot . '/state/local_repository';
        if (is_dir($localRepositoryRoot . '/records') && is_dir($localRepositoryRoot . '/.git')) {
            return $localRepositoryRoot;
        }

        self::initializeLocalRepository($projectRoot, $localRepositoryRoot);

        return $localRepositoryRoot;
    }

    public static function initializeLocalRepository(string $projectRoot, string $target): void
    {
        $source = $projectRoot . '/tests/fixtures/parity_minimal_v1';

        if (is_dir($target . '/records') && is_dir($target . '/.git')) {
            return;
        }

        if (!is_dir($source)) {
            throw new RuntimeException('Fixture repository is missing: ' . $source);
        }

        if (!is_dir($target) && !mkdir($target, 0777, true) && !is_dir($target)) {
            throw new RuntimeException('Unable to create local repository directory: ' . $target);
        }

        if (!is_dir($target . '/records')) {
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $item) {
                $targetPath = $target . '/' . $iterator->getSubPathName();
                if ($item->isDir()) {
                    if (!is_dir($targetPath) && !mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
                        throw new RuntimeException('Unable to create directory: ' . $targetPath);
                    }

                    continue;
                }

                if (!copy($item->getPathname(), $targetPath)) {
                    throw new RuntimeException('Unable to copy fixture file: ' . $item->getPathname());
                }
            }
        }

        self::initializeGitRepository($target);
    }

    public static function initializeGitRepository(string $target): void
    {
        if (is_dir($target . '/.git')) {
            return;
        }

        $commands = [
            'git init',
            'git config user.name "Forum Rewrite"',
            'git config user.email "forum-rewrite@example.invalid"',
            'git add .',
            'git commit -m "Initialize local repository"',
        ];

        foreach ($commands as $command) {
            $output = [];
            $exitCode = 0;
            exec('cd ' . escapeshellarg($target) . ' && ' . $command . ' 2>&1', $output, $exitCode);
            if ($exitCode !== 0) {
                throw new RuntimeException('Failed to initialize git repository: ' . implode("\n", $output));
            }
        }
    }
}
