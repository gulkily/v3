<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Checkouts\CheckoutManager;
use ForumRewrite\Checkouts\CheckoutRenderer;

const CHECKOUT_HELP = <<<'HELP'
Usage: ./v3 checkouts [command] [argument] [options]
  add <path>       Register an existing clone (--remote=origin --target=main)
  overview        Show cached checkout observations (default)
  refresh         Fetch/refresh cached observations without creating an update plan
  preview         Fetch selected sources and save an update preview
  apply <id>      Apply a saved preview (--sessions-paused required)
  list            List registered clones
  remove <path>   Forget a path; never deletes its files
Options:
  --role=development|test|server  Role for add; defaults to development
  --registry=/path/file.json     Shared registry (default: XDG_CONFIG_HOME/v3/checkouts.json)
  --state-dir=/path              Private state (default: XDG_STATE_HOME/v3/checkouts)
  --path=/checkout               Preview one registered path (required for test/server roles)
  --sessions-paused              Assert coding/server sessions are coordinated for apply
  --no-refresh                   Read cached overview without scheduling a worker
  --json                         Machine-readable output
Exit: 0 completed; 1 command/config error; 2 attention or partial results.
Defaults: ~/.config/v3/checkouts.json and ~/.local/state/v3/checkouts.
Coordinate active sessions before apply. Preview fetches refs; other branches stay untouched.
HELP;

$options = ['json' => in_array('--json', $argv, true)];
try {
    $positionals = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (in_array($arg, ['--help', '-h'], true)) {
            echo CHECKOUT_HELP . "\n";
            exit(0);
        }
        if ($arg === '--no-refresh') {
            $options['no-refresh'] = true;
        } elseif ($arg === '--sessions-paused') {
            $options['sessions-paused'] = true;
        } elseif ($arg === '--json') {
            $options['json'] = true;
        } elseif (preg_match('/^--(registry|state-dir|remote|target|role|path)=(.+)$/', $arg, $match)) {
            $options[$match[1]] = $match[2];
        } elseif (str_starts_with($arg, '-')) {
            throw new RuntimeException('Unknown option; run ./v3 checkouts --help.');
        } else {
            $positionals[] = $arg;
        }
    }
    $command = $positionals[0] ?? 'overview';
    if (count($positionals) > 2 || (in_array($command, ['list', 'preview', 'overview', 'refresh'], true) && count($positionals) > 1)) {
        throw new RuntimeException('Unexpected argument; run ./v3 checkouts --help.');
    }
    $allowed = ['registry', 'state-dir', 'json', ...match ($command) {
        'add' => ['remote', 'target', 'role'],
        'preview', 'refresh', '__refresh' => ['path'],
        'overview' => ['path', 'no-refresh'],
        'apply' => ['sessions-paused'],
        default => [],
    }];
    foreach (array_keys($options) as $key) {
        if (!in_array($key, $allowed, true)) {
            throw new RuntimeException('Option --' . $key . ' does not apply to ' . $command . '.');
        }
    }
    $result = (new CheckoutManager($options['registry'] ?? null, $options['state-dir'] ?? null))->run($command, $positionals[1] ?? null, $options);
    if ($command === '__refresh') {
        exit($result['exit_code'] ?? 0);
    }
    if ($options['json'] ?? false) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    } else {
        $width = filter_var(getenv('COLUMNS'), FILTER_VALIDATE_INT) ?: 80;
        $rendered = (new CheckoutRenderer($width))->render($result['checkouts'] ?? []);
        foreach ($rendered['lines'] as $line) {
            echo $line . "\n";
        }
        if ($rendered['abridged']) {
            echo isset($result['result_file']) ? 'Full recovery: ' . $result['result_file'] . "\n" : "Full paths/recovery: use --json.\n";
        }
        if (($result['checkouts'] ?? []) !== [] && in_array($command, ['preview', 'apply', 'overview', 'refresh'], true)) {
            $counts = [];
            foreach ($result['checkouts'] as $entry) {
                $counts[$entry['action']] = ($counts[$entry['action']] ?? 0) + 1;
            }
            echo implode(' · ', array_map(fn ($action, $count) => $count . ' ' . $action, array_keys($counts), array_values($counts))) . "\n";
        }
        if (isset($result['notice'])) {
            if ($command === 'overview' && $result['checkouts'] === []) {
                echo $result['notice'] . "\n";
            } elseif ($command === 'overview') {
                echo ($result['age_seconds'] === null ? 'Unchecked' : 'Checked ' . $result['age_seconds'] . 's ago')
                    . ' · ' . $result['refresh']['state'] . ' · cached observations' . "\n";
                if (in_array($result['refresh']['state'], ['failed', 'deferred'], true)) {
                    echo $result['refresh']['reason'] . "\n";
                }
            } else {
                echo ($command === 'refresh' ? $result['notice'] : ($command === 'preview'
                ? 'Fetched refs; no checked-out files changed. Pause sessions before apply.'
                : 'Other branches, stashes and linked worktrees unchanged. Preview again after recovery.')) . "\n";
            }
        }
        if ($command === 'preview' && isset($result['preview_id'])) {
            $apply = 'Apply: ./v3 checkouts apply ' . $result['preview_id'] . ' --sessions-paused';
            foreach (['registry', 'state-dir'] as $key) {
                if (isset($options[$key])) {
                    $apply .= ' --' . $key . '=' . escapeshellarg($options[$key]);
                }
            }
            echo $apply . "\n";
        }
        if ($command === 'list' && !$result['checkouts']) {
            echo "No clones registered. Use ./v3 checkouts add <path>.\n";
        } elseif ($command === 'remove') {
            echo 'Removed registration: ' . $result['path'] . "\n";
        }
    }
    exit($result['exit_code'] ?? 0);
} catch (Throwable $error) {
    if ($options['json'] ?? false) {
        echo json_encode(['error' => $error->getMessage()], JSON_THROW_ON_ERROR) . "\n";
    } else {
        fwrite(STDERR, 'Error: ' . $error->getMessage() . "\n");
    }
    exit(1);
}
