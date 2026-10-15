<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/autoload.php';
if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1];
$input = json_decode(stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
$path = $root . '/exchanges.sqlite3';
if (isset($input['approve_author'])) {
    $actor = $input['approve_author'];
    $seed = \ForumRewrite\Canonical\CanonicalPathResolver::approvalSeed(substr($actor, 8));
    file_put_contents($root . '/repository/' . $seed, "Approved-Identity-ID: {$actor}\nSeed-Reason: privacy browser author\n\n");
    exec('git -C ' . escapeshellarg($root . '/repository') . ' add ' . escapeshellarg($seed), $out, $code);
    if ($code !== 0) throw new RuntimeException('Cannot stage fixture approval');
    exec('git -C ' . escapeshellarg($root . '/repository') . ' commit -qm "Approve privacy browser author"', $out, $code);
    if ($code !== 0) throw new RuntimeException('Cannot commit fixture approval');
}
file_put_contents($root . '/secrets.php', '<?php return ' . var_export(['LLM_EXCHANGE_DATABASE_PATH' => $path], true) . ';');
$pdo = new PDO('sqlite:' . $path);
$recorder = new \ForumRewrite\Llm\LlmExchangeRecorder($pdo);
$ids = [];
foreach ($input['exchanges'] ?? [] as $row) {
    $context = ['call_type' => 'agent_response_task', 'post_id' => $row['post_id']];
    if (isset($row['dependencies'])) $context['exchange_dependencies'] = \ForumRewrite\Llm\ExchangeDependencies::manifest($row['dependencies']);
    $ids[] = $recorder->record($context, ['payload' => ['messages' => [['role' => 'user', 'content' => $row['marker']]]]],
        ['body' => $row['marker']], $row['status'] ?? 'completed', 1, ['message' => $row['marker']]);
}
echo json_encode(['ids' => $ids, 'count' => (int) $pdo->query('SELECT COUNT(*) FROM llm_exchanges')->fetchColumn()]);
