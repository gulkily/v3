<?php

declare(strict_types=1);
namespace ForumRewrite\Http;

final class ApiErrors
{
    public static function isApi(string $uri): bool
    {
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        return $path === '/api' || str_starts_with($path, '/api/');
    }

    public static function isBusy(string $message): bool
    {
        return str_contains($message, 'Timed out waiting for execution lock:');
    }

    public static function busy(): array
    {
        return ['status' => 'busy', 'error' => 'The instance is busy. Keep your draft or saved operation and retry after 10 seconds.', 'retry_after' => 10];
    }
}
