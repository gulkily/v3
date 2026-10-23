<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Checkouts\CheckoutManager;

const CHECKOUT_HELP = <<<'HELP'
Usage: ./v3 checkouts <command> [argument] [options]
  add <path>       Register an existing clone (--remote=origin --target=main)
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
        if ($arg === '--sessions-paused') {
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
    $command = $positionals[0] ?? 'list';
    if (count($positionals) > 2 || (in_array($command, ['list', 'preview'], true) && count($positionals) > 1)) {
        throw new RuntimeException('Unexpected argument; run ./v3 checkouts --help.');
    }
    $allowed = ['registry', 'state-dir', 'json', ...match ($command) {
        'add' => ['remote', 'target', 'role'],
        'preview' => ['path'],
        'apply' => ['sessions-paused'],
        default => [],
    }];
    foreach (array_keys($options) as $key) {
        if (!in_array($key, $allowed, true)) {
            throw new RuntimeException('Option --' . $key . ' does not apply to ' . $command . '.');
        }
    }
    $result = (new CheckoutManager($options['registry'] ?? null, $options['state-dir'] ?? null))->run($command, $positionals[1] ?? null, $options);
    if ($options['json'] ?? false) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n";
    } else {
        foreach ($result['checkouts'] ?? [] as $entry) {
            echo $entry['path'] . '  ' . ($entry['snapshot']['branch'] ?? '[' . $entry['role'] . ']')
                . '  ' . ($entry['action'] ?? $entry['remote'] . '/' . $entry['target']) . "\n";
            if (isset($entry['reason'])) {
                echo '  ' . $entry['reason'] . "\n";
            }
            if (isset($entry['snapshot']['target'])) {
                echo '  Source: ' . substr($entry['snapshot']['target'], 0, 12) . "\n";
            }
        }
        if (in_array($command, ['add', 'list'], true)) {
            foreach ($result['checkouts'] as $entry) {
                echo '  Expected source: ' . $entry['source'] . "\n";
            }
        }
        if (in_array($command, ['preview', 'apply'], true)) {
            $counts = [];
            foreach ($result['checkouts'] as $entry) {
                $counts[$entry['action']] = ($counts[$entry['action']] ?? 0) + 1;
            }
            echo implode(' · ', array_map(fn ($action, $count) => $count . ' ' . $action, array_keys($counts), array_values($counts))) . "\n";
        }
        if (isset($result['notice'])) {
            echo $result['notice'] . "\n";
        }
        if ($command === 'preview' && isset($result['preview_id'])) {
            $apply = 'Apply: ./v3 checkouts apply ' . $result['preview_id'] . ' --sessions-paused';
            foreach (['registry', 'state-dir'] as $key) {
                if (isset($options[$key])) {
                    $apply .= ' --' . $key . '=' . escapeshellarg($options[$key]);
                }
            }
            echo $apply . "\n";
            echo "Only selected checked-out branches are considered; other branches, stashes and linked worktrees stay untouched.\n";
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
