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

$candidatePath = null;
try {
    $candidatePath = (new ReadModelCandidateBuilder($repositoryRoot, $databasePath, 'static_artifact_build'))->build();
    $publisher = new StaticArtifactReleasePublisher($projectRoot, $repositoryRoot, $staticHtmlRoot);
    $releasePath = $publisher->build($candidatePath);
    (new ReadModelCandidatePromoter($repositoryRoot, $databasePath))->promote($candidatePath);
    $publisher->activate($releasePath);
} finally {
    if ($candidatePath !== null && is_file($candidatePath)) {
        @unlink($candidatePath);
    }
}

fwrite(STDOUT, "Built and activated static HTML release {$releasePath}\n");
