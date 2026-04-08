<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$target = $argv[1] ?? ($projectRoot . '/state/local_repository');

if (is_dir($target . '/records') && is_dir($target . '/.git')) {
    fwrite(STDOUT, "Local repository already exists at {$target}\n");
    exit(0);
}

LocalRepositoryBootstrap::initializeLocalRepository($projectRoot, $target);

fwrite(STDOUT, "Initialized local repository at {$target}\n");
