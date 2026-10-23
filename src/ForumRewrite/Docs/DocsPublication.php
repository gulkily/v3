<?php

declare(strict_types=1);

namespace ForumRewrite\Docs;

use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Host\AssetFingerprint;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\Retraction\PublicationGuard;
use ForumRewrite\Scoring\FastmodMetaPublication;
use ForumRewrite\View\FaviconRegistry;
use RuntimeException;

/** Compatibility and integrity evidence for the complete static documentation library. */
final class DocsPublication
{
    private const FORMAT = 1;
    public const MANIFEST = '.docs-publication.json';

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
            'retractions' => PublicationGuard::generation($this->repositoryRoot),
            'meta' => FastmodMetaPublication::token($this->projectRoot)['generation'],
        ], JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE));
    }

    public function version(): string
    {
        return ReadModelMetadata::repositoryHead($this->repositoryRoot);
    }

    /** Record only complete output belonging to the inputs captured before rendering. */
    public function recordPublication(string $candidateRoot, ?string $signature = null, ?string $version = null): void
    {
        $signature ??= $this->inputSignature();
        $version ??= $this->version();
        $manifest = $this->inspect($candidateRoot, $signature, $version);
        $this->assertInputs($signature, $version);
        $this->write($candidateRoot, self::MANIFEST, json_encode($manifest, JSON_THROW_ON_ERROR));
    }

    /** A false result requests a complete docs-owned rebuild by the caller. */
    public function reuseInto(string $sourceRoot, string $candidateRoot, ?string $signature = null, ?string $version = null): bool
    {
        if (!$this->isReusableContext() || is_link($candidateRoot) || realpath($sourceRoot) === realpath($candidateRoot)) {
            return false;
        }
        try {
            $signature ??= $this->inputSignature();
            $version ??= $this->version();
            $manifest = json_decode($this->read($sourceRoot, self::MANIFEST), true, flags: JSON_THROW_ON_ERROR);
            if (!is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT
                || ($manifest['input'] ?? null) !== $signature || !is_string($manifest['version'] ?? null)) {
                return false;
            }
            // Reconstruct the expected inventory/assets instead of trusting paths from JSON.
            $validated = $this->inspect($sourceRoot, $signature, $manifest['version']);
            if ($validated !== $manifest) {
                return false;
            }
            $this->assertInputs($signature, $version);
            $this->clearOwned($candidateRoot);
            foreach (['files', 'assets'] as $group) {
                foreach ($validated[$group] as $path => $hash) {
                    $contents = $this->read($sourceRoot, $path);
                    if (!hash_equals($hash, hash('sha256', $contents))) {
                        throw new RuntimeException('Documentation output changed during reuse.');
                    }
                    if ($group === 'files' && str_ends_with($path, '.html')) {
                        $contents = $this->refreshMarker($contents, $manifest['version'], $version);
                    }
                    $this->write($candidateRoot, $path, $contents);
                }
            }
            $this->recordPublication($candidateRoot, $signature, $version);
            return true;
        } catch (\Throwable) {
            // Reuse is an optimization. Rendering or its normal failure/retry handles fallback.
            $this->clearOwned($candidateRoot);
            return false;
        }
    }

    /** Remove only docs-owned outputs, without following seeded symlinks or hard links. */
    public function clearOwned(string $root): void
    {
        if (is_link($root)) {
            throw new RuntimeException('Unsafe documentation candidate root.');
        }
        foreach (['docs', 'docs.html', 'docs.html.retraction-generation', 'docs.html.fastmod-generation', self::MANIFEST] as $path) {
            $this->remove($root . '/' . $path);
        }
    }

    private function inspect(string $root, string $signature, string $version): array
    {
        if (!preg_match('/^(?:[a-f0-9]{40,64}|no-git)$/D', $version)) {
            throw new RuntimeException('Uncertain documentation repository version.');
        }
        $html = ['docs.html', 'docs/index.html', 'docs/_missing.html', 'docs/_search.html'];
        $required = ['docs/_search.json'];
        $inventory = PlatformDocsCatalog::inventory($this->projectRoot, fresh: true);
        foreach ($inventory as $entry) {
            $html[] = ltrim($entry['href'], '/') . '.html';
            $required[] = 'docs/_download/' . $entry['path'] . '.bin';
        }
        foreach (PlatformDocsCatalog::browseQueries($inventory, PlatformDocsCatalog::PAGE_SIZE) as $query) {
            $html[] = ltrim(PlatformDocsCatalog::browsePath($query), '/');
        }
        $required = array_fill_keys(array_merge($html, $required), true);
        $allowed = $required;
        foreach ($html as $path) {
            $allowed[$path . '.retraction-generation'] = true;
            $allowed[$path . '.fastmod-generation'] = true;
        }
        $paths = $this->ownedFiles($root);
        if (array_diff_key($required, array_fill_keys($paths, true)) !== []
            || array_diff_key(array_fill_keys($paths, true), $allowed) !== []) {
            throw new RuntimeException('Incomplete or unexpected documentation output.');
        }
        $files = $assets = [];
        $notifications = $this->flags()->isEnabled(FeatureFlagRegistry::APP_VERSION_NOTIFICATION);
        foreach ($paths as $path) {
            $contents = $this->read($root, $path);
            $files[$path] = hash('sha256', $contents);
            if (isset($required[$path]) && str_ends_with($path, '.html')) {
                $this->validateMarker($contents, $version, $notifications);
                // Match the builder's markup-only asset boundary; prose examples are inert.
                preg_match_all('~<script\b[^>]*>.*?</script>|<[a-z][^>]*>~is', $contents, $markup);
                preg_match_all('#/assets/[A-Za-z0-9_./-]+\.[a-f0-9]{12}\.[A-Za-z0-9]+#', implode(' ', $markup[0]), $references);
                foreach ($references[0] as $reference) {
                    $source = AssetFingerprint::sourcePathForFingerprint($this->projectRoot . '/public', $reference);
                    if ($source === null) {
                        throw new RuntimeException('Documentation references an unknown asset.');
                    }
                    $assets[ltrim($reference, '/')] = hash_file('sha256', $source);
                }
            }
        }
        foreach (array_unique(['/favicon.ico', '/favicon.gif', FaviconRegistry::resolve(SiteProfileRegistry::active())]) as $favicon) {
            if (is_file($this->projectRoot . '/public' . $favicon)) {
                $assets[ltrim($favicon, '/')] = hash_file('sha256', $this->projectRoot . '/public' . $favicon);
            }
        }
        foreach ($assets as $path => $expected) {
            if (hash('sha256', $this->read($root, $path)) !== $expected) {
                throw new RuntimeException('Missing or damaged documentation asset: ' . $path);
            }
        }
        ksort($files);
        ksort($assets);
        return ['format' => self::FORMAT, 'input' => $signature, 'version' => $version,
            'notifications' => $notifications, 'files' => $files, 'assets' => $assets];
    }

    private function ownedFiles(string $root): array
    {
        $paths = ['docs.html'];
        foreach (['retraction-generation', 'fastmod-generation'] as $suffix) {
            if (file_exists($root . '/docs.html.' . $suffix) || is_link($root . '/docs.html.' . $suffix)) {
                $paths[] = 'docs.html.' . $suffix;
            }
        }
        if (is_link($root . '/docs') || !is_dir($root . '/docs')) {
            throw new RuntimeException('Missing or unsafe documentation directory.');
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($root . '/docs', \FilesystemIterator::SKIP_DOTS),
        );
        foreach ($iterator as $file) {
            if (!$file->isFile() || $file->isLink()) {
                throw new RuntimeException('Unsafe documentation artifact.');
            }
            $paths[] = substr($file->getPathname(), strlen(rtrim($root, '/')) + 1);
        }
        sort($paths);
        return $paths;
    }

    private function validateMarker(string $contents, string $version, bool $enabled): void
    {
        preg_match_all('~<meta\b[^>]*\bname\s*=\s*["\']?app-version(?:-endpoint)?(?=["\'\s/>])[^>]*>~i', $contents, $markers);
        if (count($markers[0]) !== ($enabled ? 2 : 0)
            || ($enabled && (substr_count($contents, '<meta name="app-version" content="' . $version . '">') !== 1
                || substr_count($contents, '<meta name="app-version-endpoint" content="/api/version">') !== 1))) {
            throw new RuntimeException('Missing, malformed or unexpected documentation version marker.');
        }
    }

    private function refreshMarker(string $contents, string $previous, string $current): string
    {
        $enabled = $this->flags()->isEnabled(FeatureFlagRegistry::APP_VERSION_NOTIFICATION);
        $this->validateMarker($contents, $previous, $enabled);
        if ($enabled) {
            $contents = str_replace('<meta name="app-version" content="' . $previous . '">',
                '<meta name="app-version" content="' . $current . '">', $contents);
        }
        $this->validateMarker($contents, $current, $enabled);
        return $contents;
    }

    public function assertInputs(string $signature, string $version): void
    {
        if ($this->inputSignature() !== $signature || $this->version() !== $version) {
            throw new RuntimeException('Documentation inputs changed during publication; retry the build.');
        }
    }

    public function assertPublicationInputs(string $root): void
    {
        $manifest = json_decode($this->read($root, self::MANIFEST), true, flags: JSON_THROW_ON_ERROR);
        if (!is_array($manifest) || ($manifest['format'] ?? null) !== self::FORMAT
            || !is_string($manifest['input'] ?? null) || !is_string($manifest['version'] ?? null)) {
            throw new RuntimeException('Invalid documentation publication evidence; rebuild and retry.');
        }
        $this->assertInputs($manifest['input'], $manifest['version']);
    }

    private function read(string $root, string $relative): string
    {
        $base = realpath($root);
        $path = $base === false ? false : realpath($base . '/' . $relative);
        if ($base === false || $path !== $base . '/' . $relative || !is_file($path) || is_link($path)) {
            throw new RuntimeException('Missing or unsafe documentation output: ' . $relative);
        }
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Cannot read documentation output: ' . $relative);
        }
        return $contents;
    }

    private function write(string $root, string $relative, string $contents): void
    {
        if (is_link($root)) {
            throw new RuntimeException('Unsafe documentation candidate root.');
        }
        if (!is_dir($root) && !mkdir($root, 0777, true) && !is_dir($root)) {
            throw new RuntimeException('Cannot create documentation candidate.');
        }
        $base = realpath($root);
        $segments = explode('/', $relative);
        if ($base === false || array_intersect($segments, ['', '.', '..']) !== []) {
            throw new RuntimeException('Unsafe documentation destination.');
        }
        $filename = array_pop($segments);
        $directory = $base;
        foreach ($segments as $segment) {
            $directory .= '/' . $segment;
            if (is_link($directory) || (file_exists($directory) && !is_dir($directory))) {
                throw new RuntimeException('Unsafe documentation destination directory.');
            }
            if (!is_dir($directory) && !mkdir($directory) && !is_dir($directory)) {
                throw new RuntimeException('Cannot create documentation destination directory.');
            }
        }
        $temporary = tempnam($directory, '.docs-');
        if ($temporary === false) {
            throw new RuntimeException('Cannot create documentation temporary file.');
        }
        try {
            if (file_put_contents($temporary, $contents) !== strlen($contents)
                || !rename($temporary, $directory . '/' . $filename)) {
                throw new RuntimeException('Cannot publish documentation output.');
            }
        } finally {
            if (is_file($temporary)) {
                unlink($temporary);
            }
        }
    }

    private function remove(string $path): void
    {
        if (is_dir($path) && !is_link($path)) {
            foreach (scandir($path) ?: [] as $entry) {
                if ($entry !== '.' && $entry !== '..') {
                    $this->remove($path . '/' . $entry);
                }
            }
            if (!rmdir($path)) {
                throw new RuntimeException('Cannot remove stale documentation directory.');
            }
        } elseif ((file_exists($path) || is_link($path)) && !unlink($path)) {
            throw new RuntimeException('Cannot remove stale documentation output.');
        }
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
