<?php
// Emulate the web server's ordinary static favicon delivery for worker installation.
if (PHP_SAPI === 'cli-server' && getenv('SIGNED_REACTION_TEST_ROOT')
    && parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) === '/favicon.ico') {
    header('Content-Type: image/x-icon');
    readfile(dirname(__DIR__, 2) . '/public/favicon.ico');
    return;
}
require __DIR__ . '/signed_reaction_browser_router.php';
