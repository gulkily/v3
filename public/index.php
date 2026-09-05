<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Host\FrontController;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$siteId = SiteProfileRegistry::active()['name'];
$localStateSuffix = $siteId === 'zenmemes' ? '' : '_' . $siteId;
$repositoryRoot = getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot, $siteId);
$databasePath = getenv('FORUM_DATABASE_PATH') ?: $projectRoot . '/state/cache/post_index' . $localStateSuffix . '.sqlite3';
$staticHtmlRoot = getenv('FORUM_STATIC_HTML_ROOT') ?: ($projectRoot . '/state/static_html' . $localStateSuffix);

$controller = new FrontController($projectRoot, $repositoryRoot, $databasePath, $staticHtmlRoot);
$controller->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/', $_COOKIE);
