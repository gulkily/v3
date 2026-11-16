<?php

declare(strict_types=1);

// Test-only router for tests/browser/usage_reports_e2e.mjs: the real front
// controller over the minimal fixture, with a read model and static root in
// USAGE_TEST_ROOT. Assets come from public/.
if (PHP_SAPI !== 'cli-server' || !getenv('USAGE_TEST_ROOT')) { http_response_code(404); exit; }
$project = dirname(__DIR__, 2);
require $project . '/autoload.php';
$root = (string) getenv('USAGE_TEST_ROOT');
$path = (string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if (str_starts_with($path, '/assets/')) {
    $file = $project . '/public' . preg_replace('/\.[0-9a-f]{12}\./', '.', $path);
    if (!is_file($file)) { http_response_code(404); exit; }
    header('Content-Type: ' . (str_ends_with($file, '.css') ? 'text/css' : (str_ends_with($file, '.js') ? 'application/javascript' : 'application/octet-stream')));
    readfile($file);
    exit;
}
(new \ForumRewrite\Host\FrontController($project, $project . '/tests/fixtures/parity_minimal_v1', $root . '/read.sqlite3', $root . '/static'))
    ->handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_COOKIE);
