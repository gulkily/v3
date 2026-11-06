<?php

declare(strict_types=1);

namespace ForumRewrite\Retraction;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Canonical\LegacyPostTimestamp;
use RuntimeException;

/**
 * Writes `records/post-timestamps/` for committed legacy posts that lack
 * `Created-At`, so their dates no longer depend on Git history. Clears the
 * history purge's `legacy_timestamps_missing` blocker.
 *
 * The date is the one rebuild already derives (author date of the commit that
 * first added the file, following renames), read through the canonical
 * repository, so baking changes no displayed date. The filesystem-mtime fallback
 * is disabled: a post Git cannot date is reported and left unbaked.
 */
final class LegacyTimestampBake
{
    public const COMMIT_MESSAGE = 'Bake legacy post timestamps';

    /** @return array{baked: list<array{path:string,created_at:string}>, undated: list<string>} */
    public static function plan(string $root): array
    {
        if (!is_dir($root . '/.git')) {
            throw new RuntimeException('Not a Git repository: ' . $root);
        }
        $repository = new CanonicalRecordRepository($root, allowLegacyFilesystemTimestamp: false);
        $result = ['baked' => [], 'undated' => []];
        foreach (HistoryPurgePlanner::legacyPostsWithoutTimestamp($root) as $path) {
            try {
                $result['baked'][] = ['path' => $path, 'created_at' => $repository->loadPost($path)->createdAt];
            } catch (\Throwable) {
                $result['undated'][] = $path;
            }
        }
        return $result;
    }

    /**
     * Writes and commits the planned records in one commit that touches only
     * `records/post-timestamps/`, so unrelated working-tree edits stay out.
     *
     * @return array{baked: list<array{path:string,created_at:string}>, undated: list<string>, commit: ?string}
     */
    public static function run(string $root): array
    {
        $plan = self::plan($root);
        $written = [];
        foreach ($plan['baked'] as $entry) {
            $id = basename($entry['path'], '.txt');
            $relative = LegacyPostTimestamp::path($id);
            $file = $root . '/' . $relative;
            if (file_exists($file) || is_link($file)) {
                throw new RuntimeException('Timestamp record already exists but is not committed or does not match: ' . $relative);
            }
            // Committed bytes, not the working copy: the purge checks what is in HEAD.
            $contents = LabelRemoval::git($root, ['show', 'HEAD:' . $entry['path']]);
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0775, true);
            }
            if (file_put_contents($file, LegacyPostTimestamp::encode($id, $contents, $entry['created_at'])) === false) {
                throw new RuntimeException('Cannot write ' . $relative);
            }
            if (LegacyPostTimestamp::resolve($root, $entry['path'], $contents) !== $entry['created_at']) {
                throw new RuntimeException('Timestamp record does not validate: ' . $relative);
            }
            $written[] = $relative;
        }
        $commit = null;
        if ($written !== []) {
            LabelRemoval::git($root, ['add', '--', ...$written]);
            LabelRemoval::git($root, ['commit', '-q', '-m', self::COMMIT_MESSAGE, '--', ...$written]);
            $commit = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
        }
        return $plan + ['commit' => $commit];
    }
}
