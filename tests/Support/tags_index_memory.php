<?php

declare(strict_types=1);

require dirname(__DIR__, 2) . '/autoload.php';

use ForumRewrite\Application;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\ReadModel\TagGrouping;

$project = dirname(__DIR__, 2);
$database = $argv[1];
$count = (int) $argv[2];
$app = new Application($project, $project . '/tests/fixtures/parity_minimal_v1', $database);
ob_start();
$app->handle('GET', '/tags/');
ob_end_clean();
$pdo = new PDO('sqlite:' . $database);
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$post = $pdo->prepare('INSERT INTO posts (post_id,created_at,thread_id,body,board_tags_json,sequence_number) VALUES (?,?,?,?,?,?)');
$thread = $pdo->prepare('INSERT INTO threads (root_post_id,root_post_created_at,last_activity_at,subject,body_preview,reply_count,last_post_id,board_tags_json,thread_labels_json) VALUES (?,?,?,?,?,0,?,?,?)');
$pdo->beginTransaction();
for ($i = 0; $i < $count; $i++) {
    $id = sprintf('memory-%06d', $i);
    $post->execute([$id, '2026-10-30', $id, str_repeat('b', 2048), '["memory"]', $i]);
    $thread->execute([$id, '2026-10-30', '2026-10-30', 'Memory quote ' . $i, str_repeat('p', 1024), $id, '["memory"]', '[]']);
}
$pdo->commit();
if (($argv[3] ?? '') === 'legacy') {
    // Reproduce the old index allocation in a separate, explicitly capped process.
    TagGrouping::limitPreview(TagGrouping::byTag(ThreadRepository::fetchThreads($pdo)), 5);
    exit;
}
ob_start();
$app->handle('GET', '/tags/');
$html = (string) ob_get_clean();
if (substr_count($html, 'data-tag-preview-item="memory"') !== 5
    || !str_contains($html, 'showing 5 newest of ' . $count)
    || !str_contains($html, '/threads/' . sprintf('memory-%06d', $count - 1))
    || str_contains($html, '/threads/memory-000000')) {
    throw new RuntimeException('Tags index counts, previews or ordering changed.');
}
echo 'Records: ' . $count . "\nPeak bytes: " . memory_get_peak_usage(true) . "\n";
