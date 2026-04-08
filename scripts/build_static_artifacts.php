<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Host\StaticArtifactBuilder;

$projectRoot = dirname(__DIR__);
$repositoryRoot = $argv[1] ?? (getenv('FORUM_REPOSITORY_ROOT') ?: ($projectRoot . '/tests/fixtures/parity_minimal_v1'));
$databasePath = $argv[2] ?? (getenv('FORUM_DATABASE_PATH') ?: ($projectRoot . '/state/cache/post_index.sqlite3'));
$artifactRoot = $argv[3] ?? (getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: ($projectRoot . '/public'));

$builder = new StaticArtifactBuilder($projectRoot, $repositoryRoot, $databasePath, $artifactRoot);
$builder->build();

fwrite(STDOUT, "Built static HTML artifacts in {$artifactRoot}\n");
