<?php

declare(strict_types=1);

namespace ForumRewrite\Retraction;

use ForumRewrite\Canonical\AcceptedRemoval;
use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\RecordReceipt;
use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Canonical\LegacyPostTimestamp;
use RuntimeException;

/**
 * Read-only planning for a history purge: which paths accepted deletion
 * receipts name, how much history touches them, and what blocks a run.
 * Never modifies the repository it inspects.
 */
final class HistoryPurgePlanner
{
    /**
     * @return array{
     *   receipts:int, receipts_without_history:list<string>, targets:list<string>, commits_total:int, commits_affected:int,
     *   legacy_without_timestamp:list<string>, blockers:list<string>, can_run:bool
     * }
     */
    public static function plan(string $root): array
    {
        $result = ['receipts' => 0, 'receipts_without_history' => [], 'targets' => [], 'commits_total' => 0, 'commits_affected' => 0,
            'legacy_without_timestamp' => [], 'blockers' => [], 'can_run' => false];
        if (!is_dir($root . '/.git')) {
            $result['blockers'][] = 'missing_git';
            return $result;
        }
        if (trim(LabelRemoval::git($root, ['status', '--porcelain', '--untracked-files=no'])) !== '') {
            $result['blockers'][] = 'dirty_tree';
        }
        if (is_file($root . '/.git/instance-import/pending.json')) {
            $result['blockers'][] = 'interrupted_import';
        }
        if (HistoryPurge::interrupted($root) !== null) {
            $result['blockers'][] = 'interrupted_purge';
        }

        $candidates = [];
        $receiptIds = [];
        foreach (AcceptedRemoval::all($root) as $id => $record) {
            if (($record['operation'] ?? '') !== 'delete') {
                continue;
            }
            $result['receipts']++;
            $receiptIds[(string) $id] = [];
            foreach (CanonicalPathResolver::postCandidates((string) $id) as $candidate) {
                foreach ([$candidate, $candidate . '.asc', $candidate . RecordReceipt::SUFFIX] as $file) {
                    $candidates[$file] = true;
                    $receiptIds[(string) $id][] = $file;
                }
            }
        }

        $result['commits_total'] = (int) trim(LabelRemoval::git($root, ['rev-list', '--count', '--all']));
        if ($candidates !== []) {
            // One history walk over the posts tree; intersect with the candidates
            // so the plan names only paths that ever existed.
            $seen = [];
            foreach (explode("\n", LabelRemoval::git($root, ['log', '--all', '--format=', '--name-only', '--', 'records/posts'])) as $line) {
                if ($line !== '' && isset($candidates[$line])) {
                    $seen[$line] = true;
                }
            }
            $result['targets'] = array_keys($seen);
            sort($result['targets']);
            $result['commits_affected'] = self::affectedCommits($root, $result['targets']);
            // A receipt can name a post with no file anywhere in this history: one
            // already purged, or one that was never committed here. It is still a
            // receipt, but there is nothing for the purge to remove.
            foreach ($receiptIds as $id => $paths) {
                if (array_intersect($paths, $result['targets']) === []) {
                    $result['receipts_without_history'][] = $id;
                }
            }
            sort($result['receipts_without_history']);
        }

        $result['legacy_without_timestamp'] = self::legacyPostsWithoutTimestamp($root);
        if ($result['legacy_without_timestamp'] !== []) {
            $result['blockers'][] = 'legacy_timestamps_missing';
        }
        if ($result['targets'] === []) {
            $result['blockers'][] = 'no_targets';
        }
        $result['can_run'] = $result['blockers'] === [];
        return $result;
    }

    /**
     * Clones the repository into $workDirectory and runs the real rewrite there, timed.
     * The source repository is never touched. The caller removes $workDirectory.
     *
     * @param list<string> $targets
     * @return array{clone_seconds:float, rewrite_seconds:float, commits_total:int, rewritten_commits:int, remaining_commits:int}
     */
    public static function timeDryRun(string $root, array $targets, string $workDirectory): array
    {
        if ($targets === []) {
            throw new RuntimeException('Nothing to purge.');
        }
        if (file_exists($workDirectory)) {
            throw new RuntimeException('Dry-run directory already exists: ' . $workDirectory);
        }
        $started = hrtime(true);
        LabelRemoval::git(dirname($workDirectory), ['clone', '-q', '--no-hardlinks', $root, $workDirectory]);
        $cloned = hrtime(true);
        $map = HistoryRewrite::rewrite($workDirectory, $targets);
        $rewritten = hrtime(true);
        return [
            'clone_seconds' => ($cloned - $started) / 1e9,
            'rewrite_seconds' => ($rewritten - $cloned) / 1e9,
            'commits_total' => (int) trim(LabelRemoval::git($workDirectory, ['rev-list', '--count', '--all'])),
            'rewritten_commits' => count($map),
            'remaining_commits' => HistoryRewrite::remainingCommits($workDirectory, $targets),
        ];
    }

    /** @param list<string> $targets */
    private static function affectedCommits(string $root, array $targets): int
    {
        $shas = [];
        foreach (array_chunk($targets, 200) as $chunk) {
            foreach (explode("\n", LabelRemoval::git($root, array_merge(['rev-list', '--all', '--'], $chunk))) as $sha) {
                if ($sha !== '') {
                    $shas[$sha] = true;
                }
            }
        }
        return count($shas);
    }

    /**
     * Current post paths lacking a Created-At header and a matching timestamp record.
     * Post bytes stream from one `git cat-file --batch` process in index order, so a
     * large repository costs one object walk rather than one file open per post.
     *
     * @return list<string>
     */
    public static function legacyPostsWithoutTimestamp(string $root): array
    {
        $paths = [];
        foreach (explode("\n", LabelRemoval::git($root, ['ls-files', '-s', '--', 'records/posts'])) as $entry) {
            if (preg_match('/^\d+ ([0-9a-f]{40,64}) \d\t(.+\.txt)$/', $entry, $m) === 1) {
                $paths[] = [$m[1], $m[2]];
            }
        }
        if ($paths === []) {
            return [];
        }
        $pipes = [];
        $process = proc_open(['git', '-C', $root, 'cat-file', '--batch'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot run Git.');
        }
        $missing = [];
        try {
            foreach (array_chunk($paths, 256) as $chunk) {
                foreach ($chunk as [$sha]) {
                    fwrite($pipes[0], $sha . "\n");
                }
                fflush($pipes[0]);
                foreach ($chunk as [$sha, $path]) {
                    $header = fgets($pipes[1]);
                    if ($header === false || preg_match('/^[0-9a-f]+ blob (\d+)\n$/', $header, $m) !== 1) {
                        throw new RuntimeException('Unexpected object while reading ' . $path);
                    }
                    $size = (int) $m[1];
                    $contents = '';
                    while (strlen($contents) < $size) {
                        $chunkBytes = fread($pipes[1], $size - strlen($contents));
                        if ($chunkBytes === false || $chunkBytes === '') {
                            throw new RuntimeException('Short read while reading ' . $path);
                        }
                        $contents .= $chunkBytes;
                    }
                    fgetc($pipes[1]);
                    $headers = explode("\n\n", str_replace("\r\n", "\n", $contents), 2)[0];
                    if (preg_match('/^Created-At:/m', $headers) === 1) {
                        continue;
                    }
                    try {
                        if (LegacyPostTimestamp::resolve($root, $path, $contents) === null) {
                            $missing[] = $path;
                        }
                    } catch (\Throwable) {
                        $missing[] = $path;
                    }
                }
            }
        } finally {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
        sort($missing);
        return $missing;
    }
}
