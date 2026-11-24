<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';
require_once __DIR__ . '/RebuildHistoryFixture.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

// Run under a capped memory_limit: a qdb-shaped corpus of single-post threads.
$count = (int) $argv[1];
$fixture = new RebuildHistoryFixture(0);
try {
    $directory = $fixture->repository . '/records/posts';
    for ($i = 0; $i < $count; $i++) {
        $id = sprintf('memory-%06d', $i);
        file_put_contents("{$directory}/{$id}.txt", "Post-ID: {$id}\nCreated-At: 2026-06-01T00:00:00Z\nBoard-Tags: general\n\n" . str_repeat("quote line\n", 40));
    }
    $fixture->git('add', '.');
    $fixture->git('commit', '-qm', 'quotes');
    $database = $fixture->root . '/model.sqlite';
    (new ReadModelBuilder($fixture->repository, $database, new CanonicalRecordRepository($fixture->repository)))->rebuild();
    $pdo = new PDO('sqlite:' . $database);
    echo 'Posts: ' . $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn()
        . "\nThreads: " . $pdo->query('SELECT COUNT(*) FROM threads')->fetchColumn()
        . "\nPeak bytes: " . memory_get_peak_usage(true) . "\n";
} finally {
    $fixture->remove();
}
