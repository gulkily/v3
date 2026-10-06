<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$file = __DIR__ . $path;
$isPublicAsset = str_starts_with($path, '/assets/')
    || in_array($path, ['/favicon.ico', '/manifest.webmanifest', '/service_worker.js'], true);

// scripts/dev_server.php sets V3_DEV_LOG; it reads one line per request from stderr.
$devLogEnabled = getenv('V3_DEV_LOG') === '1';
if ($isPublicAsset && is_file($file)) {
    if ($devLogEnabled) {
        file_put_contents('php://stderr', "V3DEV\t200\t{$_SERVER['REQUEST_METHOD']}\t{$_SERVER['REQUEST_URI']}\t0\tstatic\n");
    }
    return false;
}

if ($devLogEnabled) {
    $devLogStartedAt = microtime(true);
    register_shutdown_function(static function () use ($devLogStartedAt): void {
        $why = '';
        foreach (headers_list() as $header) {
            if (stripos($header, 'Location:') === 0) {
                $why = '-> ' . trim(substr($header, strlen('Location:')));
            }
        }
        $ms = (microtime(true) - $devLogStartedAt) * 1000;
        file_put_contents('php://stderr', sprintf(
            "V3DEV\t%d\t%s\t%s\t%.1f\t%s\n",
            http_response_code() ?: 200,
            $_SERVER['REQUEST_METHOD'] ?? 'GET',
            $_SERVER['REQUEST_URI'] ?? '/',
            $ms,
            $why,
        ));
    });
}

require __DIR__ . '/index.php';
