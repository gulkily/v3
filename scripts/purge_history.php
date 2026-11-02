<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\PresentationPathResolver;
use ForumRewrite\Retraction\HistoryPurge;
use ForumRewrite\Retraction\HistoryPurgePlanner;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\DeploymentSettings;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);

function purgeHistoryUsage(): string
{
    return <<<'TEXT'
Usage:
  ./v3 purge-history preview [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--static-html-root=/path/static_html] [--skip-dry-run] [--json]
  ./v3 purge-history run [--yes] [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--static-html-root=/path/static_html] [--json]

Removes every post and signature path named by an accepted delete receipt from
all Git history, commits a history rewrite record mapping old commit ids to new
ones, then rebuilds the read model and static pages. preview changes nothing
and times the rewrite on a temporary clone. run asks you to type yes on the
terminal before rewriting (--yes skips the question for scripts); it cannot be
undone except from a full backup of the repository including .git, so take and
verify one first.
See docs/runbooks/history_purge.md.

TEXT;
}

$blockerText = [
    'missing_git' => 'the repository has no .git directory',
    'dirty_tree' => 'the working tree has uncommitted changes; commit or discard them',
    'interrupted_import' => 'an instance import is interrupted; finish it with ./v3 import-instance --resume first',
    'interrupted_purge' => 'an earlier purge did not finish',
    'legacy_timestamps_missing' => 'legacy posts lack a Created-At header and a records/post-timestamps/ record; bake timestamps first',
    'no_targets' => 'no accepted delete receipt names a path that exists in history; nothing to purge',
];

try {
    $command = $argv[1] ?? '';
    if ($command === '' || in_array($command, ['-h', '--help', 'help'], true)) {
        fwrite($command === '' ? STDERR : STDOUT, purgeHistoryUsage());
        exit($command === '' ? 1 : 0);
    }
    if (!in_array($command, ['preview', 'run'], true)) {
        throw new InvalidArgumentException('Unknown command: ' . $command);
    }
    $options = ['json' => false, 'skip-dry-run' => false, 'yes' => false];
    foreach (array_slice($argv, 2) as $argument) {
        if ($argument === '--json') { $options['json'] = true; continue; }
        if ($argument === '--skip-dry-run' && $command === 'preview') { $options['skip-dry-run'] = true; continue; }
        if ($argument === '--yes' && $command === 'run') { $options['yes'] = true; continue; }
        if (in_array($argument, ['-h', '--help'], true)) { fwrite(STDOUT, purgeHistoryUsage()); exit(0); }
        if (preg_match('/^--(repository-root|database-path|static-html-root)=(.+)$/D', $argument, $m) !== 1) {
            throw new InvalidArgumentException('Unknown option: ' . $argument);
        }
        $options[$m[1]] = $m[2];
    }
    $repositoryRoot = rtrim($options['repository-root'] ?? (DeploymentSettings::value('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot)), '/');
    $databasePath = $options['database-path'] ?? (DeploymentSettings::value('FORUM_DATABASE_PATH') ?: $projectRoot . '/state/cache/post_index.sqlite3');
    $staticHtmlRoot = $options['static-html-root'] ?? (DeploymentSettings::value('FORUM_STATIC_HTML_ROOT') ?: PresentationPathResolver::staticHtmlRoot($projectRoot, SiteProfileRegistry::active()));
    if (!is_dir($repositoryRoot)) {
        throw new InvalidArgumentException('Repository root does not exist: ' . $repositoryRoot);
    }
    $resolvedRoot = realpath($repositoryRoot) ?: $repositoryRoot;
    if ($command === 'run' && !$options['yes'] && !stream_isatty(STDIN)) {
        throw new InvalidArgumentException('run asks for confirmation on a terminal; pass --yes to skip the question when scripting. Back up the repository including .git first.');
    }

    $plan = HistoryPurgePlanner::plan($resolvedRoot);
    $interrupted = HistoryPurge::interrupted($resolvedRoot);
    $blockers = [];
    foreach ($plan['blockers'] as $blocker) {
        $text = $blockerText[$blocker] ?? $blocker;
        if ($blocker === 'interrupted_purge' && $interrupted !== null) {
            $text .= $interrupted['restorable']
                ? ' (stopped at phase ' . $interrupted['phase'] . ' before any history was discarded; run restores the original refs first)'
                : ' (stopped at phase ' . $interrupted['phase'] . ' after old history was discarded; restore the repository from the pre-purge backup)';
        }
        $blockers[$blocker] = $text;
    }
    $runnable = $plan['can_run'] || ($plan['blockers'] === ['interrupted_purge'] && $interrupted !== null && $interrupted['restorable']);

    if ($command === 'preview') {
        $dryRun = null;
        if ($runnable && $plan['targets'] !== [] && !$options['skip-dry-run']) {
            $work = sys_get_temp_dir() . '/history-purge-dry-run-' . bin2hex(random_bytes(6));
            try {
                $dryRun = HistoryPurgePlanner::timeDryRun($resolvedRoot, $plan['targets'], $work);
            } finally {
                if (is_dir($work)) {
                    exec('rm -rf ' . escapeshellarg($work));
                }
            }
        }
        $report = ['status' => $runnable ? 'ready' : 'blocked', 'repository' => $resolvedRoot, 'receipts' => $plan['receipts'],
            'receipts_without_history' => $plan['receipts_without_history'],
            'targets' => $plan['targets'], 'commits_total' => $plan['commits_total'], 'commits_affected' => $plan['commits_affected'],
            'legacy_without_timestamp' => $plan['legacy_without_timestamp'], 'blockers' => $blockers, 'dry_run' => $dryRun,
            'next' => $runnable ? './v3 purge-history run' : null];
        if ($options['json']) {
            echo json_encode($report, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            echo "History purge preview\n";
            echo 'Repository: ' . $resolvedRoot . "\n";
            echo 'Accepted delete receipts: ' . $plan['receipts'] . "\n";
            if ($plan['receipts_without_history'] !== []) {
                echo 'Receipts with no post file left in this history (nothing to purge for them): ' . count($plan['receipts_without_history']) . "\n";
                foreach ($plan['receipts_without_history'] as $id) {
                    echo '  ' . $id . "\n";
                }
            }
            echo 'Paths to purge (a post record and its detached signature count separately): ' . count($plan['targets']) . "\n";
            foreach ($plan['targets'] as $target) {
                echo '  ' . $target . "\n";
            }
            echo 'Commits: ' . $plan['commits_total'] . ' total, ' . $plan['commits_affected'] . " touching purged paths\n";
            if ($plan['legacy_without_timestamp'] !== []) {
                echo 'Legacy posts without a timestamp record: ' . count($plan['legacy_without_timestamp']) . "\n";
                foreach (array_slice($plan['legacy_without_timestamp'], 0, 20) as $path) {
                    echo '  ' . $path . "\n";
                }
                if (count($plan['legacy_without_timestamp']) > 20) {
                    echo '  ... and ' . (count($plan['legacy_without_timestamp']) - 20) . " more\n";
                }
            }
            if ($dryRun !== null) {
                echo sprintf("Dry run on a temporary clone: clone %.1f s, rewrite %.1f s, %d of %d commits rewritten, %d still touching purged paths\n",
                    $dryRun['clone_seconds'], $dryRun['rewrite_seconds'], $dryRun['rewritten_commits'], $dryRun['commits_total'], $dryRun['remaining_commits']);
                echo "Expect run to take about the rewrite time plus pruning and a full rebuild, with writes paused throughout.\n";
            }
            if ($blockers !== []) {
                echo "Blocked:\n";
                foreach ($blockers as $text) {
                    echo '  - ' . $text . "\n";
                }
            }
            if ($runnable) {
                echo "Next: back up the repository including .git, verify the backup, then run ./v3 purge-history run\n";
            }
        }
        exit($runnable ? 0 : 1);
    }

    if (!$runnable) {
        if ($options['json']) {
            echo json_encode(['status' => 'blocked', 'repository' => $resolvedRoot, 'blockers' => $blockers], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
        } else {
            fwrite(STDERR, "History purge refused:\n");
            foreach ($blockers as $text) {
                fwrite(STDERR, '  - ' . $text . "\n");
            }
        }
        exit(1);
    }
    if (!$options['yes']) {
        fwrite(STDERR, 'About to rewrite all Git history of ' . $resolvedRoot . ': ' . count($plan['targets']) . ' path(s) from ' . $plan['commits_total']
            . " commits, then prune, record, and rebuild. This cannot be undone except from your backup including .git.\nType yes to continue: ");
        $answer = fgets(STDIN);
        if ($answer === false || trim($answer) !== 'yes') {
            fwrite(STDERR, "Cancelled; nothing changed.\n");
            exit(1);
        }
    }
    $startedAt = hrtime(true);
    $progress = static function (string $message) use ($startedAt): void {
        fwrite(STDERR, sprintf("[%6.1fs] %s\n", (hrtime(true) - $startedAt) / 1e9, $message));
    };
    $result = HistoryPurge::run($resolvedRoot, $databasePath, $plan, $staticHtmlRoot, $progress);
    $limits = 'Not reached by this purge: your own backups, other clones and mirrors, downloads already made, provider or search-engine copies, and quotations in surviving posts.';
    if ($options['json']) {
        unset($result['map']);
        $result['limits'] = $limits;
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        echo 'History purge ' . ($result['verified'] ? 'complete' : 'incomplete') . "\n";
        echo 'Repository: ' . $resolvedRoot . "\n";
        if ($result['restored_interrupted_run']) {
            echo "Restored the refs of an earlier interrupted purge before starting.\n";
        }
        echo 'Paths purged: ' . $result['targets'] . "\n";
        echo 'Commits rewritten: ' . $result['commits_changed'] . ' of ' . $result['commits_total'] . "\n";
        echo 'Old objects removed: ' . $result['blobs_removed'] . " blobs\n";
        echo 'History rewrite record: ' . implode(', ', $result['record_paths']) . ' committed at ' . $result['record_commit'] . "\n";
        echo 'Read model rebuilt: ' . $databasePath . "\n";
        echo 'Static pages rebuilt: ' . $staticHtmlRoot . "\n";
        echo sprintf("Time: rewrite %.1f s, prune %.1f s, verification %.1f s\n", $result['seconds']['rewrite'], $result['seconds']['prune'], $result['seconds']['verify_refs'] + $result['seconds']['verify_objects']);
        if (!$result['verified']) {
            echo 'Verification failed: ' . count($result['lingering']) . " old object(s) still exist in the object database. The rewritten history and its record are in place, but purged content may remain reachable through a kept pack, an alternate object store or a second worktree. Restore the repository from the pre-purge backup, remove the extra reference, and purge again.\n";
        } else {
            echo "Verified: no purged path or pre-purge commit remains in the object database.\n";
        }
        echo $limits . "\n";
        echo "Next: if images are enabled, take a fresh image backup; earlier image backups only restore onto the pre-purge repository backup.\n";
    }
    exit($result['verified'] ? 0 : 1);
} catch (InvalidArgumentException $error) {
    fwrite(STDERR, 'Error: ' . $error->getMessage() . "\n\n" . purgeHistoryUsage());
    exit(1);
} catch (Throwable $error) {
    fwrite(STDERR, 'History purge failed: ' . $error->getMessage() . "\n");
    exit(1);
}
