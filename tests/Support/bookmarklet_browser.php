<?php

declare(strict_types=1);
$root = getenv('BOOKMARKLET_TEST_ROOT');
if (!$root || !in_array(PHP_SAPI, ['cli', 'cli-server'], true)) { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/autoload.php';
$project = dirname(__DIR__, 2);
if (PHP_SAPI === 'cli') {
    if (($argv[1] ?? '') === 'static') {
        $builder = new \ForumRewrite\Host\StaticArtifactBuilder($project, $root . '/repository', $root . '/read.sqlite3', $root . '/static');
        foreach (['/tools/', '/tools/bookmarklets/'] as $route) { $builder->buildSingleRoute($route); }
    } else {
        \ForumRewrite\Support\LocalRepositoryBootstrap::initializeLocalRepository($project, $root . '/repository');
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root . '/repository', $root . '/read.sqlite3', new \ForumRewrite\Canonical\CanonicalRecordRepository($root . '/repository')))->rebuild();
    }
    exit;
}
if (str_starts_with($_SERVER['REQUEST_URI'], '/test-source')) {
    echo '<!doctype html><title>Source 100% café</title><p id="selection">Selected &quot;quote&quot; 50% 😀<br>second line</p><article data-testid="tweet"><div data-testid="User-Name">Author<br>@author</div><div data-testid="tweetText">Tweet 50% 😀<br>second line</div><a href="/status/123"><time>Today</time></a></article>';
    exit;
}
(new \ForumRewrite\Host\FrontController($project, $root . '/repository', $root . '/read.sqlite3', $root . '/static'))->handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_COOKIE);
