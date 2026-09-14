<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;
$isPublicAsset = str_starts_with($path, '/assets/') || $path === '/favicon.ico';
if ($isPublicAsset && is_file($file)) {
    return false;
}

require __DIR__ . '/index.php';
