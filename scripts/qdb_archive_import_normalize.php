<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

/**
 * Transcodes each extracted quote body to UTF-8. Unicode authoring is
 * allowed on the qdb instance, so this step does not filter or rewrite
 * characters for policy conformance - it only recovers the real text,
 * preferring Windows-1252 over strict Latin-1 since the source text is
 * 2003-era IRC chat (smart quotes, em-dashes, etc. live in the
 * Windows-1252 byte range that strict Latin-1 would turn into control
 * characters instead). The one thing this step still rejects is a row
 * that fails to become valid UTF-8 at all, which would be a genuine
 * functional problem downstream (JSON/SQLite/template rendering).
 */
$arguments = parseNormalizeArguments(array_slice($argv, 1));

if ($arguments['help']) {
    fwrite(STDOUT, normalizeUsage());
    exit(0);
}

$inputPath = $arguments['input'];
$outputPath = $arguments['output'];

$input = fopen($inputPath, 'rb');
if ($input === false) {
    fwrite(STDERR, "Unable to open input file: {$inputPath}\n");
    exit(1);
}

$output = fopen($outputPath, 'wb');
if ($output === false) {
    fwrite(STDERR, "Unable to open output file for writing: {$outputPath}\n");
    exit(1);
}

$total = 0;
$alreadyUtf8Count = 0;
$flaggedCount = 0;

while (($line = fgets($input)) !== false) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
    $total++;

    $raw = base64_decode($row['quote_base64'], true);
    if ($raw === false) {
        throw new RuntimeException("quote_id {$row['quote_id']}: invalid base64 in extracted row.");
    }

    $isAlreadyUtf8 = mb_check_encoding($raw, 'UTF-8') && !mb_check_encoding($raw, 'ASCII');
    if ($isAlreadyUtf8) {
        $alreadyUtf8Count++;
        $encodingSource = 'already-utf8';
        $body = $raw;
    } else {
        $encodingSource = 'windows-1252';
        $body = mb_convert_encoding($raw, 'UTF-8', 'Windows-1252');
    }

    $flagged = !mb_check_encoding($body, 'UTF-8');
    if ($flagged) {
        $flaggedCount++;
        $body = null;
    }

    $record = [
        'quote_id' => $row['quote_id'],
        'created_at' => $row['created_at'],
        'date_is_placeholder' => $row['date_is_placeholder'],
        'score' => $row['score'],
        'vote_count' => $row['vote_count'],
        'encoding_source' => $encodingSource,
        'flagged' => $flagged,
        'body' => $body,
        'quote_base64' => $row['quote_base64'],
    ];
    fwrite($output, json_encode($record, JSON_THROW_ON_ERROR) . "\n");
}

fclose($input);
fclose($output);

fwrite(STDOUT, "Normalized {$total} rows -> {$outputPath}\n");
fwrite(STDOUT, "Already-UTF-8 source rows: {$alreadyUtf8Count}\n");
fwrite(STDOUT, "Flagged rows (did not produce valid UTF-8): {$flaggedCount}\n");

if ($flaggedCount > 0) {
    exit(2);
}

/**
 * @param list<string> $argv
 * @return array{input:string,output:string,help:bool}
 */
function parseNormalizeArguments(array $argv): array
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
        'input' => $options['input'] ?? (dirname(__DIR__) . '/state/qdb_archive_import/extracted_quotes.jsonl'),
        'output' => $options['output'] ?? (dirname(__DIR__) . '/state/qdb_archive_import/normalized_quotes.jsonl'),
        'help' => $help,
    ];
}

function normalizeUsage(): string
{
    return "Usage:\n"
        . "  php scripts/qdb_archive_import_normalize.php [--input=path] [--output=path]\n";
}
