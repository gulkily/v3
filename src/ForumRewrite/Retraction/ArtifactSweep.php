<?php

declare(strict_types=1);
namespace ForumRewrite\Retraction;

use RuntimeException;

/** Resumable directory walk. Never follows a directory symlink or enters assets. */
final class ArtifactSweep
{
    private const FAMILIES = ['threads', 'posts', 'profiles', 'users', 'tags', 'activity', 'instance', 'tools', 'about', 'docs', 'latest', 'top', 'leetness', 'qdb', 'offline', 'releases'];

    public static function run(array $roots, string $generation, array $stack, int $limit, bool $preview = false): array
    {
        if ($stack === []) {
            foreach (array_unique($roots) as $root) {
                if (is_link($root)) throw new RuntimeException('Artifact root must not be a symlink.');
                if (file_exists($root) && !is_dir($root)) throw new RuntimeException('Artifact root is not a directory.');
                if (is_dir($root)) $stack[] = ['path' => rtrim($root, '/'), 'after' => '', 'top' => true];
            }
        }
        $examined = $cleaned = $affected = 0;
        $directories = [];
        while ($stack !== [] && $examined < max(1, $limit)) {
            $index = count($stack) - 1;
            $frame = $stack[$index];
            $allowed = false;
            foreach ($roots as $root) {
                $base = rtrim($root, '/');
                if ($frame['path'] === $base || str_starts_with($frame['path'], $base . '/')) {
                    if (is_link($base)) throw new RuntimeException('Artifact root must not be a symlink.');
                    $resolvedBase = realpath($base);
                    $resolvedFrame = realpath($frame['path']);
                    if ($resolvedFrame !== false && $resolvedFrame !== $resolvedBase . substr($frame['path'], strlen($base))) throw new RuntimeException('Artifact traversal crossed a symlink.');
                    $allowed = true;
                }
            }
            if (!$allowed || str_contains($frame['path'], '/..') || is_link($frame['path'])) throw new RuntimeException('Invalid artifact traversal state.');
            if (!is_dir($frame['path'])) { array_pop($stack); continue; }
            if (!isset($directories[$frame['path']])) {
                $names = scandir($frame['path']);
                if ($names === false) throw new RuntimeException('Cannot inspect artifact directory.');
                $directories[$frame['path']] = ['names' => array_values(array_filter($names, static fn (string $entry): bool => $entry !== '.' && $entry !== '..' && strcmp($entry, $frame['after']) > 0)), 'offset' => 0];
            }
            $directory = &$directories[$frame['path']];
            $name = $directory['names'][$directory['offset']++] ?? null;
            unset($directory);
            if ($name === null) { array_pop($stack); continue; }
            $stack[$index]['after'] = $name;
            ++$examined;
            $path = $frame['path'] . '/' . $name;
            if ($name === 'current' && $frame['top']) {
                if (is_link($path)) {
                    if (trim((string) @file_get_contents($path . '.retraction-generation')) !== $generation) {
                        ++$affected;
                        if (!$preview && !unlink($path)) throw new RuntimeException('Cannot retire release pointer.');
                        if (!$preview) ++$cleaned;
                    }
                    continue;
                }
                if (file_exists($path)) throw new RuntimeException('Current release pointer must be a symlink.');
            }
            $directoryAllowed = !$frame['top'] || in_array($name, self::FAMILIES, true) || str_starts_with($name, '.candidate-');
            if (is_dir($path)) {
                if (!$directoryAllowed || in_array($name, ['assets', '.locks'], true)) continue;
                if (is_link($path)) throw new RuntimeException('Artifact directory symlink is outside sweep policy.');
                $stack[] = ['path' => $path, 'after' => '', 'top' => false];
                continue;
            }
            $covered = str_ends_with($name, '.html') || str_starts_with($name, 'artifact-')
                || (basename($frame['path']) === 'offline' && preg_match('/^(snapshot|update|manifest)[.]/', $name));
            if (!$covered || str_ends_with($name, '.retraction-generation')) continue;
            if (trim((string) @file_get_contents($path . '.retraction-generation')) === $generation && !is_link($path)) continue;
            ++$affected;
            if (!$preview) {
                if ((file_exists($path) || is_link($path)) && !unlink($path)) throw new RuntimeException('Cannot remove stale artifact.');
                if (is_file($path . '.retraction-generation') && !unlink($path . '.retraction-generation')) throw new RuntimeException('Cannot retire artifact generation.');
                ++$cleaned;
            }
        }
        return compact('stack', 'examined', 'cleaned', 'affected');
    }
}
