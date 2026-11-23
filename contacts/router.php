<?php

declare(strict_types=1);

use ForumRewrite\Contacts\LinkedInConnectionsReader;

require dirname(__DIR__) . '/autoload.php';

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
$assets = [
    '/' => ['index.html', 'text/html'],
    '/contacts.css' => ['contacts.css', 'text/css'],
    '/contacts.js' => ['contacts.js', 'application/javascript'],
];
if ($method === 'GET' && isset($assets[$path])) {
    [$filename, $type] = $assets[$path];
    header('Content-Type: ' . $type . '; charset=utf-8');
    readfile(__DIR__ . '/public/' . $filename);
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
if ($method === 'POST' && $path === '/api/preview') {
    try {
        if ((int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 65 * 1024 * 1024) {
            throw new RuntimeException('This file is too large. Extract Connections.csv and try it directly.', 413);
        }
        $parsed = (new LinkedInConnectionsReader())->readUpload($_FILES['file'] ?? []);
        $rows = [];
        foreach ($parsed['contacts'] as $number => $contact) {
            $rows[$number] = LinkedInConnectionsReader::previewRow($number, 'added', $contact);
        }
        foreach ($parsed['skipped'] as $number => $reason) {
            $rows[$number] = LinkedInConnectionsReader::previewRow($number, 'skipped', reason: $reason);
        }
        ksort($rows);
        echo json_encode([
            'counts' => ['added' => count($parsed['contacts']), 'updated' => 0, 'unchanged' => 0, 'skipped' => count($parsed['skipped'])],
            'rows' => array_values($rows),
        ], JSON_THROW_ON_ERROR);
    } catch (RuntimeException $error) {
        http_response_code(in_array($error->getCode(), [413, 422], true) ? $error->getCode() : 500);
        echo json_encode(['error' => $error->getCode() === 0 ? 'Could not read the file. Try again.' : $error->getMessage()]);
    }
    return;
}
http_response_code(404);
