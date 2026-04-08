<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$defaultRepositoryRoot = LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot);
$repositoryRoot = $argv[1] ?? $defaultRepositoryRoot;
$databasePath = $argv[2] ?? ($projectRoot . '/state/cache/post_index.sqlite3');

$builder = new ReadModelBuilder(
    $repositoryRoot,
    $databasePath,
    new CanonicalRecordRepository($repositoryRoot),
);
$builder->rebuild();

fwrite(STDOUT, "Rebuilt read model at {$databasePath}\n");
