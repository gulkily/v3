<?php

declare(strict_types=1);
require dirname(__DIR__, 2) . '/autoload.php';
require dirname(__DIR__) . '/FastmodScoreTransferTest.php';

use ForumRewrite\Images\ImageBackup;
use ForumRewrite\Scoring\SqliteFastScoreStore;

$root = $argv[1];
$command = $argv[2] ?? 'seed';
if ($command === 'inspect') {
    $pdo = new PDO('sqlite:' . $root . '/target-scores.sqlite3');
    $offline = new PDO('sqlite:' . $root . '/target-static/offline/snapshot.sqlite3');
    echo json_encode(['scores' => $pdo->query('SELECT * FROM post_fast_scores')->fetchAll(PDO::FETCH_ASSOC),
        'jobs' => (int) $pdo->query('SELECT COUNT(*) FROM fast_score_work')->fetchColumn(),
        'labels' => $offline->query("SELECT thread_labels_json FROM threads WHERE root_post_id='post-1'")->fetchColumn()], JSON_THROW_ON_ERROR);
    exit;
}
foreach (['source', 'target'] as $name) {
    $repo = $root . '/' . $name;
    ImageBackup::copyTree(dirname(__DIR__) . '/fixtures/parity_minimal_v1/records', $repo . '/records');
    if ($name === 'source') file_put_contents($repo . '/records/posts/post-1.txt', "Post-ID: post-1\nCreated-At: 2026-04-10T12:00:00Z\nBoard-Tags: general\nSubject: Portable score example\n\nA discussion about how this community works.\n");
    foreach ([['init', '-q'], ['config', 'user.email', 'test@example.invalid'], ['config', 'user.name', 'Transfer Test'], ['add', '.'], ['commit', '-qm', 'Seed']] as $arguments) {
        $process = proc_open(['git', '-C', $repo, ...$arguments], [0 => ['file', '/dev/null', 'r'], 1 => ['file', '/dev/null', 'w'], 2 => STDERR], $pipes);
        if (proc_close($process) !== 0) throw new RuntimeException('Cannot seed transfer fixture.');
    }
}
$row = FastmodScoreTransferTest::compatibleRow($root . '/source');
$store = new SqliteFastScoreStore(new PDO('sqlite:' . $root . '/source-scores.sqlite3'));
$store->importScoredRows([$row], ['post-1' => $row['content_hash']], $row['rubric_revision'], array_keys($row['scores']));
echo json_encode($row, JSON_THROW_ON_ERROR);
