<?php

declare(strict_types=1);

namespace ForumRewrite\Retraction;

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Host\HighTrafficSafety;
use ForumRewrite\Host\StaticArtifactBuilder;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\PublicationLock;
use ForumRewrite\Write\StaticArtifactInvalidator;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Removes the paths a purge plan names from all Git history, proves it, or
 * restores the pre-purge refs. Phases: snapshot refs into a journal, rewrite
 * with HistoryRewrite, verify no ref still touches a target (restore on
 * failure), expire reflogs and prune, verify the old objects are gone. Only
 * the last phase cannot be undone in place; after it the operator's backup is
 * the sole rollback. Callers that need the repository write lock use run();
 * execute() is the same work without it.
 */
final class HistoryPurge
{
    public const JOURNAL_DIRECTORY = '.git/history-purge';
    private const PSEUDO_REFS = ['ORIG_HEAD', 'FETCH_HEAD', 'MERGE_HEAD', 'CHERRY_PICK_HEAD', 'REBASE_HEAD', 'REVERT_HEAD'];

    public const COMMIT_SUBJECT = 'Record history purge';

    /**
     * The operator-facing purge: under the repository write and publication
     * locks, execute() the rewrite, write and commit the history rewrite record
     * (docs/specs/history_rewrite_record_v1.md), then rebuild the read model,
     * clear the activity commit-manifest cache and regenerate static artifacts.
     * The map is committed even when object verification failed, because the
     * refs have already moved and old links need it either way; `verified`
     * tells the caller. A refresh failure leaves the stale marker set and
     * reports `./v3 rebuild` as the next step.
     *
     * @param array $plan from HistoryPurgePlanner::plan()
     * @param \Closure(string):void|null $progress receives one human-readable line per phase or batch
     * @return array execute() result plus rewritten_at, record_paths, record_commit, rebuilt, static_artifacts
     */
    public static function run(string $root, string $databasePath, array $plan, ?string $artifactRoot = null, ?\Closure $progress = null): array
    {
        $report = static function (string $message) use ($progress): void { if ($progress !== null) { $progress($message); } };
        $report('Waiting for the repository write lock...');
        return (new ExecutionLock(dirname($databasePath) . '/forum-rewrite.lock'))->withExclusiveLock(
            static fn (): array => PublicationLock::withLock($root, static function () use ($root, $databasePath, $plan, $artifactRoot, $progress, $report): array {
                $result = self::execute($root, $plan, null, $progress);
                $rewrittenAt = gmdate('Y-m-d\TH:i:s\Z');
                $files = CommitMap::encode($rewrittenAt, (int) ($plan['receipts'] ?? 0), $result['map']);
                $paths = array_keys($files);
                $report('Committing the history rewrite record (' . count($result['map']) . ' commits mapped, ' . count($paths) . ' file(s))...');
                try {
                    HighTrafficSafety::beforeWrite($root, $databasePath, $paths[0]);
                    CommitMap::write($root, $files);
                    LabelRemoval::git($root, array_merge(['add', '--'], $paths));
                    LabelRemoval::git($root, array_merge(['-c', 'user.name=Forum Rewrite', '-c', 'user.email=forum-rewrite@example.invalid',
                        'commit', '-q', '--only', '-m', self::COMMIT_SUBJECT . ' ' . $rewrittenAt, '--'], $paths));
                } catch (Throwable $error) {
                    throw new RuntimeException('History was purged but the rewrite record was not committed: ' . $error->getMessage()
                        . ' Commit ' . implode(', ', $paths) . ' by hand, then run ./v3 rebuild.', 0, $error);
                }
                $commit = trim(LabelRemoval::git($root, ['rev-parse', 'HEAD']));
                $report('Record committed at ' . $commit . '.');
                try {
                    if ($artifactRoot !== null) {
                        $report('Retiring generated pages under ' . $artifactRoot . '...');
                        (new StaticArtifactInvalidator($artifactRoot))->invalidateAllGeneratedPresentation();
                    }
                    $report('Rebuilding the read model at ' . $databasePath . '...');
                    (new ReadModelBuilder($root, $databasePath, new CanonicalRecordRepository($root), 'history-purge', $progress))->rebuild();
                    self::clearCommitManifestCache($databasePath);
                    (new ReadModelStaleMarker($databasePath))->clear();
                    $report('Read model rebuilt.');
                    if ($artifactRoot !== null) {
                        $report('Rebuilding static pages under ' . $artifactRoot . '...');
                        (new StaticArtifactBuilder(dirname(__DIR__, 3), $root, $databasePath, $artifactRoot, $progress))->buildFromReadModel();
                        $report('Static pages rebuilt.');
                    }
                } catch (Throwable $error) {
                    (new ReadModelStaleMarker($databasePath))->mark(['reason' => 'history_purge_refresh_failed', 'commit_sha' => $commit]);
                    throw new RuntimeException('History purged and recorded at ' . $commit . '; site update incomplete. Run ./v3 rebuild: ' . $error->getMessage(), 0, $error);
                }
                return $result + ['rewritten_at' => $rewrittenAt, 'record_paths' => $paths, 'record_commit' => $commit,
                    'rebuilt' => true, 'static_artifacts' => $artifactRoot];
            })
        );
    }

    /** Cached manifests are keyed by commit id, so every pre-purge row is stale. */
    private static function clearCommitManifestCache(string $databasePath): void
    {
        $path = dirname($databasePath) . '/activity_commit_manifest_cache.sqlite3';
        if (!is_file($path)) {
            return;
        }
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE IF NOT EXISTS activity_commit_manifests (commit_sha TEXT PRIMARY KEY, files_json TEXT NOT NULL, cached_at TEXT NOT NULL)');
        $pdo->exec('DELETE FROM activity_commit_manifests');
    }

    /**
     * @param array $plan from HistoryPurgePlanner::plan(); must be runnable
     * @param callable|null $afterRewrite test hook invoked between rewrite and verification
     * @param \Closure(string):void|null $progress receives one human-readable line per phase
     * @return array{
     *   targets:int, commits_total:int, commits_changed:int, blobs_removed:int,
     *   map:array<string,string>, verified:bool, lingering:list<string>, restored_interrupted_run:bool,
     *   seconds:array<string,float>
     * }
     */
    public static function execute(string $root, array $plan, ?callable $afterRewrite = null, ?\Closure $progress = null): array
    {
        $report = static function (string $message) use ($progress): void { if ($progress !== null) { $progress($message); } };
        $restored = false;
        if (self::interrupted($root) !== null) {
            $report('Restoring the refs of an earlier interrupted purge...');
            self::restore($root);
            $restored = true;
            $report('Re-planning after the restore...');
            $plan = HistoryPurgePlanner::plan($root);
        }
        if (($plan['can_run'] ?? false) !== true || ($plan['targets'] ?? []) === []) {
            throw new RuntimeException('Purge plan is not runnable: ' . implode(', ', $plan['blockers'] ?? ['no plan']));
        }
        $targets = array_values($plan['targets']);
        $seconds = [];
        $clock = static fn (): float => hrtime(true) / 1e9;

        // Pre-state: every ref, the HEAD symref and every blob ever stored at a target path.
        $report('Snapshotting refs and the ' . count($targets) . ' path(s) to purge...');
        $t = $clock();
        $refsBefore = self::refs($root);
        $head = trim(LabelRemoval::git($root, ['symbolic-ref', '-q', 'HEAD']));
        $blobs = self::targetBlobs($root, $targets);
        $seconds['snapshot'] = $clock() - $t;

        $journal = $root . '/' . self::JOURNAL_DIRECTORY;
        if (!mkdir($journal, 0700)) {
            throw new RuntimeException('Cannot create ' . $journal);
        }
        file_put_contents($journal . '/refs-before.txt', $refsBefore === [] ? '' : implode("\n", array_map(
            static fn (string $ref, string $sha): string => $sha . ' ' . $ref, array_keys($refsBefore), $refsBefore)) . "\n");
        self::phase($root, 'rewrite', $head);

        try {
            $total = (int) ($plan['commits_total'] ?? 0);
            $report('Rewriting ' . ($total > 0 ? number_format($total) . ' commits' : 'history') . ' (old refs stay restorable until verified)...');
            $t = $clock();
            $lastReport = $t;
            $map = HistoryRewrite::rewrite($root, $targets, static function (int $done) use ($report, $total, $clock, &$lastReport): void {
                if ($done % 5000 === 0 || $clock() - $lastReport >= 2.0) {
                    $lastReport = $clock();
                    $report('Rewriting history: ' . number_format($done) . ($total > 0 ? ' of ' . number_format($total) : '') . ' commits streamed...');
                }
            });
            $seconds['rewrite'] = $clock() - $t;
            $report('Rewrite finished in ' . number_format($seconds['rewrite'], 1) . ' s; verifying that no ref still touches a purged path...');
            if ($afterRewrite !== null) {
                $afterRewrite($root, $map);
            }
            self::phase($root, 'verify-refs', $head);
            $t = $clock();
            $remaining = HistoryRewrite::remainingCommits($root, $targets);
            if ($remaining !== 0) {
                throw new RuntimeException('History rewrite left ' . $remaining . ' commit(s) touching a purged path.');
            }
            foreach ($refsBefore as $ref => $sha) {
                if (!isset($map[$sha]) && !str_starts_with($ref, 'refs/tags/')) {
                    throw new RuntimeException('History rewrite did not map ' . $ref . '.');
                }
            }
            $seconds['verify_refs'] = $clock() - $t;
        } catch (Throwable $failure) {
            $report('Rewrite failed: ' . $failure->getMessage() . ' Restoring the original refs...');
            self::restoreRefs($root, $refsBefore, $head);
            self::clearJournal($root);
            throw new RuntimeException('Purge aborted before any history was discarded; original refs restored. ' . $failure->getMessage(), 0, $failure);
        }

        // Point of no return: old objects are discarded.
        $report('Refs verified. Expiring reflogs and pruning old objects (point of no return)...');
        self::phase($root, 'prune', $head);
        $t = $clock();
        foreach ($targets as $path) {
            if (is_file($root . '/' . $path)) {
                unlink($root . '/' . $path);
            }
        }
        LabelRemoval::git($root, ['reset', '-q']);
        foreach (self::PSEUDO_REFS as $pseudo) {
            if (is_file($root . '/.git/' . $pseudo)) {
                unlink($root . '/.git/' . $pseudo);
            }
        }
        foreach (array_keys(self::refs($root, 'refs/original/')) as $ref) {
            LabelRemoval::git($root, ['update-ref', '-d', $ref]);
        }
        LabelRemoval::git($root, ['reflog', 'expire', '--expire=now', '--expire-unreachable=now', '--all']);
        LabelRemoval::git($root, ['reflog', 'expire', '--expire=now', '--expire-unreachable=now', 'HEAD']);
        LabelRemoval::git($root, ['-c', 'gc.auto=0', 'gc', '--prune=now', '--quiet']);
        $seconds['prune'] = $clock() - $t;

        self::phase($root, 'verify-objects', $head);
        $t = $clock();
        $changed = array_keys(array_filter($map, static fn (string $new, string $old): bool => $new !== $old, ARRAY_FILTER_USE_BOTH));
        $report('Pruned in ' . number_format($seconds['prune'], 1) . ' s; checking that ' . number_format(count($blobs)) . ' old blob(s) and ' . number_format(count($changed)) . ' old commit id(s) are gone...');
        $lingering = self::lingering($root, array_merge($blobs, $changed));
        $seconds['verify_objects'] = $clock() - $t;
        if ($lingering === []) {
            self::clearJournal($root);
            $report('Verified: no old object remains.');
        } else {
            self::phase($root, 'lingering', $head);
            $report('Verification failed: ' . count($lingering) . ' old object(s) still exist.');
        }

        return [
            'targets' => count($targets),
            'commits_total' => count($map),
            'commits_changed' => count($changed),
            'blobs_removed' => count($blobs),
            'map' => $map,
            'verified' => $lingering === [],
            'lingering' => $lingering,
            'restored_interrupted_run' => $restored,
            'seconds' => $seconds,
        ];
    }

    /**
     * Object ids from the list that still exist anywhere in the object database,
     * reachable or not. Empty means the purge is complete.
     *
     * @param list<string> $objectIds
     * @return list<string>
     */
    public static function lingering(string $root, array $objectIds): array
    {
        $objectIds = array_values(array_unique($objectIds));
        if ($objectIds === []) {
            return [];
        }
        $pipes = [];
        $process = proc_open(['git', '-C', $root, 'cat-file', '--batch-check'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Cannot run Git.');
        }
        $present = [];
        try {
            foreach (array_chunk($objectIds, 1000) as $chunk) {
                fwrite($pipes[0], implode("\n", $chunk) . "\n");
                fflush($pipes[0]);
                foreach ($chunk as $id) {
                    $line = fgets($pipes[1]);
                    if ($line === false) {
                        throw new RuntimeException('Object check ended early.');
                    }
                    if (!str_ends_with(trim($line), ' missing')) {
                        $present[] = $id;
                    }
                }
            }
        } finally {
            fclose($pipes[0]);
            fclose($pipes[1]);
            fclose($pipes[2]);
            proc_close($process);
        }
        return $present;
    }

    /**
     * State of an interrupted run, or null when none is recorded.
     *
     * @return array{phase:string, head:string, restorable:bool}|null
     */
    public static function interrupted(string $root): ?array
    {
        $file = $root . '/' . self::JOURNAL_DIRECTORY . '/journal.txt';
        if (!is_file($file)) {
            return null;
        }
        $state = ['phase' => 'unknown', 'head' => ''];
        foreach (explode("\n", (string) file_get_contents($file)) as $line) {
            if (preg_match('/^(Phase|HEAD): (.*)$/', $line, $m) === 1) {
                $state[strtolower($m[1]) === 'head' ? 'head' : 'phase'] = $m[2];
            }
        }
        $state['restorable'] = in_array($state['phase'], ['rewrite', 'verify-refs'], true);
        return $state;
    }

    /**
     * Puts the refs recorded by an interrupted run back. Possible only before
     * the prune phase; afterwards the old objects are gone and the operator's
     * backup is the rollback.
     *
     * @return array{phase:string, refs:int}
     */
    public static function restore(string $root): array
    {
        $state = self::interrupted($root);
        if ($state === null) {
            throw new RuntimeException('No interrupted purge to restore.');
        }
        if (!$state['restorable']) {
            throw new RuntimeException('The interrupted purge had already discarded old history (phase ' . $state['phase'] . '); restore the repository from the pre-purge backup.');
        }
        $refs = [];
        foreach (explode("\n", (string) file_get_contents($root . '/' . self::JOURNAL_DIRECTORY . '/refs-before.txt')) as $line) {
            if (preg_match('/^([0-9a-f]{40,64}) (\S+)$/', $line, $m) === 1) {
                $refs[$m[2]] = $m[1];
            }
        }
        if (is_dir($root . '/.git/history-rewrite')) {
            foreach (glob($root . '/.git/history-rewrite/*') ?: [] as $leftover) {
                unlink($leftover);
            }
            rmdir($root . '/.git/history-rewrite');
        }
        self::restoreRefs($root, $refs, $state['head']);
        self::clearJournal($root);
        return ['phase' => $state['phase'], 'refs' => count($refs)];
    }

    /** @param array<string,string> $refs refname => object id */
    private static function restoreRefs(string $root, array $refs, string $head): void
    {
        foreach ($refs as $ref => $sha) {
            if (self::lingering($root, [$sha]) === []) {
                throw new RuntimeException('Cannot restore ' . $ref . ': object ' . $sha . ' no longer exists; restore from the pre-purge backup.');
            }
        }
        foreach (array_keys(self::refs($root)) as $ref) {
            if (!isset($refs[$ref])) {
                LabelRemoval::git($root, ['update-ref', '-d', $ref]);
            }
        }
        foreach ($refs as $ref => $sha) {
            LabelRemoval::git($root, ['update-ref', $ref, $sha]);
        }
        if ($head !== '') {
            LabelRemoval::git($root, ['symbolic-ref', 'HEAD', $head]);
        }
        LabelRemoval::git($root, ['reset', '-q']);
    }

    /** @return array<string,string> refname => object id */
    private static function refs(string $root, string $prefix = ''): array
    {
        $refs = [];
        $args = ['for-each-ref', '--format=%(objectname) %(refname)'];
        if ($prefix !== '') {
            $args[] = $prefix;
        }
        foreach (explode("\n", trim(LabelRemoval::git($root, $args))) as $line) {
            if (preg_match('/^([0-9a-f]{40,64}) (\S+)$/', $line, $m) === 1) {
                $refs[$m[2]] = $m[1];
            }
        }
        return $refs;
    }

    /**
     * Every blob id ever stored at one of the target paths on any ref.
     *
     * @param list<string> $targets
     * @return list<string>
     */
    private static function targetBlobs(string $root, array $targets): array
    {
        $blobs = [];
        foreach (array_chunk($targets, 200) as $chunk) {
            $out = LabelRemoval::git($root, array_merge(['log', '--all', '--format=', '--raw', '--no-abbrev', '--no-renames', '--'], $chunk));
            foreach (explode("\n", $out) as $line) {
                if (preg_match('/^:\d+ \d+ ([0-9a-f]{40,64}) ([0-9a-f]{40,64}) [A-Z]\d*\t(.+)$/', $line, $m) === 1) {
                    foreach ([$m[1], $m[2]] as $sha) {
                        if (trim($sha, '0') !== '') {
                            $blobs[$sha] = true;
                        }
                    }
                }
            }
        }
        return array_keys($blobs);
    }

    private static function phase(string $root, string $phase, string $head): void
    {
        file_put_contents($root . '/' . self::JOURNAL_DIRECTORY . '/journal.txt',
            'Phase: ' . $phase . "\nHEAD: " . $head . "\nUpdated-At: " . gmdate('Y-m-d\TH:i:s\Z') . "\n", LOCK_EX);
    }

    private static function clearJournal(string $root): void
    {
        $journal = $root . '/' . self::JOURNAL_DIRECTORY;
        foreach (['journal.txt', 'refs-before.txt'] as $name) {
            if (is_file($journal . '/' . $name)) {
                unlink($journal . '/' . $name);
            }
        }
        if (is_dir($journal)) {
            rmdir($journal);
        }
    }
}
