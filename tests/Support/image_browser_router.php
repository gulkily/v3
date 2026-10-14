<?php

declare(strict_types=1);
if (PHP_SAPI !== 'cli-server' || !getenv('IMAGE_TEST_ROOT')) { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/autoload.php';
$root = getenv('IMAGE_TEST_ROOT');
$flags = json_decode(file_get_contents($root . '/flags.json'), true);
putenv('FORUM_IMAGE_CONTENT_ENABLED=' . (!empty($flags['images']) ? 'true' : 'false'));
putenv('FORUM_APPROVED_MEMBERS_ONLY=' . (!empty($flags['members']) ? 'true' : 'false'));
putenv('FORUM_SITE_ID=' . ($flags['site'] ?? 'zenmemes'));
$instance = !empty($flags['restored']) ? $root . '/restore' : $root;
(new \ForumRewrite\Host\FrontController($root, $instance . '/repository', $instance . '/read.sqlite3', $instance . '/static'))
    ->handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_COOKIE);
