<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LegacyPostTimestamp;
use ForumRewrite\Canonical\CanonicalRecordParseException;
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
                if (str_starts_with($key, 'post-timestamp:')) {
                    $metadata = LegacyPostTimestamp::read($source . '/' . $path);
                    foreach ($target['post:' . $metadata['post_id']] ?? [] as $postPath) {
                        $local = (new CanonicalRecordRepository($destination))->loadPost($postPath);
                        if ($local->createdAt !== $metadata['created_at']) {
                            $entry['state'] = 'conflict';
                            $entry['reason'] = 'Existing post has a different creation timestamp';
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
        // Rejected/excluded files still exist: retain their claimed identities so
        // dependents can explain the actual rejection instead of calling them absent.
        foreach ($entries as $path => $entry) {
            if ($entry['key'] !== null) { $byKey[$entry['key']] ??= $path; }
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
                $blockedBy = null;
                $state = 'invalid';
                foreach ($entry['dependencies'] as $dependency) {
                    $sourcePath = $byKey[$dependency] ?? null;
                    if ($sourcePath !== null) {
                        if (!in_array($entries[$sourcePath]['state'], ['import', 'duplicate'], true)) {
                            $blockedBy = $sourcePath;
                            $excluded = $entries[$sourcePath]['state'] === 'excluded';
                            $reason = ($excluded ? 'Excluded dependency: ' : 'Rejected dependency: ') . $dependency . ' (' . $sourcePath . ')';
                            if ($excluded && str_starts_with($entry['key'], 'post-timestamp:')) {
                                $state = 'excluded';
                                $reason = 'Timestamp for excluded post';
                            }
                            break;
                        }
                    } elseif (!isset($target[$dependency])) {
                        $reason = 'Missing dependency: ' . $dependency;
                        break;
                    }
                }
                if (isset($entry['record'])) {
                    $parent = $entries[$entry['record']] ?? null;
                    if ($parent === null || !in_array($parent['state'], ['import', 'duplicate'], true)) {
                        $reason = $parent === null ? 'Signature record is absent' : 'Signature record is rejected';
                        $blockedBy = $parent === null ? null : $entry['record'];
                        if (($parent['state'] ?? '') === 'excluded') {
                            $state = 'excluded';
                            $reason = 'Signature for excluded record';
                        }
                    } elseif ($parent['state'] === 'duplicate' && $entry['state'] === 'import'
                        && !is_file($destination . '/' . $entry['record'])) {
                        $reason = 'Duplicate record uses a different path; signature requires manual association';
                        $blockedBy = null;
                    }
                } else {
                    foreach (['.asc', '.sig'] as $suffix) {
                        if (isset($entries[$path . $suffix]) && !in_array($entries[$path . $suffix]['state'], ['import', 'duplicate'], true)) {
                            $reason = 'Detached signature is rejected';
                            $blockedBy = $path . $suffix;
                            break;
                        }
                    }
                }
                if ($reason !== null) {
                    $entries[$path]['state'] = $state;
                    $entries[$path]['reason'] = $reason;
                    if ($blockedBy !== null) {
                        $entries[$path]['blocked_by'] = $blockedBy;
                        $entries[$path]['root_cause'] = $entries[$blockedBy]['root_cause'] ?? $blockedBy;
                    }
                    $changed = true;
                }
            }
        } while ($changed);
        return $entries;
    }

    private function describe(string $root, string $path): array
    {
        $entry = ['state' => 'import', 'reason' => '', 'key' => ArchiveRecordCatalog::pathIdentityKey($path), 'dependencies' => []];
        if (ArchiveRecordCatalog::isSignaturePath($path) && !ArchiveRecordCatalog::isRecordPath($path)) {
            $record = substr($path, 0, -4);
            if (!is_file($root . '/' . $record)) {
                return [...$entry, 'state' => 'invalid', 'reason' => 'Signature record is absent: ' . $record, 'record' => $record];
            }
            $parent = $this->describe($root, $record);
            if ($parent['state'] !== 'import') {
                $parent['blocked_by'] = $record;
                $parent['root_cause'] = $record;
            }
            return [...$parent, 'key' => $parent['key'] === null ? null : 'signature:' . $parent['key'] . ':' . substr($path, -4), 'record' => $record];
        }
        if (preg_match('#^records/(instance|approval-seeds|feature-flag-changes|private-messages|invitations)/#', $path)) {
            return [...$entry, 'state' => 'excluded', 'reason' => 'Instance authority, settings, or private data'];
        }
        if (!ArchiveRecordCatalog::isRecordPath($path)) {
            return [...$entry, 'state' => 'unsupported', 'reason' => 'Unsupported record family or path'];
        }
        if (is_file($root . '/' . $path) && filesize($root . '/' . $path) > 16 * 1024 * 1024) {
            return [...$entry, 'state' => 'invalid', 'reason' => 'Record exceeds 16 MiB parser limit'];
        }
        try {
            $repository = new CanonicalRecordRepository($root, requireLegacyTimestampMetadata: true);
            if (str_starts_with($path, 'records/posts/')) {
                $contents = (string) file_get_contents($root . '/' . $path);
                $post = $repository->loadPost($path); // Includes path and timestamp metadata validation.
                try {
                    (new PostRecordParser())->parse($contents);
                } catch (CanonicalRecordParseException $error) {
                    if ($error->getMessage() !== 'Missing required post header: Created-At') { throw $error; }
                    $entry['dependencies'][] = 'post-timestamp:' . $post->postId;
                }
                if ((in_array('identity', $post->boardTags, true) && in_array('approval', $post->boardTags, true))
                    || in_array('invitation', $post->boardTags, true)) {
                    return [...$entry, 'state' => 'excluded', 'reason' => 'Approval or invitation authority post'];
                }
                foreach ([$post->threadId, $post->parentId] as $id) {
                    if ($id !== null) {
                        $entry['dependencies'][] = 'post:' . $id;
                    }
                }
                $author = $post->authorIdentityId;
            } elseif (str_starts_with($path, 'records/post-timestamps/')) {
                $metadata = LegacyPostTimestamp::read($root . '/' . $path);
                $entry['dependencies'][] = 'post:' . $metadata['post_id'];
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
