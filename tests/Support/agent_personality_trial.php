<?php

declare(strict_types=1);

// Run only against a fresh isolated directory from scripture_evaluation.py setup.
$directory = $argv[1] ?? '';
if (!is_file($directory . '/environment.json') || is_file($directory . '/personality-results.json')) {
    throw new RuntimeException('A fresh isolated evaluation directory is required.');
}
require $directory . '/app/autoload.php';
$project = dirname(__DIR__, 2);
$live = \ForumRewrite\Support\PrivateConfig::load($project);
if (($live['LLM_PROVIDER'] ?? '') !== 'openai' || empty($live['LLM_API_KEY'])) {
    throw new RuntimeException('Configured OpenAI provider required for this bounded trial.');
}
$env = json_decode(file_get_contents($directory . '/environment.json'), true, 512, JSON_THROW_ON_ERROR);
foreach ($env as $key => $value) { putenv($key . '=' . $value); }
putenv('LLM_API_KEY=' . $live['LLM_API_KEY']);
putenv('LLM_MODEL=' . $live['LLM_MODEL']);
putenv('DEDALUS_AGENT_REPLIES_AUTOMATIC_ENABLED=false');
putenv('SCRIPTURE_RESPONSE_IMMEDIATE_DISPATCH=false');
putenv('SCRIPTURE_RESPONSE_GUIDANCE=verified');
putenv('SCRIPTURE_RESPONSE_REASONING_EFFORT=low');
$app = new \ForumRewrite\Application($directory . '/app', $env['FORUM_REPOSITORY_ROOT'], $env['FORUM_DATABASE_PATH'], $env['FORUM_PUBLIC_ARTIFACT_ROOT']);
$request = function (string $method, string $path) use ($app): string {
    ob_start(); $app->handle($method, $path); return (string) ob_get_clean();
};
$_COOKIE = ['identity_hint' => 'guest'];
$cases = json_decode(file_get_contents($project . '/tests/fixtures/agent_personality_review.json'), true, 512, JSON_THROW_ON_ERROR);
$selected = isset($argv[2]) ? explode(',', $argv[2]) : array_column($cases['cases'], 'id');
if (array_diff($selected, array_column($cases['cases'], 'id')) !== []) { throw new RuntimeException('Unknown trial case.'); }
$results = ['model' => $live['LLM_MODEL'], 'rubric' => $cases['rubric'], 'cases' => []];
foreach ($cases['cases'] as $case) {
    if (!in_array($case['id'], $selected, true)) { continue; }
    // Creation/moderation uses the stub; only the requested writer calls the provider.
    putenv('LLM_PROVIDER=stub');
    $created = $request('POST', '/api/create_thread?board_tags=general&body=' . rawurlencode($case['body']));
    if (!preg_match('/^post_id=(.+)$/m', $created, $match)) { throw new RuntimeException('Synthetic post creation failed: ' . $created); }
    $id = trim($match[1]);
    $mode = $case['type'] === 'default_text_reply' ? '' : '&response_mode=' . $case['type'];
    $requested = json_decode($request('POST', '/api/generate_agent_reply?post_id=' . $id . $mode), true);
    if (($requested['generation_status'] ?? '') !== 'requested') { throw new RuntimeException('Synthetic request failed.'); }
    $pdo = new PDO('sqlite:' . $env['FORUM_DATABASE_PATH']);
    $store = new \ForumRewrite\Agent\SqliteAgentReplyGenerationStore($pdo);
    $claimed = $store->claimRequestedForPost($id);
    putenv('LLM_PROVIDER=openai');
    $done = $app->fulfillAgentReplyRequest($claimed);
    $saved = $store->findByTarget($id, $claimed['target_content_hash']);
    $result = $case + ['status' => $done['generation_status'], 'reply' => $saved['response_text'], 'failure_code' => $saved['failure_code']];
    putenv('LLM_PROVIDER=stub');
    foreach (['thread' => '/threads/' . $id, 'post' => '/posts/' . ($done['agent_post_id'] ?? $id), 'forte' => '/forte?thread=' . $id] as $surface => $path) {
        file_put_contents($directory . '/' . $case['id'] . '-' . $surface . '.html', $request('GET', $path));
    }
    if ($case['id'] === 'scripture' && $done['generation_status'] === 'generated') {
        $initial = $done['agent_post_id'];
        $request('POST', '/api/expand_agent_reply?post_id=' . $initial);
        $expansion = $store->claimRequestedForPost($initial);
        putenv('LLM_PROVIDER=openai');
        $expanded = $app->fulfillAgentReplyRequest($expansion);
        $savedExpansion = $store->findByTarget($initial, $expansion['target_content_hash']);
        $result['expansion'] = ['status' => $expanded['generation_status'], 'reply' => $savedExpansion['response_text'], 'failure_code' => $savedExpansion['failure_code']];
    }
    $results['cases'][] = $result;
    file_put_contents($directory . '/personality-results.json', json_encode($results, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    fwrite(STDERR, $case['id'] . ': ' . $result['status'] . "\n");
}
