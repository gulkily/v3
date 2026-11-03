<?php

declare(strict_types=1);

// Test-only router: one `php -S` serves both schemes. The http/https replay harness puts a
// local TLS proxy in front and marks proxied requests, which arrive here as https.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
if (($_SERVER['HTTP_X_TEST_FORWARDED_PROTO'] ?? '') === 'https') {
    $_SERVER['HTTPS'] = 'on';
}
require __DIR__ . '/invite_browser_router.php';
