<?php

declare(strict_types=1);

namespace ForumRewrite\Host;

use RuntimeException;

final class StaticArtifactReleasePublisher
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
        private readonly string $staticHtmlRoot,
    ) {
    }

    public function build(string $databasePath, ?\Closure $progressReporter = null): string
    {
        if (!is_dir($this->staticHtmlRoot) && !mkdir($this->staticHtmlRoot, 0777, true) && !is_dir($this->staticHtmlRoot)) {
            throw new RuntimeException('Unable to create static artifact root: ' . $this->staticHtmlRoot);
        }

        $token = \ForumRewrite\Retraction\PublicationGuard::begin($databasePath, $this->repositoryRoot);
        $candidatePath = $this->staticHtmlRoot . '/.candidate-' . bin2hex(random_bytes(8));
        try {
            (new StaticArtifactBuilder(
                $this->projectRoot,
                $this->repositoryRoot,
                $databasePath,
                $candidatePath,
                $progressReporter,
            ))->buildFromReadModel();

            $releaseDirectory = $this->staticHtmlRoot . '/releases';
            if (!is_dir($releaseDirectory) && !mkdir($releaseDirectory, 0777, true) && !is_dir($releaseDirectory)) {
                throw new RuntimeException('Unable to create static artifact release directory.');
            }

            $releasePath = $releaseDirectory . '/release-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
            \ForumRewrite\Retraction\PublicationGuard::publish($token, $releasePath, static function () use ($candidatePath, $releasePath): void {
                if (!rename($candidatePath, $releasePath)) throw new RuntimeException('Unable to finalize static artifact release.');
            });
            return $releasePath;
        } catch (\Throwable $throwable) {
            $this->removeDirectory($candidatePath);
            throw $throwable;
        }
    }

    public function activate(string $releasePath): void
    {
        $releaseDirectory = $this->staticHtmlRoot . '/releases/';
        if (!str_starts_with($releasePath, $releaseDirectory) || !is_dir($releasePath)) {
            throw new RuntimeException('Static artifact release must be an existing release under the static root.');
        }

        $pendingLink = $this->staticHtmlRoot . '/.current-' . bin2hex(random_bytes(8));
        $relativeReleasePath = 'releases/' . basename($releasePath);
        if (!symlink($relativeReleasePath, $pendingLink)) {
            throw new RuntimeException('Unable to create pending static artifact release link.');
        }

        try {
            $generation = @file_get_contents($releasePath . '.retraction-generation');
            $token = ['root' => $this->repositoryRoot, 'generation' => $generation === false ? '' : trim($generation)];
            \ForumRewrite\Retraction\PublicationGuard::publish($token, $this->staticHtmlRoot . '/current', function () use ($pendingLink): void {
                if (!rename($pendingLink, $this->staticHtmlRoot . '/current')) throw new RuntimeException('Unable to activate static release.');
            });
        } finally { if (is_link($pendingLink)) @unlink($pendingLink); }
    }

    /**
     * Starts from the active complete release, then refreshes only shared
     * routes. This deliberately does not rebuild the read model or render
     * every tag, thread, post, and profile page.
     */
    public function buildSharedRefresh(string $databasePath, ?\Closure $progressReporter = null): string
    {
        $activeReleasePath = realpath($this->staticHtmlRoot . '/current');
        if ($activeReleasePath === false || !is_dir($activeReleasePath)) {
            throw new RuntimeException('Shared static refresh requires an active complete static release. Run ./v3 build-static first.');
        }

        $token = \ForumRewrite\Retraction\PublicationGuard::begin($databasePath, $this->repositoryRoot);
        if (!is_file($activeReleasePath . '.retraction-generation') || trim((string) file_get_contents($activeReleasePath . '.retraction-generation')) !== $token['generation']) throw new RuntimeException('Shared refresh requires a full build after retraction.');

        $candidatePath = $this->staticHtmlRoot . '/.candidate-' . bin2hex(random_bytes(8));
        try {
            \ForumRewrite\Retraction\PublicationGuard::withLock($this->repositoryRoot, function () use ($token, $activeReleasePath, $candidatePath): void {
                if (\ForumRewrite\Retraction\PublicationGuard::generation($this->repositoryRoot) !== $token['generation']) throw new RuntimeException('Retraction changed before shared refresh.');
                $this->copyDirectory($activeReleasePath, $candidatePath);
            });
            (new StaticArtifactBuilder(
                $this->projectRoot,
                $this->repositoryRoot,
                $databasePath,
                $candidatePath,
                $progressReporter,
            ))->refreshSharedPagesFromReadModel();

            $releaseDirectory = $this->staticHtmlRoot . '/releases';
            if (!is_dir($releaseDirectory) && !mkdir($releaseDirectory, 0777, true) && !is_dir($releaseDirectory)) {
                throw new RuntimeException('Unable to create static artifact release directory.');
            }

            $releasePath = $releaseDirectory . '/release-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
            \ForumRewrite\Retraction\PublicationGuard::publish($token, $releasePath, static function () use ($candidatePath, $releasePath): void {
                if (!rename($candidatePath, $releasePath)) throw new RuntimeException('Unable to finalize static artifact release.');
            });
            return $releasePath;
        } catch (\Throwable $throwable) {
            $this->removeDirectory($candidatePath);
            throw $throwable;
        }
    }

    private function copyDirectory(string $sourcePath, string $targetPath): void
    {
        if (!mkdir($targetPath, 0777, true) && !is_dir($targetPath)) {
            throw new RuntimeException('Unable to create static refresh candidate directory.');
        }

        $entries = scandir($sourcePath);
        if ($entries === false) {
            throw new RuntimeException('Unable to read active static release.');
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..' || $entry === '.locks') {
                continue;
            }

            $sourceEntry = $sourcePath . '/' . $entry;
            $targetEntry = $targetPath . '/' . $entry;
            if (is_dir($sourceEntry) && !is_link($sourceEntry)) {
                $this->copyDirectory($sourceEntry, $targetEntry);
                continue;
            }

            if (!link($sourceEntry, $targetEntry) && !copy($sourceEntry, $targetEntry)) {
                throw new RuntimeException('Unable to copy static artifact into refresh candidate: ' . $entry);
            }
        }
    }

    private function removeDirectory(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }

        $entries = scandir($path);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $entryPath = $path . '/' . $entry;
            if (is_dir($entryPath) && !is_link($entryPath)) {
                $this->removeDirectory($entryPath);
            } else {
                @unlink($entryPath);
            }
        }

        @rmdir($path);
    }
}
