<?php

declare(strict_types=1);

namespace ForumRewrite\Retraction;

use ForumRewrite\Canonical\LabelRemoval;
use RuntimeException;

/**
 * Built-in Git history rewrite primitive shared by the purge planner's dry run
 * and the purge engine. Removes the given paths from every commit on every ref
 * by streaming `git fast-export` through a path filter into `git fast-import`
 * on the same repository. Commit count, order, messages, authors and dates are
 * preserved; a commit that only touched a removed path becomes an empty commit
 * so the old-to-new mapping stays one to one. Callers own locking, ref
 * snapshots, verification, reflog expiry and pruning.
 *
 * Engine decision (Stage 1 sizing gate): `filter-branch` spawns a shell per
 * commit and measured about three commits per second on a 100k-post synthetic
 * repository; this stream rewrites the same history in well under a minute and
 * yields the commit map natively through original ids and import marks.
 */
final class HistoryRewrite
{
    /**
     * @param list<string> $paths canonical record paths to drop from all history
     * @return array<string,string> old commit id => new commit id, for every rewritten commit
     */
    public static function rewrite(string $root, array $paths): array
    {
        if ($paths === []) {
            throw new RuntimeException('Nothing to rewrite.');
        }
        $targets = [];
        foreach ($paths as $path) {
            if (!preg_match('#^records/[A-Za-z0-9/._-]+$#D', $path) || str_contains($path, '..')) {
                throw new RuntimeException('Refusing to rewrite an unexpected path: ' . $path);
            }
            $targets[$path] = true;
        }
        if (!is_dir($root . '/.git')) {
            throw new RuntimeException('Not a Git repository: ' . $root);
        }

        $work = $root . '/.git/history-rewrite';
        if (file_exists($work)) {
            throw new RuntimeException('A history rewrite is already in progress: ' . $work);
        }
        if (!mkdir($work, 0700)) {
            throw new RuntimeException('Cannot create ' . $work);
        }
        $marksFile = $work . '/marks';
        $exportErr = $work . '/export.err';
        $importErr = $work . '/import.err';
        $env = ['PATH' => (string) getenv('PATH'), 'HOME' => (string) (getenv('HOME') ?: '/tmp')];

        try {
            $exportPipes = [];
            $export = proc_open(
                ['git', '-C', $root, 'fast-export', '--all', '--no-data', '--show-original-ids', '--reencode=no', '--signed-tags=strip'],
                [1 => ['pipe', 'w'], 2 => ['file', $exportErr, 'w']],
                $exportPipes,
                null,
                $env
            );
            $importPipes = [];
            $import = proc_open(
                ['git', '-C', $root, 'fast-import', '--quiet', '--force', '--export-marks=' . $marksFile],
                [0 => ['pipe', 'r'], 1 => ['file', $importErr, 'a'], 2 => ['file', $importErr, 'a']],
                $importPipes,
                null,
                $env
            );
            if (!is_resource($export) || !is_resource($import)) {
                throw new RuntimeException('Cannot run Git.');
            }

            $markToOld = self::filterStream($exportPipes[1], $importPipes[0], $targets);

            fclose($exportPipes[1]);
            fclose($importPipes[0]);
            $exportStatus = proc_close($export);
            $importStatus = proc_close($import);
            if ($exportStatus !== 0) {
                throw new RuntimeException('History export failed: ' . trim((string) file_get_contents($exportErr)));
            }
            if ($importStatus !== 0) {
                throw new RuntimeException('History import failed: ' . trim((string) file_get_contents($importErr)));
            }

            $map = [];
            foreach (explode("\n", (string) file_get_contents($marksFile)) as $line) {
                if (preg_match('/^:(\d+) ([0-9a-f]{40,64})$/', trim($line), $m) === 1 && isset($markToOld[(int) $m[1]])) {
                    $map[$markToOld[(int) $m[1]]] = $m[2];
                }
            }
            if (count($map) !== count($markToOld)) {
                throw new RuntimeException('History rewrite produced an incomplete commit map.');
            }
            return $map;
        } finally {
            foreach ([$marksFile, $exportErr, $importErr] as $file) {
                if (is_file($file)) {
                    unlink($file);
                }
            }
            if (is_dir($work)) {
                rmdir($work);
            }
        }
    }

    /**
     * Copies a fast-export stream to fast-import, dropping file modifications and
     * deletions for the target paths. Returns commit mark => original commit id.
     *
     * @param resource $in
     * @param resource $out
     * @param array<string,true> $targets
     * @return array<int,string>
     */
    private static function filterStream($in, $out, array $targets): array
    {
        // A `data <n>` block carries exactly n bytes with no terminator, so the
        // stream is copied by line except inside those blocks. File commands only
        // occur inside commits; `mark` directly follows `commit`, then `original-oid`.
        $markToOld = [];
        $commitMark = null;
        while (($line = fgets($in)) !== false) {
            if (preg_match('/^data (\d+)\n$/', $line, $m) === 1) {
                fwrite($out, $line);
                $remaining = (int) $m[1];
                while ($remaining > 0) {
                    $chunk = fread($in, min($remaining, 65536));
                    if ($chunk === false || $chunk === '') {
                        throw new RuntimeException('History export stream ended inside a data block.');
                    }
                    fwrite($out, $chunk);
                    $remaining -= strlen($chunk);
                }
                continue;
            }
            if (str_starts_with($line, 'commit ')) {
                $commitMark = null;
            } elseif ($commitMark === null && preg_match('/^mark :(\d+)\n$/', $line, $m) === 1) {
                $commitMark = (int) $m[1];
            } elseif ($commitMark !== null && !isset($markToOld[$commitMark]) && preg_match('/^original-oid ([0-9a-f]{40,64})\n$/', $line, $m) === 1) {
                $markToOld[$commitMark] = $m[1];
            } elseif (preg_match('/^M \d+ \S+ (.+)\n$/', $line, $m) === 1 && isset($targets[$m[1]])) {
                continue;
            } elseif (preg_match('/^D (.+)\n$/', $line, $m) === 1 && isset($targets[$m[1]])) {
                continue;
            }
            fwrite($out, $line);
        }
        return $markToOld;
    }

    /** Commits on all refs that still touch any of the paths. */
    public static function remainingCommits(string $root, array $paths): int
    {
        if ($paths === []) {
            return 0;
        }
        $refs = array_filter(explode("\n", trim(LabelRemoval::git($root, ['for-each-ref', '--format=%(refname)']))),
            static fn (string $ref): bool => $ref !== '');
        if ($refs === []) {
            return 0;
        }
        $count = 0;
        foreach (array_chunk($paths, 200) as $chunk) {
            $count += (int) trim(LabelRemoval::git($root, array_merge(['rev-list', '--count'], array_values($refs), ['--'], $chunk)));
        }
        return $count;
    }
}
