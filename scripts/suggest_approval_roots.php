<?php

declare(strict_types=1);

// Run with ./v3 approval suggest-roots; all scripts: scripts/README.md.

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Support\ApprovalRootSuggestions;

function suggestRootsUsage(): string
{
    return <<<'TEXT'
Usage:
  ./v3 approval suggest-roots [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--json]

Read-only. After importing a site without its approval seeds, suggests the
fewest keys to seed as roots so the recorded approvals count again.
Uses FORUM_REPOSITORY_ROOT / FORUM_DATABASE_PATH or the normal local paths.
Does not initialize a repository, rebuild a database, or change approvals;
it prints ./v3 approval seed commands for you to review and run.
Evidence: operator_receipt (a receipt says this key approved as an operator),
unverified (no receipt; could be a root or a forged approval),
receipt_says_not_operator (its own approver is missing from these records).
Exit 0 means the analysis ran; exit 1 means an input/error.

TEXT;
}

try {
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (in_array($argument, ['--help', '-h'], true)) {
            fwrite(STDOUT, suggestRootsUsage());
            exit(0);
        }
        if ($argument === '--json') {
            $options['json'] = true;
            continue;
        }
        if (preg_match('/^--(repository-root|database-path)=(.+)$/D', $argument, $match) === 1) {
            $options[$match[1]] = $match[2];
            continue;
        }
        throw new InvalidArgumentException('Unknown or empty option: ' . $argument);
    }

    $projectRoot = dirname(__DIR__);
    // Do not call LocalRepositoryBootstrap::defaultRepositoryRoot: it may initialize state.
    $repositoryRoot = realpath($options['repository-root'] ?? (\ForumRewrite\Support\DeploymentSettings::value('FORUM_REPOSITORY_ROOT') ?: $projectRoot . '/state/local_repository'));
    $databasePath = realpath($options['database-path'] ?? (\ForumRewrite\Support\DeploymentSettings::value('FORUM_DATABASE_PATH') ?: $projectRoot . '/state/cache/post_index.sqlite3'));
    if ($repositoryRoot === false || !is_dir($repositoryRoot . '/records')) {
        throw new RuntimeException('Repository with a records directory must already exist; use --repository-root.');
    }
    if ($databasePath === false || !is_file($databasePath)) {
        throw new RuntimeException('Read-model database must already exist; use --database-path. This command does not rebuild it.');
    }

    $pdo = new PDO('sqlite:file:' . str_replace('%2F', '/', rawurlencode($databasePath)) . '?mode=ro');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA query_only = ON');
    $pdo->beginTransaction();
    $report = (new ApprovalRootSuggestions())->collect($pdo, $repositoryRoot);
    $pdo->rollBack();
    $report['database_path'] = $databasePath;
    if ((new ReadModelStaleMarker($databasePath))->exists()) {
        $report['warnings'][] = 'Read model has a stale marker; results may be out of date. Run ./v3 status.';
    }
    $paths = isset($options['repository-root']) || isset($options['database-path'])
        ? ' ' . escapeshellarg($repositoryRoot) . ' ' . escapeshellarg($databasePath) : '';
    foreach ($report['suggestions'] as &$suggestion) {
        $suggestion['seed_command'] = './v3 approval seed ' . $suggestion['identity_id'] . " 'restored root'" . $paths;
    }
    unset($suggestion);

    if (isset($options['json'])) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    }

    $clean = static fn (mixed $value): string => preg_replace('/[\x00-\x1f\x7f]/', ' ', (string) $value) ?? '';
    fwrite(STDOUT, "Approval root suggestions (read-only)\n");
    fwrite(STDOUT, 'Database: ' . $clean($databasePath) . "\n");
    fwrite(STDOUT, $report['scope'] . "\n" . $report['limitations'] . "\n");
    foreach ($report['warnings'] as $warning) {
        fwrite(STDOUT, 'Warning: ' . $clean($warning) . "\n");
    }
    foreach (['existing_seeds', 'approval_edges', 'keys_in_graph', 'sources_already_covered', 'suggested_roots', 'keys_restored', 'keys_without_approval_path'] as $key) {
        fwrite(STDOUT, $key . ': ' . $report[$key] . "\n");
    }
    $columns = ['evidence', 'username', 'identity_id', 'restores', 'approvals_given', 'operator_receipts', 'first_approval_at', 'reason'];
    fwrite(STDOUT, "\n" . implode("\t", $columns) . "\n");
    foreach ($report['suggestions'] as $row) {
        fwrite(STDOUT, implode("\t", array_map(static fn (string $key): string => $clean($row[$key] ?? ''), $columns)) . "\n");
    }
    if ($report['suggestions'] !== []) {
        fwrite(STDOUT, "\nReview, then run:\n");
        foreach ($report['suggestions'] as $row) {
            fwrite(STDOUT, '  ' . $clean($row['seed_command']) . "\n");
        }
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n\n" . suggestRootsUsage());
    exit(1);
}
