<?php
/** Internal worker. Parent invokes with an argument array; visitor state is never inherited. */
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
try {
    if (PHP_SAPI !== 'cli' || !in_array(count($argv), [6, 7], true)) throw new RuntimeException('Internal render worker arguments required.');
    [, $project, $repository, $database, $assets, $route] = $argv;
    $_COOKIE = $_GET = $_POST = $_REQUEST = $_SESSION = [];
    $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $route, 'HTTP_USER_AGENT' => 'v3-cache-bot'];
    $context = \ForumRewrite\Host\HighTrafficPublication::capture($repository);
    if (isset($argv[6]) && !hash_equals($argv[6], \ForumRewrite\Host\HighTrafficPublication::identity($context))) {
        throw new RuntimeException('High-traffic source changed before rendering; retry generation.');
    }
    $database = \ForumRewrite\Host\HighTrafficRenderInput::prepare($context, $database, dirname($assets) . '/model');
    $html = (new \ForumRewrite\Host\StaticArtifactBuilder($project, $repository, $database, $assets))->renderHighTrafficRoute($route);
    \ForumRewrite\Host\HighTrafficPublication::validate($context);
    fwrite(STDOUT, $html);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(http_response_code() === 404 ? 4 : 1);
}
