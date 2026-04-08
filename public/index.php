<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Application;

$projectRoot = dirname(__DIR__);
$repositoryRoot = getenv('FORUM_REPOSITORY_ROOT') ?: ($projectRoot . '/tests/fixtures/parity_minimal_v1');
$databasePath = getenv('FORUM_DATABASE_PATH') ?: $projectRoot . '/state/cache/post_index.sqlite3';

$application = new Application($projectRoot, $repositoryRoot, $databasePath);
$application->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/');
