<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Host\StaticArtifactBuilder;

$project = dirname(__DIR__, 2);
$root = $argv[1];
$count = (int) $argv[2];
$mode = $argv[3] ?? 'route';
putenv('FORUM_SITE_ID=qdb');
$app = new Application($project, $project . '/tests/fixtures/parity_minimal_v1', $root . '/read.sqlite3');
ob_start(); $app->handle('GET', '/tags/'); ob_end_clean();
$pdo = new PDO('sqlite:' . $root . '/read.sqlite3');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$post = $pdo->prepare('INSERT INTO posts (post_id,created_at,thread_id,body,board_tags_json,sequence_number) VALUES (?,?,?,?,?,?)');
$thread = $pdo->prepare('INSERT INTO threads (root_post_id,root_post_created_at,last_activity_at,subject,body_preview,reply_count,last_post_id,board_tags_json,thread_labels_json) VALUES (?,?,?,?,?,0,?,?,?)');
$pdo->beginTransaction();
for ($i = 0; $i < $count; $i++) {
    $id = sprintf('memory-%06d', $i);
    $body = str_repeat('b', 2048);
    if ($mode === 'large' && $i === $count - 1) {
        $header = "Post-ID: $id\nCreated-At: 2026-10-30T00:00:00Z\nBoard-Tags: memory\n\n";
        $record = $header . str_repeat('b', \ForumRewrite\Canonical\RecordLimits::TEXT - strlen($header) - 1) . "\n";
        $body = (new \ForumRewrite\Canonical\PostRecordParser())->parse($record)->body;
        unset($record);
    }
    $post->execute([$id, '2026-10-30', $id, $body, '["memory"]', $i]);
    $thread->execute([$id, '2026-10-30', '2026-10-30', 'Memory quote ' . $i, str_repeat('p', 1024), $id, '["memory"]', '["memory' . (1 + $i % 23) . '"]']);
    unset($body);
}
$pdo->commit();
$started = microtime(true);
if ($mode === 'artifacts') {
    $builder = new StaticArtifactBuilder($project, $project . '/tests/fixtures/parity_minimal_v1', $root . '/read.sqlite3', $root . '/artifacts');
    foreach (array_merge(['memory'], array_map(static fn ($i) => 'memory' . $i, range(1, 23))) as $tag) {
        if (!$builder->buildSingleRoute('/tags/' . $tag)) throw new RuntimeException('Tag generation failed');
        $html = (string) file_get_contents($root . '/artifacts/tags/' . $tag . '.html');
        if (preg_match_all('/<article class="card (?:post-card quote-card|thread-card)"/', $html) !== min(25, $count)) throw new RuntimeException('Unbounded or missing tag cards: ' . $tag);
        unset($html);
    }
} else {
    ob_start(); $app->handle('GET', '/tags/memory'); $html = (string) ob_get_clean();
    if (preg_match_all('/<article class="card (?:post-card quote-card|thread-card)"/', $html) !== min(25, $count)) throw new RuntimeException('Incorrect tag page size');
    if ($count > 25 && (!str_contains($html, 'rel="next"') || str_contains($html, '/threads/memory-000000'))) throw new RuntimeException('Wrong page boundary');
    unset($html);
}
echo 'Mode: ' . $mode . "\nRecords: " . $count . "\nPeak bytes: " . memory_get_peak_usage(true)
    . "\nElapsed seconds: " . round(microtime(true) - $started, 3) . "\nHydrated rows per page: " . min(25, $count) . "\n";
