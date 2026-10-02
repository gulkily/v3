<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

$arguments = parseExtractArguments(array_slice($argv, 1));

if ($arguments['help']) {
    fwrite(STDOUT, extractUsage());
    exit(0);
}

$outputPath = $arguments['output'];
$databaseName = $arguments['database'];
$dbUser = $arguments['user'];
$dbPassword = $arguments['password'];
$dbHost = $arguments['host'];

$outputDir = dirname($outputPath);
if (!is_dir($outputDir) && !mkdir($outputDir, 0777, true) && !is_dir($outputDir)) {
    fwrite(STDERR, "Unable to create output directory: {$outputDir}\n");
    exit(1);
}

$startedAt = microtime(true);
fwrite(STDOUT, "Connecting to {$dbHost}/{$databaseName} as {$dbUser}...\n");

try {
    // charset=latin1 matches the quotes table's real column charset, so MySQL
    // hands back the original bytes untouched instead of converting them -
    // Stage 3 owns the latin1-to-UTF-8 decision, not this extraction step.
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$databaseName};charset=latin1",
        $dbUser,
        $dbPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (Throwable $throwable) {
    fwrite(STDERR, "Unable to connect: " . $throwable->getMessage() . "\n");
    exit(1);
}

$countStmt = $pdo->query('SELECT COUNT(*) FROM quotes WHERE approved = 1 AND spam = 0 AND deleted_flag = 0');
$expectedCount = (int) $countStmt->fetchColumn();
fwrite(STDOUT, "Qualifying rows to extract: {$expectedCount}\n");

$stmt = $pdo->prepare(
    'SELECT quote_id, quote, add_timestamp, score, vote_count
     FROM quotes
     WHERE approved = 1 AND spam = 0 AND deleted_flag = 0
     ORDER BY quote_id'
);
$stmt->execute();

$output = fopen($outputPath, 'wb');
if ($output === false) {
    fwrite(STDERR, "Unable to open output file for writing: {$outputPath}\n");
    exit(1);
}

$written = 0;
$placeholderDateCount = 0;
while (($row = $stmt->fetch(PDO::FETCH_ASSOC)) !== false) {
    $quoteId = (int) $row['quote_id'];
    $isPlaceholderDate = $row['add_timestamp'] === null;
    if ($isPlaceholderDate) {
        $placeholderDateCount++;
    }

    $record = [
        'quote_id' => $quoteId,
        'created_at' => $isPlaceholderDate
            ? placeholderCreatedAt($quoteId)
            : reformatTimestamp((string) $row['add_timestamp']),
        'date_is_placeholder' => $isPlaceholderDate,
        'score' => (int) $row['score'],
        'vote_count' => (int) $row['vote_count'],
        'quote_base64' => base64_encode((string) $row['quote']),
    ];
    fwrite($output, json_encode($record, JSON_THROW_ON_ERROR) . "\n");
    $written++;
    if ($written % 2000 === 0) {
        fwrite(STDOUT, "Extracted {$written}/{$expectedCount}...\n");
    }
}
fclose($output);

fwrite(STDOUT, "Rows with no recorded add_timestamp (placeholder date assigned): {$placeholderDateCount}\n");

$elapsedSeconds = microtime(true) - $startedAt;
fwrite(STDOUT, sprintf("Extracted %d rows to %s in %.2fs\n", $written, $outputPath, $elapsedSeconds));

if ($written !== $expectedCount) {
    fwrite(STDERR, "Warning: extracted row count ({$written}) does not match the pre-query count ({$expectedCount}).\n");
    exit(1);
}

function reformatTimestamp(string $mysqlDatetime): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $mysqlDatetime, new DateTimeZone('UTC'));
    if ($dt === false) {
        throw new RuntimeException("Unable to parse add_timestamp: {$mysqlDatetime}");
    }

    return $dt->format('Y-m-d\TH:i:s\Z');
}

/**
 * ~45% of qualifying quotes have no recorded add_timestamp at all (every
 * quote_id <= 20162 in the source dump) - there is no other column that
 * recovers their real post date. Per explicit decision, these get an
 * honest placeholder: a fixed date safely earlier than the earliest real
 * add_timestamp in the dump (2003-06-13), offset by quote_id seconds so
 * they still sort in their known relative (submission) order instead of
 * colliding on one identical instant. This is a marker, not a captured
 * date - callers must use date_is_placeholder to tell the difference.
 */
function placeholderCreatedAt(int $quoteId): string
{
    $base = new DateTimeImmutable('2003-01-01T00:00:00Z');

    return $base->modify("+{$quoteId} seconds")->format('Y-m-d\TH:i:s\Z');
}

/**
 * @param list<string> $argv
 * @return array{output:string,database:string,user:string,password:string,host:string,help:bool}
 */
function parseExtractArguments(array $argv): array
{
    $values = [];
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
            continue;
        }
        $values[] = $argument;
    }

    return [
        'output' => $options['output'] ?? (dirname(__DIR__) . '/state/qdb_archive_import/extracted_quotes.jsonl'),
        'database' => $options['database'] ?? 'qdb_import_check',
        'user' => $options['user'] ?? (getenv('QDB_IMPORT_MYSQL_USER') ?: 'qdb_import'),
        'password' => $options['password'] ?? (getenv('QDB_IMPORT_MYSQL_PASSWORD') ?: ''),
        'host' => $options['host'] ?? 'localhost',
        'help' => $help,
    ];
}

function extractUsage(): string
{
    return "Usage:\n"
        . "  php scripts/qdb_archive_import_extract.php [--output=path] [--database=name] [--user=name] [--password=secret] [--host=host]\n"
        . "  Credentials can also come from QDB_IMPORT_MYSQL_USER / QDB_IMPORT_MYSQL_PASSWORD env vars.\n";
}
