<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Checkouts\CheckoutManager;

const CHECKOUT_HELP = <<<'HELP'
Usage: ./v3 checkouts <command> [argument] [options]
  add <path>       Register an existing clone (--remote=origin --target=main)
  preview         Fetch selected sources and save an update preview
  list            List registered clones
  remove <path>   Forget a path; never deletes its files
Options:
  --role=development|test|server  Role for add; defaults to development
  --registry=/path/file.json     Shared registry (default: XDG_CONFIG_HOME/v3/checkouts.json)
  --state-dir=/path              Private state (default: XDG_STATE_HOME/v3/checkouts)
  --path=/checkout              Preview one registered path (required for test/server roles)
  --json                        Machine-readable output
HELP;

$options = ['json' => in_array('--json', $argv, true)];
try {
    $positionals = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (in_array($arg, ['--help', '-h'], true)) {
            echo CHECKOUT_HELP . "\n";
            exit(0);
        }
        if ($arg === '--json') {
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
        if (isset($result['notice'])) {
            echo $result['notice'] . "\n";
        }
        if (isset($result['preview_id'])) {
            echo 'Apply: ./v3 checkouts apply ' . $result['preview_id'] . ' --sessions-paused' . "\n";
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
