<?php

declare(strict_types=1);
require dirname(__DIR__) . '/autoload.php';

$args = array_slice($argv, 1);
if ($args === [] || in_array('--help', $args, true)) {
    fwrite(STDOUT, "Usage: ./v3 images verify|restore <images.tar> <repository_root> <database_path> [--image-root=/private/images]\nVerify checks a complete matching pair. Restore installs into a fresh private directory, or verifies an identical existing store. Rebuild the read model afterward.\n");
    exit($args === [] ? 1 : 0);
}
try {
    $mode = array_shift($args);
    $root = null; $positional = [];
    foreach ($args as $arg) {
        if (str_starts_with($arg, '--image-root=')) { $root = substr($arg, 13); }
        elseif (str_starts_with($arg, '--')) { throw new RuntimeException('Unknown option: ' . $arg); }
        else { $positional[] = $arg; }
    }
    if (!in_array($mode, ['verify', 'restore'], true) || count($positional) !== 3) { throw new RuntimeException('Expected verify|restore, image archive, repository, and database paths. Use --help.'); }
    [$archive, $repository, $database] = $positional;
    $repository = realpath($repository) ?: throw new RuntimeException('Repository does not exist.');
    $result = (new \ForumRewrite\Images\ImageRestore())->run($archive, $repository, $database, $root, $mode === 'verify');
    fwrite(STDOUT, json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR) . "\n");
} catch (Throwable $error) {
    fwrite(STDERR, 'Image ' . ($mode ?? 'recovery') . ' failed: ' . $error->getMessage() . "\n");
    exit(1);
}
