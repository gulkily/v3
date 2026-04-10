<?php

declare(strict_types=1);

require dirname(__DIR__) . '/autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Support\ExecutionLock;
use ForumRewrite\Support\LocalRepositoryBootstrap;

$projectRoot = dirname(__DIR__);
$defaultRepositoryRoot = LocalRepositoryBootstrap::defaultRepositoryRoot($projectRoot);
$repositoryRoot = $argv[1] ?? $defaultRepositoryRoot;
$databasePath = $argv[2] ?? ($projectRoot . '/state/cache/post_index.sqlite3');
$startedAt = microtime(true);
$sourceCounts = [
    'posts' => count(glob($repositoryRoot . '/records/posts/*.txt') ?: []),
    'identities' => count(glob($repositoryRoot . '/records/identity/*.txt') ?: []),
    'approval_seeds' => count(glob($repositoryRoot . '/records/approval-seeds/*.txt') ?: []),
];

(new ExecutionLock(dirname($databasePath) . '/forum-rewrite.lock'))->withExclusiveLock(
    static function () use ($repositoryRoot, $databasePath): void {
        $builder = new ReadModelBuilder(
            $repositoryRoot,
            $databasePath,
            new CanonicalRecordRepository($repositoryRoot),
        );
        $builder->rebuild();
    }
);

$pdo = new PDO('sqlite:' . $databasePath);
$readModelCounts = [
    'posts' => (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn(),
    'threads' => (int) $pdo->query('SELECT COUNT(*) FROM threads')->fetchColumn(),
    'profiles' => (int) $pdo->query('SELECT COUNT(*) FROM profiles')->fetchColumn(),
    'activity' => (int) $pdo->query('SELECT COUNT(*) FROM activity')->fetchColumn(),
];
$elapsedSeconds = microtime(true) - $startedAt;

fwrite(STDOUT, "Rebuilt read model at {$databasePath}\n");
fwrite(STDOUT, "Repository: {$repositoryRoot}\n");
fwrite(STDOUT, sprintf("Source records: %d posts, %d identities, %d approval seeds\n", $sourceCounts['posts'], $sourceCounts['identities'], $sourceCounts['approval_seeds']));
fwrite(STDOUT, sprintf("Read model: %d posts, %d threads, %d profiles, %d activity rows\n", $readModelCounts['posts'], $readModelCounts['threads'], $readModelCounts['profiles'], $readModelCounts['activity']));
fwrite(STDOUT, sprintf("Elapsed: %.3f seconds\n", $elapsedSeconds));
