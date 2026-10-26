<?php
/** Internal worker. Parent invokes with an argument array; visitor state is never inherited. */
declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';
try {
    if (PHP_SAPI !== 'cli' || !in_array(count($argv), [7, 8], true)) throw new RuntimeException('Internal render worker arguments required.');
    [, $project, $repository, $database, $assets, $route, $profile] = $argv;
    // Apache SetEnv values are request-scoped and need not reach CLI children.
    // Require the parent's resolved profile instead of silently using a fallback.
    if (!isset(\ForumRewrite\SiteProfileRegistry::all()[$profile])) throw new RuntimeException('Unknown shared render site profile.');
    if (!putenv('FORUM_SITE_ID=' . $profile)) throw new RuntimeException('Cannot set shared render site profile.');
    $_COOKIE = $_GET = $_POST = $_REQUEST = $_SESSION = [];
    $_SERVER = ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $route, 'HTTP_USER_AGENT' => 'v3-cache-bot'];
    $report = static function (string $stage): void { fwrite(STDERR, 'High-traffic stage: ' . $stage . "\n"); };
    $report('validating retraction evidence (no full-record scan)');
    $context = \ForumRewrite\Host\HighTrafficPublication::captureForRender($repository);
    if (isset($argv[7]) && !hash_equals($argv[7], \ForumRewrite\Host\HighTrafficPublication::identity($context))) {
        throw new RuntimeException('High-traffic source changed before rendering; retry generation.');
    }
    $report('preparing isolated render input');
    $database = \ForumRewrite\Host\HighTrafficRenderInput::prepare($context, $database, dirname($assets) . '/model', $report);
    $report('rendering HTML and copying referenced assets');
    $html = (new \ForumRewrite\Host\StaticArtifactBuilder($project, $repository, $database, $assets, $report))->renderHighTrafficRoute($route, allowStaleReadModel: $context['state']['absent'] !== []);
    $report('validating rendered candidate');
    \ForumRewrite\Host\HighTrafficPublication::validate($context);
    fwrite(STDOUT, $html);
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . "\n");
    exit(http_response_code() === 404 ? 4 : 1);
}
