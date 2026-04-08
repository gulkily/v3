<?php

declare(strict_types=1);

$projectRoot = dirname(__DIR__);
$source = $projectRoot . '/tests/fixtures/parity_minimal_v1';
$target = $argv[1] ?? ($projectRoot . '/state/local_repository');

if (is_dir($target)) {
    fwrite(STDOUT, "Local repository already exists at {$target}\n");
    exit(0);
}

mkdir($target, 0777, true);
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $item) {
    $targetPath = $target . '/' . $iterator->getSubPathName();
    if ($item->isDir()) {
        if (!is_dir($targetPath)) {
            mkdir($targetPath, 0777, true);
        }

        continue;
    }

    copy($item->getPathname(), $targetPath);
}

fwrite(STDOUT, "Initialized local repository at {$target}\n");
