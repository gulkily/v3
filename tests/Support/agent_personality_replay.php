<?php

declare(strict_types=1);

// Read and replay completed work in an isolated trial, optionally using prior code.
$directory = $argv[1] ?? '';
$code = $argv[2] ?? ($directory . '/app');
require $code . '/autoload.php';
$env = json_decode(file_get_contents($directory . '/environment.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($env as $key => $value) { putenv($key . '=' . $value); }
putenv('LLM_PROVIDER=stub');
putenv('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED=false');
putenv('SCRIPTURE_RESPONSE_IMMEDIATE_DISPATCH=false');
$app = new \ForumRewrite\Application($code, $env['FORUM_REPOSITORY_ROOT'], $env['FORUM_DATABASE_PATH'], $env['FORUM_PUBLIC_ARTIFACT_ROOT']);
$_COOKIE = ['identity_hint' => 'guest'];
$render = function (string $path) use ($app): string {
    ob_start(); $app->handle('GET', $path); return (string) ob_get_clean();
};
$render('/'); // Exercise startup using the selected code version.
$pdo = new PDO('sqlite:' . $env['FORUM_DATABASE_PATH']);
$store = new \ForumRewrite\Agent\SqliteAgentReplyGenerationStore($pdo);
$before = (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
$rows = $pdo->query("SELECT target_post_id, target_content_hash, agent_post_id FROM post_generated_responses WHERE status = 'posted'")->fetchAll(PDO::FETCH_ASSOC);
$normalize = static fn (string $s): string => preg_replace('/\s+/u', ' ', trim(html_entity_decode(strip_tags($s), ENT_QUOTES | ENT_HTML5, 'UTF-8')));
foreach ($rows as $row) {
    $saved = $store->findByTarget($row['target_post_id'], $row['target_content_hash']);
    $again = $app->fulfillAgentReplyRequest($saved);
    if (($again['agent_post_id'] ?? '') !== $row['agent_post_id']) { throw new RuntimeException('Replay changed the published result.'); }
    $post = $pdo->query('SELECT * FROM posts WHERE post_id = ' . $pdo->quote($row['agent_post_id']))->fetch(PDO::FETCH_ASSOC);
    // Expansion is displayed beneath its initial response, not as its own card.
    $isExpansion = str_contains($post['agent_response_json'] ?? '', 'scripture_expansion');
    $paths = ['/threads/' . $post['thread_id'], '/posts/' . ($isExpansion ? $post['parent_id'] : $post['post_id'])];
    foreach ($paths as $path) {
        if (!str_contains($normalize($render($path)), $normalize($post['body']))) {
            throw new RuntimeException('Published text missing from ' . $path);
        }
    }
    $forte = json_decode($render('/api/forte_thread_detail?thread_id=' . $post['thread_id']), true);
    if (!str_contains($normalize($forte['html'] ?? ''), $normalize($post['body']))) { throw new RuntimeException('Forte lost published reply text.'); }
}
if ($before !== (int) $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn()) { throw new RuntimeException('Replay duplicated posts.'); }
fwrite(STDERR, count($rows) . " completed replies preserved across startup/replay and thread/permalink/Forte rendering.\n");
