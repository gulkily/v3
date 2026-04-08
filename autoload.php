<?php

declare(strict_types=1);

spl_autoload_register(static function (string $class): void {
    $prefix = 'ForumRewrite\\';
    $baseDir = __DIR__ . '/src/ForumRewrite/';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $path = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($path)) {
        require $path;
    }
});
