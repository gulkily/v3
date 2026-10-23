<?php
/** Internal worker. Parent invokes with an argument array; visitor state is never inherited. */
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
try {
    if (PHP_SAPI !== 'cli' || count($argv) !== 6) throw new RuntimeException('Internal render worker arguments required.');
    [, $project, $repository, $database, $assets, $route] = $argv;
    $_COOKIE = $_GET = $_POST = $_REQUEST = $_SESSION = [];
    $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $route, 'HTTP_USER_AGENT' => 'v3-cache-bot'];
    $html = (new \ForumRewrite\Host\StaticArtifactBuilder($project, $repository, $database, $assets))->renderHighTrafficRoute($route);
    fwrite(STDOUT, $html);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(http_response_code() === 404 ? 4 : 1);
}
