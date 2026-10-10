<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Support\ApprovalAudit;

function approvalAuditUsage(): string
{
    return <<<'TEXT'
Usage:
  ./v3 approval audit [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--review-only] [--json]

Read-only audit of currently approved keys and their attributed approvers.
Uses FORUM_REPOSITORY_ROOT / FORUM_DATABASE_PATH or the normal local paths.
Does not initialize a repository, rebuild a database, or change approvals.
Review candidates are cross-username approvals on currently multi-key accounts
and unknown attribution; they are not confirmed historical policy violations.
--review-only filters rows; summary counts always describe the complete audit.
Exit 0 means the audit ran (even with candidates); exit 1 means an input/error.

TEXT;
}

try {
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (in_array($argument, ['--help', '-h'], true)) {
            fwrite(STDOUT, approvalAuditUsage());
            exit(0);
        }
        if (in_array($argument, ['--json', '--review-only'], true)) {
            $options[substr($argument, 2)] = true;
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
    $repositoryRoot = realpath($options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: $projectRoot . '/state/local_repository'));
    $databasePath = realpath($options['database-path'] ?? (getenv('FORUM_DATABASE_PATH') ?: $projectRoot . '/state/cache/post_index.sqlite3'));
    if ($repositoryRoot === false || !is_dir($repositoryRoot . '/records')) {
        throw new RuntimeException('Repository with a records directory must already exist; use --repository-root.');
    }
    if ($databasePath === false || !is_file($databasePath)) {
        throw new RuntimeException('Read-model database must already exist; use --database-path. Audit does not rebuild it.');
    }

    $pdo = new PDO('sqlite:file:' . str_replace('%2F', '/', rawurlencode($databasePath)) . '?mode=ro');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA query_only = ON');
    $pdo->beginTransaction();
    $report = (new ApprovalAudit())->collect($pdo, $repositoryRoot);
    $pdo->rollBack();
    $report['database_path'] = $databasePath;
    if ((new ReadModelStaleMarker($databasePath))->exists()) {
        $report['warnings'][] = 'Read model has a stale marker; results may be out of date. Run ./v3 status.';
    }
    $report['review_only'] = isset($options['review-only']);
    if ($report['review_only']) {
        $report['rows'] = array_values(array_filter($report['rows'], static fn (array $row): bool => $row['review_candidate']));
    }
    if (isset($options['json'])) {
        fwrite(STDOUT, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    }

    $clean = static fn (mixed $value): string => preg_replace('/[\x00-\x1f\x7f]/', ' ', (string) $value) ?? '';
    fwrite(STDOUT, "Approval audit (read-only)\n");
    fwrite(STDOUT, 'Database: ' . $clean($databasePath) . "\n");
    fwrite(STDOUT, $report['scope'] . "\n" . $report['limitations'] . "\n");
    foreach ($report['warnings'] as $warning) {
        fwrite(STDOUT, 'Warning: ' . $clean($warning) . "\n");
    }
    foreach (['approved_keys', 'approved_usernames', 'review_candidates'] as $key) {
        fwrite(STDOUT, $key . ': ' . $report[$key] . "\n");
    }
    foreach ($report['counts'] as $category => $count) {
        fwrite(STDOUT, $category . ': ' . $count . "\n");
    }
    $columns = ['category', 'username_token', 'identity_id', 'approved_key_count', 'approver_username', 'approver_identity_id', 'profile_slug', 'reason'];
    fwrite(STDOUT, "\n" . implode("\t", $columns) . "\n");
    foreach ($report['rows'] as $row) {
        fwrite(STDOUT, implode("\t", array_map(static fn (string $key): string => $clean($row[$key]), $columns)) . "\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n\n" . approvalAuditUsage());
    exit(1);
}
