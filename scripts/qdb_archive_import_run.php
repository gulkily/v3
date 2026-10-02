<?php

declare(strict_types=1);

/**
 * Ties Stages 2-5 together into one runnable, bounded, one-shot import:
 * extract -> normalize -> write posts -> commit in bounded batches (not
 * one commit per quote) -> exactly one read-model rebuild at the end.
 * Each sub-step is still its own standalone script (scripts/qdb_archive_
 * import_{extract,normalize,write_posts}.php); this just runs them in
 * sequence and owns the one thing none of them do yet: git commits.
 *
 * --normalized-input=path skips extraction and normalization entirely and
 * writes/commits/rebuilds straight from an already-produced normalized
 * file. That is the production path: prod has no MySQL dependency and
 * shouldn't gain one just for this one-shot backfill, so extraction runs
 * once wherever the dump lives and only the ~10MB normalized file travels
 * to the production host (see docs/runbooks/qdb_archive_import.md).
 */
$projectRoot = dirname(__DIR__);
$arguments = parseRunArguments(array_slice($argv, 1));

if ($arguments['help'] || $arguments['repositoryRoot'] === null || $arguments['databasePath'] === null) {
    fwrite(STDOUT, runUsage());
    exit($arguments['help'] ? 0 : 1);
}

$repositoryRoot = rtrim($arguments['repositoryRoot'], '/');
$databasePath = $arguments['databasePath'];
$batchSize = $arguments['batchSize'];
$limit = $arguments['limit'];
$workDir = $projectRoot . '/state/qdb_archive_import';
$extractedPath = $workDir . '/extracted_quotes.jsonl';
$normalizedPath = $workDir . '/normalized_quotes.jsonl';

if (!is_dir($repositoryRoot . '/records/posts')) {
    fwrite(STDERR, "Not a canonical repository (missing records/posts): {$repositoryRoot}\n");
    exit(1);
}

$startedAt = microtime(true);
$skippingFetch = $arguments['normalizedInput'] !== null;
$totalSteps = $skippingFetch ? 3 : 4;
$step = 0;

if ($skippingFetch) {
    if (!is_file($arguments['normalizedInput'])) {
        fwrite(STDERR, "--normalized-input file not found: {$arguments['normalizedInput']}\n");
        exit(1);
    }
    $step++;
    fwrite(STDOUT, "[{$step}/{$totalSteps}] Skipping extraction/normalization - using --normalized-input directly.\n");
    $normalizedPath = $arguments['normalizedInput'];
} else {
    $step++;
    fwrite(STDOUT, "[{$step}/{$totalSteps}] Extracting qualifying quotes from the MySQL dump...\n");
    runPhpScript($projectRoot . '/scripts/qdb_archive_import_extract.php', array_filter([
        '--output' => $extractedPath,
        '--database' => $arguments['mysqlDatabase'],
        '--user' => $arguments['mysqlUser'],
        '--password' => $arguments['mysqlPassword'],
        '--host' => $arguments['mysqlHost'],
    ]));

    $step++;
    fwrite(STDOUT, "[{$step}/{$totalSteps}] Normalizing quote bodies to UTF-8...\n");
    runPhpScript($projectRoot . '/scripts/qdb_archive_import_normalize.php', [
        '--input' => $extractedPath,
        '--output' => $normalizedPath,
    ]);
}

$writeInputPath = $normalizedPath;
if ($limit !== null) {
    $writeInputPath = $workDir . '/normalized_quotes.limit' . $limit . '.jsonl';
    writeLimitedCopy($normalizedPath, $writeInputPath, $limit);
    fwrite(STDOUT, "Limited to the first {$limit} rows for this run: {$writeInputPath}\n");
}

$step++;
fwrite(STDOUT, "[{$step}/{$totalSteps}] Writing canonical post records...\n");
runPhpScript($projectRoot . '/scripts/qdb_archive_import_write_posts.php', [
    '--input' => $writeInputPath,
    '--repository-root' => $repositoryRoot,
]);

$step++;
fwrite(STDOUT, "[{$step}/{$totalSteps}] Committing in batches of {$batchSize} and rebuilding the read model...\n");
$paths = newOrChangedPostPaths($repositoryRoot);
fwrite(STDOUT, 'Files to commit: ' . count($paths) . "\n");

$commitCount = commitInBatches($repositoryRoot, $paths, $batchSize);
fwrite(STDOUT, "Commits made: {$commitCount}\n");

if ($commitCount > 0) {
    runPhpScript($projectRoot . '/scripts/rebuild_read_model.php', [], [$repositoryRoot, $databasePath]);
} else {
    fwrite(STDOUT, "Nothing new to commit; skipping rebuild.\n");
}

$elapsedSeconds = microtime(true) - $startedAt;
fwrite(STDOUT, sprintf("Done in %.2fs.\n", $elapsedSeconds));

/**
 * @param array<string, string|null> $options
 * @param list<string> $positional
 */
function runPhpScript(string $scriptPath, array $options, array $positional = []): void
{
    $command = ['php', $scriptPath];
    foreach ($options as $key => $value) {
        if ($value === null || $value === '') {
            continue;
        }
        $command[] = $key . '=' . $value;
    }
    foreach ($positional as $value) {
        $command[] = $value;
    }

    $commandLine = implode(' ', array_map('escapeshellarg', $command));
    passthru($commandLine, $exitCode);
    if ($exitCode !== 0) {
        fwrite(STDERR, "Command failed (exit {$exitCode}): {$commandLine}\n");
        exit($exitCode);
    }
}

function writeLimitedCopy(string $source, string $destination, int $limit): void
{
    $in = fopen($source, 'rb');
    $out = fopen($destination, 'wb');
    if ($in === false || $out === false) {
        throw new RuntimeException('Unable to open files for the limited copy.');
    }

    $count = 0;
    while ($count < $limit && ($line = fgets($in)) !== false) {
        fwrite($out, $line);
        $count++;
    }
    fclose($in);
    fclose($out);
}

/**
 * @return list<string>
 */
function newOrChangedPostPaths(string $repositoryRoot): array
{
    $command = sprintf(
        'git -C %s status --porcelain --untracked-files=all -- records/posts',
        escapeshellarg($repositoryRoot)
    );
    exec($command, $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException('git status failed in ' . $repositoryRoot);
    }

    $paths = [];
    foreach ($output as $line) {
        // Porcelain format: "XY path" (X/Y are 1-char status codes).
        $path = trim(substr($line, 3));
        if ($path !== '') {
            $paths[] = $path;
        }
    }
    sort($paths);

    return $paths;
}

/**
 * @param list<string> $paths
 */
function commitInBatches(string $repositoryRoot, array $paths, int $batchSize): int
{
    if ($paths === []) {
        return 0;
    }

    $batches = array_chunk($paths, $batchSize);
    $total = count($batches);
    foreach ($batches as $index => $batch) {
        $batchNumber = $index + 1;
        $addCommand = sprintf(
            'git -C %s add -- %s',
            escapeshellarg($repositoryRoot),
            implode(' ', array_map('escapeshellarg', $batch))
        );
        runShell($addCommand);

        $message = sprintf('Import qdb archive quotes (batch %d/%d, %d records)', $batchNumber, $total, count($batch));
        $commitCommand = sprintf(
            'git -C %s commit -q -m %s',
            escapeshellarg($repositoryRoot),
            escapeshellarg($message)
        );
        runShell($commitCommand);
        fwrite(STDOUT, "  committed batch {$batchNumber}/{$total} (" . count($batch) . " records)\n");
    }

    return $total;
}

function runShell(string $command): void
{
    exec($command . ' 2>&1', $output, $exitCode);
    if ($exitCode !== 0) {
        throw new RuntimeException("Command failed ({$exitCode}): {$command}\n" . implode("\n", $output));
    }
}

/**
 * @param list<string> $argv
 * @return array{repositoryRoot:?string,databasePath:?string,batchSize:int,limit:?int,mysqlDatabase:?string,mysqlUser:?string,mysqlPassword:?string,mysqlHost:?string,help:bool}
 */
function parseRunArguments(array $argv): array
{
    $help = false;
    $options = [];
    foreach ($argv as $argument) {
        if ($argument === '-h' || $argument === '--help') {
            $help = true;
            continue;
        }
        if (str_starts_with($argument, '--') && str_contains($argument, '=')) {
            [$key, $value] = explode('=', substr($argument, 2), 2);
            $options[$key] = $value;
        }
    }

    return [
        'repositoryRoot' => $options['repository-root'] ?? null,
        'databasePath' => $options['database-path'] ?? null,
        'batchSize' => isset($options['batch-size']) ? max(1, (int) $options['batch-size']) : 500,
        'limit' => isset($options['limit']) ? max(1, (int) $options['limit']) : null,
        'normalizedInput' => $options['normalized-input'] ?? null,
        'mysqlDatabase' => $options['mysql-database'] ?? null,
        'mysqlUser' => $options['mysql-user'] ?? (getenv('QDB_IMPORT_MYSQL_USER') ?: null),
        'mysqlPassword' => $options['mysql-password'] ?? (getenv('QDB_IMPORT_MYSQL_PASSWORD') ?: null),
        'mysqlHost' => $options['mysql-host'] ?? null,
        'help' => $help,
    ];
}

function runUsage(): string
{
    return "Usage:\n"
        . "  php scripts/qdb_archive_import_run.php --repository-root=path --database-path=path\n"
        . "    [--batch-size=500] [--limit=N]\n"
        . "    [--mysql-database=name] [--mysql-user=name] [--mysql-password=secret] [--mysql-host=host]\n"
        . "    | --normalized-input=path\n"
        . "\n"
        . "  --repository-root and --database-path are both required (no defaults) -\n"
        . "  this writes and commits real files, and rebuilds a specific read model.\n"
        . "\n"
        . "  --normalized-input=path skips extraction/normalization entirely and runs\n"
        . "  straight from an already-produced normalized file (the mysql-* options are\n"
        . "  ignored when this is set). This is the production path: see\n"
        . "  docs/runbooks/qdb_archive_import.md.\n"
        . "\n"
        . "  --limit truncates to the first N normalized rows, for a small dry run.\n";
}
