<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\PostRecordParser;

/**
 * Writes one canonical post record per normalized quote row directly into
 * a repository's records/posts/ tree - no LocalWriteService call, since
 * that write path is designed for one live action at a time, not a
 * ~15k-row bulk backfill. This script only writes files; batching those
 * into git commits and rebuilding the read model are later stages.
 */
$arguments = parseWritePostsArguments(array_slice($argv, 1));

if ($arguments['help'] || $arguments['repositoryRoot'] === null) {
    fwrite(STDOUT, writePostsUsage());
    exit($arguments['help'] ? 0 : 1);
}

$inputPath = $arguments['input'];
$repositoryRoot = rtrim($arguments['repositoryRoot'], '/');

if (!is_dir($repositoryRoot . '/records/posts')) {
    fwrite(STDERR, "Not a canonical repository (missing records/posts): {$repositoryRoot}\n");
    exit(1);
}

$input = fopen($inputPath, 'rb');
if ($input === false) {
    fwrite(STDERR, "Unable to open input file: {$inputPath}\n");
    exit(1);
}

$parser = new PostRecordParser();
$seenPostIds = [];
$written = 0;
$total = 0;
$strippedControlCharCount = 0;
$strippedControlCharSamples = [];

while (($line = fgets($input)) !== false) {
    $line = trim($line);
    if ($line === '') {
        continue;
    }

    $row = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
    $total++;

    if ($row['flagged']) {
        fwrite(STDERR, "Skipping quote_id {$row['quote_id']}: flagged by Stage 3, needs review.\n");
        continue;
    }

    $quoteId = (int) $row['quote_id'];
    $createdAt = (string) $row['created_at'];
    $postId = buildImportedPostId($quoteId, $createdAt);

    if (isset($seenPostIds[$postId])) {
        throw new RuntimeException("Post-ID collision on {$postId} (quote_id {$quoteId}).");
    }
    $seenPostIds[$postId] = true;

    $body = normalizeLineEndingsAndTrim((string) $row['body']);
    [$body, $strippedCodepoints] = stripUnparseableControlCharacters($body);
    if ($strippedCodepoints !== []) {
        $strippedControlCharCount++;
        if (count($strippedControlCharSamples) < 20) {
            $strippedControlCharSamples[] = $quoteId . ': ' . implode(' ', $strippedCodepoints);
        }
    }

    $contents = "Post-ID: {$postId}\n"
        . "Created-At: {$createdAt}\n"
        . "Board-Tags: general\n"
        . "Imported-Score-Seed: " . (int) $row['score'] . "\n"
        . "Imported-Vote-Count-Seed: " . (int) $row['vote_count'] . "\n"
        . "\n{$body}\n";

    // Parse-before-write: never write a record this app's own parser
    // would reject.
    $record = $parser->parse($contents);
    if ($record->postId !== $postId) {
        throw new RuntimeException("Parsed Post-ID mismatch for quote_id {$quoteId}.");
    }

    $recordPath = $repositoryRoot . '/' . CanonicalPathResolver::datedPost($postId, $createdAt);
    $recordDir = dirname($recordPath);
    if (!is_dir($recordDir) && !mkdir($recordDir, 0777, true) && !is_dir($recordDir)) {
        throw new RuntimeException("Unable to create directory: {$recordDir}");
    }

    if (file_put_contents($recordPath, $contents) === false) {
        throw new RuntimeException("Unable to write: {$recordPath}");
    }

    $written++;
    if ($written % 2000 === 0) {
        fwrite(STDOUT, "Written {$written}...\n");
    }
}

fclose($input);

fwrite(STDOUT, "Processed {$total} rows, wrote {$written} post records under {$repositoryRoot}/records/posts/\n");
fwrite(STDOUT, "Rows with stripped Unicode control/format characters (file format requires this; invisible either way): {$strippedControlCharCount}\n");
foreach ($strippedControlCharSamples as $sample) {
    fwrite(STDOUT, "  - {$sample}\n");
}

function buildImportedPostId(int $quoteId, string $createdAtRfc3339): string
{
    $dt = DateTimeImmutable::createFromFormat('Y-m-d\TH:i:s\Z', $createdAtRfc3339, new DateTimeZone('UTC'));
    if ($dt === false) {
        throw new RuntimeException("Unable to parse created_at: {$createdAtRfc3339}");
    }

    return sprintf('thread-%s-qdb-%d', $dt->format('YmdHis'), $quoteId);
}

function normalizeLineEndingsAndTrim(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);

    return trim($body);
}

/**
 * GenericTextRecordParser unconditionally rejects true Unicode control and
 * format characters (\p{C}) in any canonical record on any instance - this
 * is not the optional authoring policy, it's a hard requirement of the
 * file format itself, so a record containing one of these would simply
 * fail to parse. \n and \t are explicitly exempt and left untouched; only
 * \p{C} characters are removed, nothing else. All of them are
 * non-printable, so nothing a reader would see changes.
 *
 * @return array{0: string, 1: list<string>}
 */
function stripUnparseableControlCharacters(string $body): array
{
    $stripped = [];
    $characters = preg_split('//u', $body, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    $kept = [];
    foreach ($characters as $character) {
        if ($character === "\n" || $character === "\t" || preg_match('/[\p{C}]/u', $character) !== 1) {
            $kept[] = $character;
            continue;
        }

        $stripped[] = sprintf('U+%04X', mb_ord($character, 'UTF-8'));
    }

    return [implode('', $kept), $stripped];
}

/**
 * @param list<string> $argv
 * @return array{input:string,repositoryRoot:?string,help:bool}
 */
function parseWritePostsArguments(array $argv): array
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
        'input' => $options['input'] ?? (dirname(__DIR__) . '/state/qdb_archive_import/normalized_quotes.jsonl'),
        'repositoryRoot' => $options['repository-root'] ?? null,
        'help' => $help,
    ];
}

function writePostsUsage(): string
{
    return "Usage:\n"
        . "  php scripts/qdb_archive_import_write_posts.php --repository-root=path [--input=path]\n"
        . "  --repository-root is required (no default) - this writes real files.\n";
}
