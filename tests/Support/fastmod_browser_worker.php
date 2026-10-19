<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/autoload.php';
if (PHP_SAPI !== 'cli') exit(1);
$root = $argv[1];
$pdo = new PDO('sqlite:' . $root . '/read.sqlite3');
$pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
$fetch = static function (string $id) use ($pdo): ?array {
    $statement = $pdo->prepare('SELECT post_id, thread_id, parent_id, subject, body FROM posts WHERE post_id = ?');
    $statement->execute([$id]);
    return $statement->fetch() ?: null;
};
$config = \ForumRewrite\Support\PrivateConfig::load($root);
$workflow = \ForumRewrite\Scoring\FastScoreWorkflowFactory::fromPrivateConfig($config, $root, $fetch);
$store = new \ForumRewrite\Scoring\SqliteFastScoreStore(new PDO('sqlite:' . $config['FAST_SCORING_DATABASE_PATH']));
if (($argv[2] ?? '') === 'retry') {
    foreach ($store->recentWork() as $work) {
        if ($work['score_status'] === 'invalid_response') {
            $store->retryWork($work['post_id'], $work['content_hash'], $work['rubric_revision']);
        }
    }
}
$sweep = new \ForumRewrite\Scoring\FastScoreSweepService($pdo, new \ForumRewrite\Scoring\FastScoreContextFactory($fetch), $workflow, $store,
    \ForumRewrite\Scoring\FastScoringRubricRevision::fromConfig(\ForumRewrite\Scoring\FastScoringConfig::fromPrivateConfig($config), $root));
echo json_encode(['summary' => $sweep->run(1), 'work' => $store->recentWork()]);
