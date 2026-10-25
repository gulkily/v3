<?php

declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';

$root = dirname(__DIR__);
try {
    $command = $argv[1] ?? 'preview';
    if (in_array($command, ['--help', '-h', 'help'], true)) {
        echo "Usage: ./v3 retraction-sweep preview|run|status|migrate [--json] [--limit=250] [--repository-root=PATH] [--static-html-root=PATH] [--exchange-database-path=PATH] [--state-path=PATH]\n";
        exit(0);
    }
    $options = [];
    foreach (array_slice($argv, 2) as $arg) {
        if ($arg === '--json') { $options['json'] = true; continue; }
        if (!preg_match('/^--(repository-root|exchange-database-path|state-path|static-html-root|limit)=(.+)$/D', $arg, $m)) throw new InvalidArgumentException('Unknown option.');
        $options[$m[1]] = $m[2];
    }
    if (!in_array($command, ['preview', 'status', 'run', 'migrate'], true)) throw new InvalidArgumentException('Use preview, status, run or migrate.');
    if ($command === 'migrate') {
        $repository = $options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: $root . '/state/local_repository');
        $database = getenv('FORUM_DATABASE_PATH') ?: $root . '/state/cache/post_index.sqlite3';
        $result = \ForumRewrite\Retraction\RetractionMigration::run($repository, dirname($database) . '/forum-rewrite.lock');
        if ($options['json'] ?? false) echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
        else {
            echo 'Migration: ' . $result['status'] . '; converted ' . count($result['migrated']) . " records.\n";
            foreach ($result['unresolved'] as $path => $reason) echo $path . ': ' . $reason . "\n";
            echo "Run ./v3 rebuild, then ./v3 retraction-sweep run. Restore missing trusted evidence and rerun migrate for unresolved records.\n";
        }
        exit($result['unresolved'] === [] ? 0 : 1);
    }
    $sweep = \ForumRewrite\Retraction\RetractionSweepFactory::create(
        $root,
        $options['repository-root'] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: $root . '/state/local_repository'),
        $options['static-html-root'] ?? (getenv('FORUM_STATIC_HTML_ROOT') ?: \ForumRewrite\PresentationPathResolver::staticHtmlRoot($root, \ForumRewrite\SiteProfileRegistry::active())),
        $options['exchange-database-path'] ?? null,
        $options['state-path'] ?? null,
    );
    $limit = $options['limit'] ?? '250';
    if (!ctype_digit($limit) || (int) $limit < 1 || (int) $limit > 10000) throw new InvalidArgumentException('Limit must be 1–10000.');
    $result = match ($command) { 'run' => $sweep->run((int) $limit), 'status' => $sweep->state(), default => $sweep->preview((int) $limit) };
    if ($options['json'] ?? false) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    } else {
        echo 'Retraction sweep: ' . $result['status'] . "\n";
        if (isset($result['updated_at'])) echo 'Last run: ' . $result['updated_at'] . "\n";
        if (isset($result['retractions'])) echo 'Retracted posts: ' . $result['retractions'] . "\n";
        if (isset($result['exchange_store'])) echo 'Exchange store: ' . $result['exchange_store'] . "\n";
        $counts = $result['totals'] ?? $result;
        foreach (['examined' => 'Exchanges examined', 'affected' => 'Exchanges to clean', 'cleaned' => 'Exchanges cleaned', 'uncertain' => 'Uncertain exchanges retained'] as $key => $label) {
            if (isset($counts[$key])) echo $label . ': ' . $counts[$key] . "\n";
        }
        if (isset($counts['artifacts_cleaned'])) echo 'Artifacts retired: ' . $counts['artifacts_cleaned'] . "\n";
        elseif (isset($result['artifacts'])) {
            $artifacts = $result['artifacts'];
            if (isset($artifacts['examined'])) echo 'Artifact entries examined: ' . $artifacts['examined'] . "\n";
            if (isset($artifacts['affected'])) echo 'Artifacts to retire: ' . $artifacts['affected'] . "\n";
            if (isset($artifacts['cleaned'])) echo 'Artifacts retired: ' . $artifacts['cleaned'] . "\n";
        }
        if (isset($result['failure'])) echo 'Failure: ' . $result['failure'] . "\n";
        echo 'More work: ' . (($result['remaining'] ?? false) ? 'yes' : 'no') . "\n";
        if ($result['status'] === 'failed') echo "Check canonical evidence, permissions and SQLite readers, then retry with retraction-sweep run.\n";
        elseif ($result['remaining'] ?? false) echo "Run retraction-sweep run to continue, or let the cron worker handle it.\n";
    }
} catch (Throwable $error) {
    fwrite(STDERR, "Retraction sweep failed: " . $error->getMessage() . "\n");
    exit(1);
}
