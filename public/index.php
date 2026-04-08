<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Host\FrontController;

$projectRoot = dirname(__DIR__);
$repositoryRoot = getenv('FORUM_REPOSITORY_ROOT') ?: ($projectRoot . '/tests/fixtures/parity_minimal_v1');
$databasePath = getenv('FORUM_DATABASE_PATH') ?: $projectRoot . '/state/cache/post_index.sqlite3';
$staticHtmlRoot = getenv('FORUM_STATIC_HTML_ROOT') ?: ($projectRoot . '/state/static_html');

$controller = new FrontController($projectRoot, $repositoryRoot, $databasePath, $staticHtmlRoot);
$controller->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/', $_COOKIE);
