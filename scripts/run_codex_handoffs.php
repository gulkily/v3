<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Codex\CodexHandoffRunner;
use ForumRewrite\Codex\CodexHandoffStore;
use ForumRewrite\Support\ExecutionLock;

$projectRoot = dirname(__DIR__);
$options = parseCodexHandoffOptions(array_slice($argv, 1));
$databasePath = (string) ($options['database-path'] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3')));
$runnerProjectRoot = (string) ($options['project-root'] ?? $projectRoot);
$codexBin = (string) ($options['codex-bin'] ?? (getenv('FORUM_CODEX_EXECUTABLE') ?: 'codex'));
$limit = max(1, (int) ($options['limit'] ?? 1));
$dryRun = ($options['dry-run'] ?? false) === true;
$quiet = ($options['quiet'] ?? false) === true;

try {
    $pdo = new PDO('sqlite:' . $databasePath);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $store = new CodexHandoffStore($pdo);

    if ($dryRun) {
        emitCodexHandoff($quiet, "Codex handoff dry run\n");
        emitCodexHandoff($quiet, "Database: {$databasePath}\n");
        emitCodexHandoff($quiet, "Project root: {$runnerProjectRoot}\n");
        emitCodexHandoff($quiet, "Codex binary: {$codexBin}\n");
        emitCodexHandoff($quiet, "Approved handoffs: " . countApprovedCodexHandoffs($pdo) . "\n");
        exit(0);
    }

    $runner = new CodexHandoffRunner($store, $runnerProjectRoot, $codexBin);
    $summary = [
        'claimed' => 0,
        'completed' => 0,
        'failed' => 0,
    ];

    $run = static function () use ($store, $runner, $limit, $quiet, &$summary): void {
        $handoffs = $store->claimNextApproved($limit);
        $summary['claimed'] = count($handoffs);
        emitCodexHandoff($quiet, 'Claimed handoffs: ' . count($handoffs) . "\n");

        foreach ($handoffs as $handoff) {
            $handoffId = (string) ($handoff['handoff_id'] ?? '');
            emitCodexHandoff($quiet, "Running handoff: {$handoffId}\n");
            $result = $runner->runApproved($handoff);
            $status = (string) ($result['status'] ?? 'failed');
            if ($status === 'completed') {
                $summary['completed']++;
            } else {
                $summary['failed']++;
            }
            emitCodexHandoff($quiet, 'Result: handoff_id=' . $handoffId . ' status=' . $status . "\n");
        }
    };

    try {
        (new ExecutionLock(dirname($databasePath) . '/forum-rewrite-codex-handoffs.lock', 0))->withExclusiveLock($run);
    } catch (RuntimeException $exception) {
        if (str_contains($exception->getMessage(), 'Timed out waiting for execution lock')) {
            emitCodexHandoff($quiet, "Another Codex handoff runner is already running.\n");
            exit(0);
        }

        throw $exception;
    }

    emitCodexHandoff($quiet, sprintf(
        "Codex handoff run complete: claimed=%d completed=%d failed=%d\n",
        $summary['claimed'],
        $summary['completed'],
        $summary['failed']
    ));
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n\n" . codexHandoffUsageText());
    exit(1);
}

/**
 * @param list<string> $args
 * @return array<string, string|bool|int>
 */
function parseCodexHandoffOptions(array $args): array
{
    $options = [];
    foreach ($args as $arg) {
        if ($arg === '--quiet') {
            $options['quiet'] = true;
            continue;
        }
        if ($arg === '--dry-run') {
            $options['dry-run'] = true;
            continue;
        }
        if ($arg === '-h' || $arg === '--help') {
            fwrite(STDOUT, codexHandoffUsageText());
            exit(0);
        }

        foreach (['limit', 'database-path', 'project-root', 'codex-bin'] as $key) {
            $prefix = '--' . $key . '=';
            if (str_starts_with($arg, $prefix)) {
                $value = substr($arg, strlen($prefix));
                $options[$key] = $key === 'limit' ? max(1, (int) $value) : $value;
                continue 2;
            }
        }

        throw new InvalidArgumentException('Unknown option: ' . $arg);
    }

    return $options;
}

function emitCodexHandoff(bool $quiet, string $message): void
{
    if ($quiet) {
        return;
    }

    fwrite(STDOUT, $message);
}

function countApprovedCodexHandoffs(PDO $pdo): int
{
    $stmt = $pdo->query("SELECT COUNT(*) FROM codex_handoffs WHERE status = 'approved'");
    if ($stmt === false) {
        return 0;
    }

    return (int) $stmt->fetchColumn();
}

function codexHandoffUsageText(): string
{
    return <<<'TEXT'
Usage:
  php scripts/run_codex_handoffs.php [--limit=1] [--dry-run] [--quiet] [--database-path=/path/post_index.sqlite3] [--project-root=/path/project] [--codex-bin=/path/codex]

Runs approved localhost Codex handoffs with `codex exec --json --sandbox workspace-write`.
Use --dry-run to inspect queued approved handoffs without starting Codex.

TEXT;
}
