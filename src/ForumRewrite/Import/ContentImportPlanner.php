<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\PostRecordParser;
use FilesystemIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;
use Throwable;

/** Builds a byte-preserving, additive public-content merge without mutating either repository. */
final class ContentImportPlanner
{
    public static function files(string $root): array
    {
        $paths = [];
        if (!is_dir($root . '/records')) {
            throw new RuntimeException('Repository is missing records/.');
        }
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/records', FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->isFile() && !$file->isLink()) {
                $paths[] = substr($file->getPathname(), strlen($root) + 1);
            }
        }
        sort($paths);
        return $paths;
    }

    public function plan(string $source, string $destination): array
    {
        $target = [];
        foreach (self::files($destination) as $path) {
            $key = ArchiveRecordCatalog::identityKey($destination, $path);
            if ($key !== null) {
                $target[$key][] = $path;
            }
        }
        $entries = [];
        $byKey = [];
        foreach (self::files($source) as $path) {
            $entry = $this->describe($source, $path);
            $entry['hash'] = hash_file('sha256', $source . '/' . $path);
            if ($entry['state'] === 'import') {
                $key = $entry['key'];
                $existing = $target[$key] ?? [];
                if (file_exists($destination . '/' . $path)) {
                    $existing[] = $path;
                }
                if ($existing !== []) {
                    $entry['state'] = 'duplicate';
                    foreach (array_unique($existing) as $other) {
                        if (is_link($destination . '/' . $other) || !is_file($destination . '/' . $other)
                            || hash_file('sha256', $destination . '/' . $other) !== $entry['hash']) {
                            $entry['state'] = 'conflict';
                            $entry['reason'] = 'Existing path or canonical identity has different bytes';
                            break;
                        }
                    }
                }
                if (isset($byKey[$key])) {
                    $previous = $byKey[$key];
                    if ($entries[$previous]['hash'] === $entry['hash']) {
                        $entry['state'] = 'duplicate';
                    } else {
                        $entry['state'] = $entries[$previous]['state'] = 'conflict';
                        $entry['reason'] = $entries[$previous]['reason'] = 'Divergent source records share an identity';
                    }
                } else {
                    $byKey[$key] = $path;
                }
            }
            $entries[$path] = $entry;
        }
        // Records and their detached signatures are inseparable on conflicts; then
        // propagate rejected dependencies to a fixed point (bootstrap cycles are valid).
        do {
            $changed = false;
            foreach ($entries as $path => $entry) {
                if (!in_array($entry['state'], ['import', 'duplicate'], true)) {
                    continue;
                }
                $reason = null;
                foreach ($entry['dependencies'] as $dependency) {
                    $sourcePath = $byKey[$dependency] ?? null;
                    if ($sourcePath !== null) {
                        if (!in_array($entries[$sourcePath]['state'], ['import', 'duplicate'], true)) {
                            $reason = 'Rejected dependency: ' . $dependency;
                        }
                    } elseif (!isset($target[$dependency])) {
                        $reason = 'Missing dependency: ' . $dependency;
                    }
                }
                if (isset($entry['record'])) {
                    $parent = $entries[$entry['record']] ?? null;
                    if ($parent === null || !in_array($parent['state'], ['import', 'duplicate'], true)) {
                        $reason = 'Signature record is unavailable or rejected';
                    }
                } else {
                    foreach (['.asc', '.sig'] as $suffix) {
                        if (isset($entries[$path . $suffix]) && !in_array($entries[$path . $suffix]['state'], ['import', 'duplicate'], true)) {
                            $reason = 'Detached signature is rejected';
                        }
                    }
                }
                if ($reason !== null) {
                    $entries[$path]['state'] = 'invalid';
                    $entries[$path]['reason'] = $reason;
                    $changed = true;
                }
            }
        } while ($changed);
        return $entries;
    }

    private function describe(string $root, string $path): array
    {
        $entry = ['state' => 'import', 'reason' => '', 'key' => null, 'dependencies' => []];
        if (ArchiveRecordCatalog::isSignaturePath($path) && !ArchiveRecordCatalog::isRecordPath($path)) {
            $record = substr($path, 0, -4);
            $parent = $this->describe($root, $record);
            return [...$parent, 'key' => $parent['key'] === null ? null : 'signature:' . $parent['key'] . ':' . substr($path, -4), 'record' => $record];
        }
        if (preg_match('#^records/(instance|approval-seeds|feature-flag-changes|private-messages|invitations)/#', $path)) {
            return [...$entry, 'state' => 'excluded', 'reason' => 'Instance authority, settings, or private data'];
        }
        if (!ArchiveRecordCatalog::isRecordPath($path)) {
            return [...$entry, 'state' => 'unsupported', 'reason' => 'Unsupported record family or path'];
        }
        try {
            $repository = new CanonicalRecordRepository($root);
            if (str_starts_with($path, 'records/posts/')) {
                // Do not synthesize Created-At from foreign Git history or rewrite signed bytes.
                $contents = file_get_contents($root . '/' . $path);
                $post = (new PostRecordParser())->parse($contents === false ? '' : $contents);
                $repository->loadPost($path); // Includes canonical path validation.
                if (array_intersect($post->boardTags, ['approval', 'invitation', 'private', 'private-message', 'pm']) !== []) {
                    return [...$entry, 'state' => 'excluded', 'reason' => 'Authority or private post'];
                }
                foreach ([$post->threadId, $post->parentId] as $id) {
                    if ($id !== null) {
                        $entry['dependencies'][] = 'post:' . $id;
                    }
                }
                $author = $post->authorIdentityId;
            } elseif (str_starts_with($path, 'records/identity/')) {
                $record = $repository->loadIdentity($path);
                $entry['dependencies'] = ['post:' . $record->bootstrapByPost, 'post:' . $record->bootstrapByThread];
            } elseif (str_starts_with($path, 'records/public-keys/')) {
                $repository->loadPublicKey($path);
            } else {
                $record = match (explode('/', $path)[1]) {
                    'thread-subjects' => $repository->loadThreadSubject($path),
                    'thread-labels' => $repository->loadThreadLabel($path),
                    'post-reactions' => $repository->loadPostReaction($path),
                };
                $entry['dependencies'][] = 'post:' . ($record->threadId ?? $record->postId);
                $author = $record->authorIdentityId;
            }
            if (isset($author)) {
                $entry['dependencies'][] = 'identity:' . $author;
            }
            $entry['key'] = ArchiveRecordCatalog::identityKey($root, $path);
            if ($entry['key'] === null) {
                throw new RuntimeException('Unrecognized canonical identity');
            }
        } catch (Throwable $error) {
            $entry['state'] = 'invalid';
            $entry['reason'] = $error->getMessage();
        }
        return $entry;
    }
}
