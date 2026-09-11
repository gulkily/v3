<?php

declare(strict_types=1);

$artifactRoot = $argv[1] ?? (getenv('FORUM_PUBLIC_ARTIFACT_ROOT') ?: (dirname(__DIR__) . '/public'));
if (!is_dir($artifactRoot)) {
    fwrite(STDERR, "Artifact root does not exist: {$artifactRoot}\n");
    exit(1);
}

$missing = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($artifactRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if (!$file->isFile() || strtolower($file->getExtension()) !== 'html') {
        continue;
    }

    $contents = file_get_contents($file->getPathname());
    if ($contents === false || preg_match_all('#/assets/[A-Za-z0-9_./-]+\.[a-f0-9]{12}\.[A-Za-z0-9]+#', $contents, $matches) === false) {
        continue;
    }

    foreach (array_unique($matches[0]) as $assetPath) {
        if (!is_file($artifactRoot . $assetPath)) {
            $missing[] = $file->getPathname() . ' -> ' . $assetPath;
        }
    }
}

if ($missing !== []) {
    fwrite(STDERR, "Missing fingerprinted assets:\n" . implode("\n", $missing) . "\n");
    exit(1);
}

fwrite(STDOUT, "All fingerprinted asset references resolve under {$artifactRoot}\n");
