<?php

declare(strict_types=1);

// Only the browser test's local PHP server uses this router.
if (PHP_SAPI !== 'cli-server' || !getenv('PRIVATE_MESSAGE_TEST_ROOT')) {
    http_response_code(404);
    exit;
}
require dirname(__DIR__, 2) . '/autoload.php';
$root = getenv('PRIVATE_MESSAGE_TEST_ROOT');
(new \ForumRewrite\Host\FrontController($root, $root . '/repository', $root . '/read.sqlite3', $root . '/static'))
    ->handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_COOKIE);
