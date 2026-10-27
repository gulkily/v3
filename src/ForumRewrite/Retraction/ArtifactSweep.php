<?php

declare(strict_types=1);
namespace ForumRewrite\Retraction;

use RuntimeException;

/** Repeatable traversal using ordinary file age; no per-artifact retraction metadata. */
final class ArtifactSweep
{
    private const FAMILIES = ['threads', 'posts', 'profiles', 'users', 'tags', 'activity', 'instance', 'tools', 'about', 'docs', 'latest', 'top', 'leetness', 'qdb', 'offline', 'releases', 'pages'];

    public static function run(array $roots, int $olderThan, bool $preview = false): array
    {
        $result = ['examined' => 0, 'affected' => 0, 'cleaned' => 0];
        foreach (array_unique($roots) as $root) {
            if (is_link($root)) throw new RuntimeException('Artifact root must not be a symlink.');
            if (!file_exists($root)) continue;
            if (!is_dir($root)) throw new RuntimeException('Artifact root must be a directory.');
            $locator = new \ForumRewrite\Docs\DocsReleaseLocator($root);
            if (!$preview) $locator->preserveLegacy();
            $pinned = $locator->legacy();
            $base = realpath($root);
            $walk = static function (string $directory, bool $top) use (&$walk, &$result, $preview, $olderThan, $pinned, $root, $base): void {
                foreach (new \FilesystemIterator($directory, \FilesystemIterator::SKIP_DOTS) as $file) {
                    $path = $file->getPathname(); $name = $file->getFilename();
                    if ($pinned !== null && \ForumRewrite\Docs\DocsReleaseLocator::ownsLegacyPath($pinned['root'], $base . substr($path, strlen($root)))) continue;
                    if (in_array($name, ['assets', '.locks', 'locks'], true)) continue;
                    if ($file->isLink()) {
                        if ($top && $name === 'current' && (int) lstat($path)['mtime'] <= $olderThan) {
                            ++$result['affected'];
                            if (!$preview && !unlink($path)) throw new RuntimeException('Cannot retire static release pointer.');
                            if (!$preview) ++$result['cleaned'];
                        }
                        continue; // Never traverse links, including in generated directories.
                    }
                    if ($file->isDir()) {
                        if (!$top || in_array($name, self::FAMILIES, true) || str_starts_with($name, '.candidate-')) $walk($path, false);
                        continue;
                    }
                    ++$result['examined'];
                    $legacyStamp = str_ends_with($name, '.retraction-generation');
                    $covered = str_ends_with($name, '.html') || str_starts_with($name, 'artifact-')
                        || (basename($directory) === 'offline' && preg_match('/^(snapshot|update|manifest)[.]/', $name));
                    if (!$legacyStamp && (!$covered || $file->getMTime() > $olderThan)) continue;
                    ++$result['affected'];
                    if (!$preview && !unlink($path)) throw new RuntimeException('Cannot retire generated artifact: ' . $path);
                    if (!$preview) ++$result['cleaned'];
                }
            };
            $walk($root, true);
        }
        return $result;
    }
}
