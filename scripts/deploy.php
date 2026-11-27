<?php

declare(strict_types=1);

// Run with ./v3 deploy; all scripts: scripts/README.md.

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Deploy\AppUpdate;

const DEPLOY_HELP = <<<'HELP'
Usage: ./v3 deploy [--check|--status|--history|--rollback=COMMIT|--return-to-main] [--timeout=SECONDS]
  (default)   Fetch origin/main and fast-forward this application checkout.
  --check     Fetch and report how far behind origin/main the checkout is.
  --status    Report local state and the latest attempt without contacting GitHub.
  --history   List the versions this checkout has run, newest first.
  --rollback=COMMIT  Return to a version from --history. Deploy again to move forward.
  --return-to-main  Leave a branch other than main for origin/main. Its commits stop
              running but stay on the server; a server main with its own commits
              is kept as a main-before-return-* branch.
Keeps local edits to tracked files (stash, fast-forward, reapply). A branch
already merged into origin/main is switched back to main first. Refuses,
leaving the checkout unchanged, when the update conflicts with a local edit,
the branch has commits not on origin/main (see --return-to-main), history has
diverged, or another deploy is running.
Pull only: run docs publish or build-static yourself if the release needs them.
Exit: 0 deployed, rolled back, returned, up to date or checked; 1 refused or failed; 2 usage error.
HELP;

$mode = 'deploy';
$timeout = 120;
$rollbackTo = '';
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        echo DEPLOY_HELP . "\n";
        exit(0);
    } elseif ($arg === '--check') {
        $mode = 'check';
    } elseif ($arg === '--status') {
        $mode = 'status';
    } elseif ($arg === '--history') {
        $mode = 'history';
    } elseif ($arg === '--return-to-main') {
        $mode = 'return';
    } elseif (preg_match('/^--rollback=([0-9a-f]{7,64})$/', $arg, $m) === 1) {
        $mode = 'rollback';
        $rollbackTo = $m[1];
    } elseif (preg_match('/^--timeout=(\d+)$/', $arg, $m) === 1 && (int) $m[1] > 0) {
        $timeout = (int) $m[1];
    } else {
        fwrite(STDERR, "Unknown option: {$arg}\n\n" . DEPLOY_HELP . "\n");
        exit(2);
    }
}

$update = AppUpdate::forProject(dirname(__DIR__));

$printState = static function (array $state): void {
    echo 'Commit: ' . ($state['commit'] ?? 'unavailable') . "\n";
    echo 'Branch: ' . ($state['branch'] ?? 'detached or unavailable') . "\n";
    echo 'Local-Changes: ' . ($state['local_changes'] ?? 'unavailable') . "\n";
    echo 'Behind: ' . ($state['behind'] ?? 'unknown') . "\n";
    echo 'Ahead: ' . ($state['ahead'] ?? 'unknown') . "\n";
    $stat = $state['incoming_stat'] ?? null;
    if ($stat !== null) {
        echo 'Incoming-Size: ' . $stat['files'] . ' files changed, +' . $stat['insertions'] . ' -' . $stat['deletions'] . "\n";
    }
    foreach ($state['incoming_commits'] ?? [] as $commit) {
        echo 'Incoming-Commit: ' . substr($commit['commit'], 0, 12) . ' ' . $commit['date'] . ' ' . $commit['subject'] . "\n";
    }
    if (($state['incoming_more'] ?? 0) > 0) {
        echo 'Incoming-More: ' . $state['incoming_more'] . "\n";
    }
    foreach ($state['incoming_local_overlap'] ?? [] as $file) {
        echo 'Incoming-Changes-Local-Edit: ' . $file . "\n";
    }
    echo 'Last-Checked-At: ' . ($state['last_checked_at'] ?? 'never') . "\n";
    echo 'Busy: ' . ($state['busy'] ? 'yes' : 'no') . "\n";
};

if ($mode === 'status') {
    $state = $update->inspect();
    $printState($state);
    foreach (AppUpdate::latestAttemptLines($state['latest']) as $line) {
        echo $line . "\n";
    }
    exit(0);
}

if ($mode === 'history') {
    $running = $update->inspect()['commit'] ?? null;
    echo 'Running: ' . ($running === null ? 'unavailable' : substr($running, 0, 12)) . "\n";
    foreach ($update->history(PHP_INT_MAX) as $entry) {
        echo 'Version: ' . $entry['deployed_at'] . ' ' . $entry['action'] . ' ' . substr($entry['new_commit'], 0, 12)
            . ($entry['subject'] === '' ? '' : ' ' . $entry['subject']) . "\n";
    }
    exit(0);
}

if ($mode === 'check') {
    $result = $update->check($timeout);
    echo 'Outcome: ' . $result['outcome'] . "\n";
    if ($result['reason'] !== null) {
        echo 'Reason: ' . $result['reason'] . "\n";
    }
    echo 'Message: ' . $result['message'] . "\n";
    $printState($result['state']);
    exit($result['outcome'] === 'checked' ? 0 : 1);
}

$user = function_exists('posix_getpwuid') && function_exists('posix_geteuid') ? (posix_getpwuid(posix_geteuid())['name'] ?? '') : '';
$actor = 'cli' . ($user !== '' ? ':' . $user : '');
if ($mode === 'rollback') {
    $preview = $update->rollbackPreview($rollbackTo);
    foreach ($preview['commits'] as $commit) {
        echo 'Undoes-Commit: ' . substr($commit['commit'], 0, 12) . ' ' . $commit['date'] . ' ' . $commit['subject'] . "\n";
    }
    if ($preview['more'] > 0) {
        echo 'Undoes-More: ' . $preview['more'] . "\n";
    }
    foreach ($preview['warnings'] as $warning) {
        echo 'Warning: ' . $warning . "\n";
    }
}
if ($mode === 'return') {
    $preview = $update->returnToMainPreview();
    foreach ($preview['commits'] as $commit) {
        echo 'Stops-Running: ' . substr($commit['commit'], 0, 12) . ' ' . $commit['date'] . ' ' . $commit['subject'] . "\n";
    }
    if ($preview['more'] > 0) {
        echo 'Stops-Running-More: ' . $preview['more'] . "\n";
    }
}
$result = match ($mode) {
    'rollback' => $update->rollback($rollbackTo, $actor, AppUpdate::MODE_CLI, $timeout),
    'return' => $update->returnToMain($actor, AppUpdate::MODE_CLI, $timeout),
    default => $update->deploy($actor, AppUpdate::MODE_CLI, $timeout),
};
echo 'Outcome: ' . $result['outcome'] . "\n";
if ($result['reason'] !== null) {
    echo 'Reason: ' . $result['reason'] . "\n";
}
echo 'Old-Commit: ' . ($result['old_commit'] ?? '-') . "\n";
echo 'New-Commit: ' . ($result['new_commit'] ?? '-') . "\n";
echo 'Message: ' . $result['message'] . "\n";
exit(in_array($result['outcome'], ['deployed', 'rolled_back', 'returned', 'up_to_date'], true) ? 0 : 1);
