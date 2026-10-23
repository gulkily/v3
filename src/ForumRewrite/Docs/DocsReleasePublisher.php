<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

use ForumRewrite\Support\ExecutionLock;
use RuntimeException;

final class DocsReleasePublisher
{
    private readonly DocsReleaseLocator $locator;

    public function __construct(private readonly string $projectRoot, private readonly string $repositoryRoot, string $staticHtmlRoot)
    {
        $this->locator = new DocsReleaseLocator($staticHtmlRoot);
    }

    public function publish(?\Closure $progress = null): string
    {
        $root = $this->locator->root();
        foreach ([$root, $root . '/releases'] as $directory) {
            if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) {
                throw new RuntimeException('Docs publication storage must be a directory without symlinks.');
            }
            if (!is_dir($directory) && !mkdir($directory, 0777, true) && !is_dir($directory)) {
                throw new RuntimeException('Cannot create docs storage: ' . $directory);
            }
        }
        if (is_link($root . '/.publish.lock')) {
            throw new RuntimeException('Unsafe docs publication lock.');
        }
        return (new ExecutionLock($root . '/.publish.lock'))->withExclusiveLock(function () use ($root, $progress): string {
            $publication = new DocsPublication($this->projectRoot, $this->repositoryRoot, independent: true);
            $signature = $publication->inputSignature();
            $candidate = $root . '/.candidate-' . bin2hex(random_bytes(8));
            $release = null;
            $pending = $root . '/.current-' . bin2hex(random_bytes(8));
            try {
                $active = $this->locator->active();
                if ($active === null || !$publication->reuseInto($active['root'], $candidate, $signature, $signature)) {
                    (new DocsArtifactBuilder($this->projectRoot, $this->repositoryRoot, $candidate, $signature, $progress))->build();
                    $publication->recordPublication($candidate, $signature, $signature);
                } else {
                    ($progress)?->__invoke('Reusing documentation (' . $active['documents'] . ' documents).');
                }
                $count = count(PlatformDocsCatalog::inventory($this->projectRoot, fresh: true));
                $metadata = json_encode(['format' => 1, 'version' => $signature, 'documents' => $count], JSON_THROW_ON_ERROR);
                if (file_put_contents($candidate . '/' . DocsReleaseLocator::METADATA, $metadata) !== strlen($metadata)) {
                    throw new RuntimeException('Cannot record docs release.');
                }
                $publication->assertInputs($signature, $signature);
                $release = $root . '/releases/docs-' . gmdate('YmdHis') . '-' . bin2hex(random_bytes(4));
                if (!rename($candidate, $release)) {
                    throw new RuntimeException('Cannot finalize docs release.');
                }
                $publication->assertInputs($signature, $signature);
                if (!symlink('releases/' . basename($release), $pending) || !rename($pending, $root . '/current')) {
                    throw new RuntimeException('Cannot activate docs release.');
                }
                return $release;
            } catch (\Throwable $error) {
                $this->remove($candidate);
                if ($release !== null && (realpath($root . '/current') ?: null) !== $release) {
                    $this->remove($release);
                }
                throw $error;
            } finally {
                if (is_link($pending)) {
                    unlink($pending);
                }
            }
        });
    }

    private function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->remove($path . '/' . $entry);
                }
            }
            @rmdir($path);
        } elseif (file_exists($path) || is_link($path)) {
            @unlink($path);
        }
    }
}
