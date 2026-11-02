<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Host\FrontController;
use ForumRewrite\PresentationPathResolver;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$profile = SiteProfileRegistry::active();
$identityRequest = ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) === \ForumRewrite\Support\FrontendInstance::PATH;
$repositoryRoot = \ForumRewrite\Support\DeploymentSettings::value('FORUM_REPOSITORY_ROOT', $projectRoot) ?: ($identityRequest
    ? $projectRoot . '/state/local_repository'
    : LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot));
$databasePath = \ForumRewrite\Support\DeploymentSettings::value('FORUM_DATABASE_PATH', $projectRoot) ?: LocalRepositoryBootstrap::defaultDatabasePath($projectRoot);
$staticHtmlRoot = \ForumRewrite\Support\DeploymentSettings::value('FORUM_STATIC_HTML_ROOT', $projectRoot) ?: PresentationPathResolver::staticHtmlRoot($projectRoot, $profile);

$controller = new FrontController($projectRoot, $repositoryRoot, $databasePath, $staticHtmlRoot, \ForumRewrite\Support\DeploymentSettings::value('FORUM_PUBLIC_ARTIFACT_ROOT', $projectRoot) ?: null);
$controller->handle($_SERVER['REQUEST_METHOD'] ?? 'GET', $_SERVER['REQUEST_URI'] ?? '/', $_COOKIE);
