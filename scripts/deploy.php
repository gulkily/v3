<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Deploy\AppUpdate;

const DEPLOY_HELP = <<<'HELP'
Usage: ./v3 deploy [--check|--status] [--timeout=SECONDS]
  (default)   Fetch origin/main and fast-forward this application checkout.
  --check     Fetch and report how far behind origin/main the checkout is.
  --status    Report local state and the latest attempt without contacting GitHub.
Keeps local edits to tracked files (stash, fast-forward, reapply). Refuses,
leaving the checkout unchanged, when the update conflicts with a local edit,
the branch is not main, history has diverged, or another deploy is running.
Pull only: run docs publish or build-static yourself if the release needs them.
Exit: 0 deployed, up to date or checked; 1 refused or failed; 2 usage error.
HELP;

$mode = 'deploy';
$timeout = 120;
foreach (array_slice($argv, 1) as $arg) {
    if ($arg === '--help' || $arg === '-h') {
        echo DEPLOY_HELP . "\n";
        exit(0);
    } elseif ($arg === '--check') {
        $mode = 'check';
    } elseif ($arg === '--status') {
        $mode = 'status';
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
    echo 'Last-Checked-At: ' . ($state['last_checked_at'] ?? 'never') . "\n";
    echo 'Busy: ' . ($state['busy'] ? 'yes' : 'no') . "\n";
};

if ($mode === 'status') {
    $state = $update->inspect();
    $printState($state);
    $latest = $state['latest'];
    echo 'Latest-Attempt: ' . ($latest === null ? 'none' : ($latest['Finished-At'] ?? '-') . ' ' . ($latest['Outcome'] ?? '-') . ' (' . ($latest['Mode'] ?? '-') . ')') . "\n";
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
$result = $update->deploy('cli' . ($user !== '' ? ':' . $user : ''), AppUpdate::MODE_CLI, $timeout);
echo 'Outcome: ' . $result['outcome'] . "\n";
if ($result['reason'] !== null) {
    echo 'Reason: ' . $result['reason'] . "\n";
}
echo 'Old-Commit: ' . ($result['old_commit'] ?? '-') . "\n";
echo 'New-Commit: ' . ($result['new_commit'] ?? '-') . "\n";
echo 'Message: ' . $result['message'] . "\n";
exit(in_array($result['outcome'], ['deployed', 'up_to_date'], true) ? 0 : 1);
