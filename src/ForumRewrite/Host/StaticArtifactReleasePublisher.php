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

    public function build(string $databasePath): string
    {
        if (!is_dir($this->staticHtmlRoot) && !mkdir($this->staticHtmlRoot, 0777, true) && !is_dir($this->staticHtmlRoot)) {
            throw new RuntimeException('Unable to create static artifact root: ' . $this->staticHtmlRoot);
        }

        $candidatePath = $this->staticHtmlRoot . '/.candidate-' . bin2hex(random_bytes(8));
        try {
            (new StaticArtifactBuilder(
                $this->projectRoot,
                $this->repositoryRoot,
                $databasePath,
                $candidatePath,
            ))->buildFromReadModel();

            $releaseDirectory = $this->staticHtmlRoot . '/releases';
            if (!is_dir($releaseDirectory) && !mkdir($releaseDirectory, 0777, true) && !is_dir($releaseDirectory)) {
                throw new RuntimeException('Unable to create static artifact release directory.');
            }

            $releasePath = $releaseDirectory . '/release-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
            if (!rename($candidatePath, $releasePath)) {
                throw new RuntimeException('Unable to finalize static artifact release.');
            }

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

        if (!rename($pendingLink, $this->staticHtmlRoot . '/current')) {
            @unlink($pendingLink);
            throw new RuntimeException('Unable to activate static artifact release.');
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
