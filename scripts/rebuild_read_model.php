<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\ReadModel\ReadModelCandidateBuilder;
use ForumRewrite\ReadModel\ReadModelCandidatePromoter;
use ForumRewrite\ReadModel\ReadModelRecovery;
use ForumRewrite\Offline\OfflineSnapshotBootstrap;
use ForumRewrite\PresentationPathResolver;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$arguments = parseRebuildArguments(array_slice($argv, 1));
$defaultRepositoryRoot = LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot);
$repositoryRoot = $arguments['repository_root'] ?? $defaultRepositoryRoot;
$databasePath = $arguments['database_path'] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3'));

if ($arguments['help']) {
    fwrite(STDOUT, rebuildUsage());
    exit(0);
}

if ($arguments['diagnose']) {
    fwrite(STDOUT, ReadModelRecovery::renderDiagnosis($databasePath, ReadModelRecovery::inspect($databasePath)));
    exit(0);
}

if ($arguments['recover']) {
    if (!$arguments['confirm']) {
        fwrite(STDERR, "Recovery archives the live database and all present SQLite sidecars before rebuilding. Re-run with --confirm after reviewing ./v3 rebuild diagnose.\n");
        exit(1);
    }

    try {
        $recoveryRoot = ReadModelRecovery::archiveForRebuild($databasePath);
        fwrite(STDOUT, "Archived the live read-model database and sidecars: {$recoveryRoot}\n");
        fwrite(STDOUT, "Rebuilding a fresh read model from canonical records...\n");
    } catch (Throwable $throwable) {
        fwrite(STDERR, "Read-model recovery could not start: " . $throwable->getMessage() . "\n");
        exit(1);
    }
}

$startedAt = microtime(true);

fwrite(STDOUT, "Starting read-model rebuild.\n");
fwrite(STDOUT, "Repository: {$repositoryRoot}\n");
fwrite(STDOUT, "Database: {$databasePath}\n");

$candidatePath = null;
$sourceCounts = null;
$readModelCounts = null;
$phase = 'scanning source record counts';
$failure = null;
$readModelPromoted = false;
try {
    fwrite(STDOUT, "[1/3] Scanning source record counts...\n");
    $sourceCounts = [
        'posts' => countPostRecords($repositoryRoot),
        'identities' => count(glob($repositoryRoot . '/records/identity/*.txt') ?: []),
        'approval_seeds' => count(glob($repositoryRoot . '/records/approval-seeds/*.txt') ?: []),
    ];
    fwrite(STDOUT, sprintf(
        "[1/3] Source scan complete: %d posts, %d identities, %d approval seeds.\n",
        $sourceCounts['posts'],
        $sourceCounts['identities'],
        $sourceCounts['approval_seeds'],
    ));

    $phase = 'building and validating the read-model candidate';
    fwrite(STDOUT, "[2/3] Building and validating a read-model candidate...\n");
    $candidatePath = (new ReadModelCandidateBuilder(
        $repositoryRoot,
        $databasePath,
        'manual',
        static function (string $message): void {
            fwrite(STDOUT, "[2/3] {$message}\n");
        },
    ))->build();
    fwrite(STDOUT, "[2/3] Read-model candidate is ready.\n");

    $phase = 'promoting the read-model candidate';
    fwrite(STDOUT, "[3/3] Promoting the read-model candidate...\n");
    (new ReadModelCandidatePromoter(
        $repositoryRoot,
        $databasePath,
        static function (string $message): void {
            fwrite(STDOUT, "[3/3] {$message}\n");
        },
    ))->promote($candidatePath);
    fwrite(STDOUT, "[3/3] Read model promoted.\n");
    $readModelPromoted = true;
    $phase = 'reading rebuilt model counts';
    $pdo = new PDO('sqlite:' . $databasePath);
    $readModelCounts = [
        'posts' => (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
        'threads' => (int) $pdo->query('SELECT COUNT(*) FROM threads')->fetchColumn(),
        'profiles' => (int) $pdo->query('SELECT COUNT(*) FROM profiles')->fetchColumn(),
        'activity' => (int) $pdo->query('SELECT COUNT(*) FROM activity')->fetchColumn(),
    ];

    $phase = 'ensuring the initial offline snapshot';
    $profile = SiteProfileRegistry::active();
    $staticHtmlRoot = (string) (getenv('FORUM_STATIC_HTML_ROOT') ?: PresentationPathResolver::staticHtmlRoot($projectRoot, $profile));
    $approvedMembersOnly = FeatureFlagEvaluator::forApplication($repositoryRoot, $projectRoot)
        ->evaluate(FeatureFlagRegistry::APPROVED_MEMBERS_ONLY)
        ->effectiveValue;
    $snapshot = (new OfflineSnapshotBootstrap($staticHtmlRoot))->ensure($databasePath, !$approvedMembersOnly);
    $snapshotMessage = match ($snapshot['status']) {
        'published' => 'published at ' . $snapshot['path'],
        'already_available' => 'already available at ' . $snapshot['path'],
        'unavailable' => 'intentionally unavailable while approved-members-only is enabled',
    };
    fwrite(STDOUT, "Offline snapshot bootstrap: {$snapshotMessage}\n");
} catch (Throwable $throwable) {
    $failure = $throwable;
} finally {
    if ($candidatePath !== null && is_file($candidatePath)) {
        @unlink($candidatePath);
    }
}

if ($failure !== null) {
    fwrite(STDERR, sprintf(
        "Read-model rebuild failed while %s after %.3f seconds.\n",
        $phase,
        microtime(true) - $startedAt,
    ));
    fwrite(STDERR, $failure->getMessage() . "\n");
    if ($readModelPromoted) {
        fwrite(STDERR, "The read model was promoted, but offline snapshot readiness failed. Retry with ./v3 offline publish after correcting the error.\n");
    }
    exit(1);
}

$elapsedSeconds = microtime(true) - $startedAt;

fwrite(STDOUT, "Rebuilt read model at {$databasePath}\n");
fwrite(STDOUT, "Repository: {$repositoryRoot}\n");
fwrite(STDOUT, sprintf("Source records: %d posts, %d identities, %d approval seeds\n", $sourceCounts['posts'], $sourceCounts['identities'], $sourceCounts['approval_seeds']));
fwrite(STDOUT, sprintf("Read model: %d posts, %d threads, %d profiles, %d activity rows\n", $readModelCounts['posts'], $readModelCounts['threads'], $readModelCounts['profiles'], $readModelCounts['activity']));
fwrite(STDOUT, sprintf("Elapsed: %.3f seconds\n", $elapsedSeconds));

function countPostRecords(string $repositoryRoot): int
{
    $postsRoot = $repositoryRoot . '/records/posts';
    if (!is_dir($postsRoot)) {
        return 0;
    }

    $count = 0;
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($postsRoot, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($iterator as $item) {
        if ($item->isFile() && str_ends_with($item->getFilename(), '.txt')) {
            $count += 1;
        }
    }

    return $count;
}

/**
 * @param list<string> $argv
 * @return array{repository_root:?string,database_path:?string,diagnose:bool,recover:bool,confirm:bool,help:bool}
 */
function parseRebuildArguments(array $argv): array
{
    $values = [];
    $diagnose = false;
    $recover = false;
    $confirm = false;
    $help = false;

    foreach ($argv as $argument) {
        if ($argument === '--diagnose') {
            $diagnose = true;
            continue;
        }
        if ($argument === '--recover-sidecars') {
            $recover = true;
            continue;
        }
        if ($argument === '--confirm') {
            $confirm = true;
            continue;
        }
        if ($argument === '-h' || $argument === '--help') {
            $help = true;
            continue;
        }
        $values[] = $argument;
    }

    if ($diagnose && $recover) {
        fwrite(STDERR, "Use either --diagnose or --recover-sidecars, not both.\n");
        exit(1);
    }

    return [
        'repository_root' => $values[0] ?? null,
        'database_path' => $values[1] ?? null,
        'diagnose' => $diagnose,
        'recover' => $recover,
        'confirm' => $confirm,
        'help' => $help,
    ];
}

function rebuildUsage(): string
{
    return "Usage:\n"
        . "  php scripts/rebuild_read_model.php [repository_root] [database_path]\n"
        . "  php scripts/rebuild_read_model.php --diagnose [repository_root] [database_path]\n"
        . "  php scripts/rebuild_read_model.php --recover-sidecars --confirm [repository_root] [database_path]\n";
}
