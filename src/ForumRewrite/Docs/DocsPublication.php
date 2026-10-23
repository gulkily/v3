<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use RuntimeException;

/** Compatibility and integrity evidence for the complete static documentation library. */
final class DocsPublication
{
    private const FORMAT = 1;

    public function __construct(
        private readonly string $projectRoot,
        private readonly string $repositoryRoot,
    ) {
    }

    public function isReusableContext(): bool
    {
        // Authenticated/viewer-derived chrome is outside anonymous publication evidence.
        return ($_COOKIE ?? []) === [] && ($_SESSION ?? []) === [];
    }

    public function inputSignature(): string
    {
        if (!$this->isReusableContext()) {
            throw new RuntimeException('Documentation reuse requires an anonymous publication context.');
        }
        $files = [];
        foreach (['src', 'templates', 'public', 'autoload.php'] as $path) {
            $this->fingerprint($this->projectRoot . '/' . $path, $path, $files);
        }
        ksort($files);
        $flags = [];
        foreach ($this->flags()->all() as $flag) {
            $flags[$flag->definition->key] = $flag->effectiveValue;
        }
        ksort($flags);
        // Fresh reads must bypass a renderer's scoped inventory to detect mid-build changes.
        $inventory = PlatformDocsCatalog::inventory($this->projectRoot, fresh: true);
        $directories = [];
        $docs = $this->projectRoot . '/docs';
        if (is_dir($docs) && !is_link($docs)) {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($docs, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST,
            );
            foreach ($iterator as $file) {
                if ($file->isDir() || $file->isLink()) {
                    // Empty directories and directory links can change rendered relative links.
                    $directories[substr($file->getPathname(), strlen($docs))] = $file->isLink()
                        ? ['link' => readlink($file->getPathname()), 'resolved' => realpath($file->getPathname())]
                        : 'directory';
                }
            }
        }
        ksort($directories);
        return hash('sha256', json_encode([
            'format' => self::FORMAT,
            'php' => PHP_VERSION,
            'documents' => $inventory,
            'directories' => $directories,
            'files' => $files,
            'profile' => SiteProfileRegistry::active(),
            'flags' => $flags,
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    private function flags(): FeatureFlagEvaluator
    {
        return FeatureFlagEvaluator::forApplication($this->repositoryRoot, $this->projectRoot);
    }

    private function fingerprint(string $path, string $relative, array &$files): void
    {
        if (is_link($path)) {
            throw new RuntimeException('Cannot establish documentation rendering inputs through a symlink: ' . $relative);
        }
        if (is_dir($path)) {
            $entries = scandir($path);
            if ($entries === false) {
                throw new RuntimeException('Cannot inspect documentation rendering input: ' . $relative);
            }
            foreach ($entries as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->fingerprint($path . '/' . $entry, $relative . '/' . $entry, $files);
                }
            }
            return;
        }
        $hash = is_file($path) ? hash_file('sha256', $path) : null;
        if ($hash === false) {
            throw new RuntimeException('Cannot read documentation rendering input: ' . $relative);
        }
        $files[$relative] = $hash;
    }
}
