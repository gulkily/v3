<?php

declare(strict_types=1);

$root = getenv('MEDIA_EMBED_BROWSER_ROOT');
if (PHP_SAPI !== 'cli-server' || !$root) { http_response_code(404); exit; }
require dirname(__DIR__, 2) . '/autoload.php';
$flags = json_decode(file_get_contents($root . '/flags.json'), true, 512, JSON_THROW_ON_ERROR);
putenv('FORUM_MEDIA_EMBEDS_ENABLED=' . ($flags['cards'] ? 'true' : 'false'));
putenv('FORUM_MEDIA_EMBEDS_INLINE_PLAYER_ENABLED=' . ($flags['players'] ? 'true' : 'false'));
putenv('FORUM_APPROVED_MEMBERS_ONLY=false');
putenv('FORUM_SITE_ID=' . $flags['site']);
if (parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/test-thumbnail.svg') {
    header('Content-Type: image/svg+xml');
    echo '<svg xmlns="http://www.w3.org/2000/svg" width="1280" height="720"><rect width="1280" height="720" fill="#617d89"/></svg>';
    exit;
}
(new \ForumRewrite\Host\FrontController($root, $root . '/repository', $root . '/read.sqlite3', $root . '/static'))
    ->handle($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $_COOKIE);
