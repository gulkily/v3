<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Host\StaticArtifactReleasePublisher;
use ForumRewrite\ReadModel\ReadModelCandidateBuilder;
use ForumRewrite\ReadModel\ReadModelCandidatePromoter;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$repositoryRoot = $argv[1] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot));
$databasePath = $argv[2] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3'));
$staticHtmlRoot = $argv[3] ?? (getenv('FORUM_STATIC_HTML_ROOT') ?: ($projectRoot . '/state/static_html'));
$startedAt = microtime(true);

$candidatePath = null;
$releasePath = null;
$phase = 'initialization';
$failure = null;

fwrite(STDOUT, "Starting static HTML release build\n");
fwrite(STDOUT, "Repository: {$repositoryRoot}\n");
fwrite(STDOUT, "Database: {$databasePath}\n");
fwrite(STDOUT, "Static artifact root: {$staticHtmlRoot}\n");

try {
    $phase = 'building and validating the read-model candidate';
    fwrite(STDOUT, "[1/4] Building and validating a read-model candidate...\n");
    $candidatePath = (new ReadModelCandidateBuilder(
        $repositoryRoot,
        $databasePath,
        'static_artifact_build',
        static function (string $message): void {
            fwrite(STDOUT, "[1/4] {$message}\n");
        },
    ))->build();
    fwrite(STDOUT, "[1/4] Read-model candidate is ready.\n");

    $phase = 'rendering static HTML and fingerprinted assets';
    fwrite(STDOUT, "[2/4] Rendering static HTML and fingerprinted assets...\n");
    $publisher = new StaticArtifactReleasePublisher($projectRoot, $repositoryRoot, $staticHtmlRoot);
    $releasePath = $publisher->build(
        $candidatePath,
        static function (string $message): void {
            fwrite(STDOUT, "[2/4] {$message}\n");
        },
    );
    $artifactCounts = countReleaseArtifacts($releasePath);
    fwrite(STDOUT, "[2/4] Static release is ready: {$releasePath}\n");
    fwrite(STDOUT, sprintf("Static artifacts: %d HTML files, %d asset files\n", $artifactCounts['html'], $artifactCounts['assets']));

    $phase = 'promoting the read model';
    fwrite(STDOUT, "[3/4] Promoting the read model...\n");
    (new ReadModelCandidatePromoter($repositoryRoot, $databasePath))->promote($candidatePath);
    fwrite(STDOUT, "[3/4] Read model promoted.\n");

    $phase = 'activating the static release';
    fwrite(STDOUT, "[4/4] Activating the static release...\n");
    $publisher->activate($releasePath);
    fwrite(STDOUT, "[4/4] Static release activated.\n");
} catch (Throwable $throwable) {
    fwrite(STDERR, sprintf(
        "Static HTML release build failed while %s after %.3f seconds: %s\n",
        $phase,
        microtime(true) - $startedAt,
        $throwable->getMessage(),
    ));
    $failure = $throwable;
} finally {
    if ($candidatePath !== null && is_file($candidatePath)) {
        @unlink($candidatePath);
    }
}

if ($failure !== null) {
    exit(1);
}

fwrite(STDOUT, "Built and activated static HTML release {$releasePath}\n");
fwrite(STDOUT, sprintf("Elapsed: %.3f seconds\n", microtime(true) - $startedAt));

/**
 * @return array{html:int,assets:int}
 */
function countReleaseArtifacts(string $releasePath): array
{
    $counts = ['html' => 0, 'assets' => 0];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($releasePath, FilesystemIterator::SKIP_DOTS),
    );

    foreach ($iterator as $artifact) {
        if (!$artifact->isFile()) {
            continue;
        }

        $relativePath = substr($artifact->getPathname(), strlen($releasePath) + 1);
        if (str_ends_with($relativePath, '.html')) {
            $counts['html']++;
        }
        if (str_starts_with($relativePath, 'assets/')) {
            $counts['assets']++;
        }
    }

    return $counts;
}
