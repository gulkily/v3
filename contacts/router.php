<?php

declare(strict_types=1);

header('Cache-Control: no-store');
header("Content-Security-Policy: default-src 'none'; script-src 'self'; style-src 'self'; connect-src 'self'; base-uri 'none'; form-action 'none'; frame-ancestors 'none'");
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

$host = '127.0.0.1:' . ($_SERVER['SERVER_PORT'] ?? '');
if (($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1' || ($_SERVER['HTTP_HOST'] ?? '') !== $host
    || isset($_SERVER['HTTP_FORWARDED']) || preg_grep('/^HTTP_X_FORWARDED_/', array_keys($_SERVER))) {
    http_response_code(403);
    return;
}
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
if ($method === 'GET' && $path === '/') {
    header('Content-Type: text/html; charset=utf-8');
    readfile(__DIR__ . '/public/index.html');
    return;
}
if (!is_string($path) || !str_starts_with($path, '/api/')) {
    http_response_code(404);
    return;
}
header('Content-Type: application/json; charset=utf-8');
$secret = getenv('V3_CONTACTS_SECRET');
if (!is_string($secret) || $secret === '' || !hash_equals('Bearer ' . $secret, $_SERVER['HTTP_AUTHORIZATION'] ?? '')) {
    http_response_code(401);
    echo '{"error":"Open the URL printed by ./v3 contacts."}';
    return;
}
if ($method !== 'GET' && ($_SERVER['HTTP_ORIGIN'] ?? '') !== 'http://' . $host) {
    http_response_code(403);
    echo '{"error":"Open Contacts from its local address and retry."}';
    return;
}
if ($method === 'GET' && $path === '/api/contacts') {
    echo '{"contacts":[]}';
    return;
}
http_response_code(404);
