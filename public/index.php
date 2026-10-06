<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Host\FrontController;
use ForumRewrite\PresentationPathResolver;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$profile = SiteProfileRegistry::active();
$repositoryRoot = getenv('FORUM_REPOSITORY_ROOT') ?: LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot);
$databasePath = getenv('FORUM_DATABASE_PATH') ?: LocalRepositoryBootstrap::defaultDatabasePath($projectRoot);
$staticHtmlRoot = getenv('FORUM_STATIC_HTML_ROOT') ?: PresentationPathResolver::staticHtmlRoot($projectRoot, $profile);

$controller = new FrontController($projectRoot, $repositoryRoot, $databasePath, $staticHtmlRoot);
$controller->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/', $_COOKIE);
