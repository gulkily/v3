<?php

declare(strict_types=1);

// Run with ./v3 approval private-access-report; all scripts: scripts/README.md.

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Security\PrivateKeyAccess;

function privateAccessReportUsage(): string
{
    return <<<'TEXT'
Usage:
  ./v3 approval private-access-report [--repository-root=/path/repository] [--database-path=/path/read-model.sqlite3] [--public-only] [--json]

Read-only. Lists every approved key with why it may read its account's private
messages: root, first (the username's first key), own_account (approved by a
same-username key with access), operator (approved by an operator), confirmed,
or public_only. --public-only lists only the keys that would lose access.
Uses FORUM_REPOSITORY_ROOT / FORUM_DATABASE_PATH or the normal local paths.
Exit 0 means the report ran; exit 1 means an input/error.

TEXT;
}

try {
    $options = [];
    foreach (array_slice($argv, 1) as $argument) {
        if (in_array($argument, ['--help', '-h'], true)) {
            fwrite(STDOUT, privateAccessReportUsage());
            exit(0);
        }
        if ($argument === '--json' || $argument === '--public-only') {
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
    $rows = (new PrivateKeyAccess($pdo, $repositoryRoot))->report();
    if (isset($options['public-only'])) {
        $rows = array_values(array_filter($rows, static fn (array $row): bool => $row['reason'] === PrivateKeyAccess::PUBLIC_ONLY));
    }
    $stale = (new ReadModelStaleMarker($databasePath))->exists();

    if (isset($options['json'])) {
        fwrite(STDOUT, json_encode(['database_path' => $databasePath, 'stale' => $stale, 'keys' => $rows], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
        exit(0);
    }

    $clean = static fn (mixed $value): string => preg_replace('/[\x00-\x1f\x7f]/', ' ', (string) $value) ?? '';
    fwrite(STDOUT, "Private access report (read-only)\nDatabase: " . $clean($databasePath) . "\n");
    if ($stale) {
        fwrite(STDOUT, "Warning: read model has a stale marker; results may be out of date. Run ./v3 status.\n");
    }
    $counts = array_count_values(array_column($rows, 'reason'));
    ksort($counts);
    foreach ($counts as $reason => $count) {
        fwrite(STDOUT, $reason . ': ' . $count . "\n");
    }
    $columns = ['reason', 'username_token', 'identity_id', 'approved_by_identity_id'];
    fwrite(STDOUT, "\n" . implode("\t", $columns) . "\n");
    foreach ($rows as $row) {
        fwrite(STDOUT, implode("\t", array_map(static fn (string $key): string => $clean($row[$key] ?? ''), $columns)) . "\n");
    }
} catch (Throwable $exception) {
    fwrite(STDERR, 'Error: ' . $exception->getMessage() . "\n\n" . privateAccessReportUsage());
    exit(1);
}
