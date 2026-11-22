<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/LocalMetaTrainingTest.php';

use ForumRewrite\Scoring\LocalMetaFiles;
use ForumRewrite\Scoring\LocalMetaScores;
use ForumRewrite\Scoring\LocalMetaSweep;
use ForumRewrite\Scoring\LocalMetaTraining;
use ForumRewrite\Scoring\LocalMetaTrainingRefused;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;
use ForumRewrite\TaskQueue\TaskQueueWorker;

final class LocalMetaPageTest
{
    private const OPERATOR = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    /**
     * One POST per process, as on a server; returns [HTTP status, page HTML, queued training tasks].
     *
     * @param array<string, string> $server
     * @return array{0:int, 1:string, 2:int}
     */
    private function post(?string $identity, array $server, string $action, ?string $hint = null): array
    {
        $script = <<<'PHP'
require $argv[1] . '/autoload.php';
require $argv[1] . '/tests/Support/TestViewerSession.php';

use ForumRewrite\Application;
use ForumRewrite\TaskQueue\SqliteTaskQueueStore;

[$identity, $server, $action, $hint] = json_decode($argv[3], true);
$databasePath = sys_get_temp_dir() . '/local-meta-page-' . bin2hex(random_bytes(6)) . '.sqlite3';
$queuePath = sys_get_temp_dir() . '/local-meta-page-queue-' . bin2hex(random_bytes(6)) . '.sqlite3';
putenv('FORUM_TASK_QUEUE_DATABASE_PATH=' . $queuePath);
$_COOKIE = [];
if ($identity !== null) TestViewerSession::signIn($identity);
if ($hint !== null) $_COOKIE['identity_hint'] = $hint;
$_SERVER = array_merge($_SERVER, ['HTTP_HOST' => 'forum.test'], $server);
$app = new Application($argv[1], $argv[2], $databasePath);
$pages = [];
foreach ($action === 'retrain-twice' ? ['retrain', 'retrain'] : [$action] as $one) {
    $_POST = ['action' => $one];
    ob_start();
    $app->handle('POST', '/tools/fastmod/');
    $pages[] = (string) ob_get_clean();
}
$count = is_file($queuePath) ? (int) (new PDO('sqlite:' . $queuePath))->query("SELECT COUNT(*) FROM internal_tasks WHERE type = 'local_meta_train'")->fetchColumn() : 0;
echo "\n---RESULT---\n" . json_encode([http_response_code(), implode("\n---PAGE---\n", $pages), $count]);
TestViewerSession::reset();
@unlink($databasePath);
@unlink($queuePath);
PHP;
        $output = (string) shell_exec(sprintf('php -r %s %s %s %s 2>&1', escapeshellarg($script), escapeshellarg(dirname(__DIR__)),
            escapeshellarg(__DIR__ . '/fixtures/parity_minimal_v1'), escapeshellarg((string) json_encode([$identity, $server, $action, $hint]))));
        $result = json_decode(substr($output, (int) strrpos($output, "---RESULT---\n") + 13), true);
        if (!is_array($result)) throw new RuntimeException('No result: ' . $output);
        return $result;
    }

    public function testRetrainQueuesOnceForASameOriginOperatorSession(): void
    {
        $same = ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'http://forum.test'];
        [$status, $pages, $count] = $this->post(self::OPERATOR, $same, 'retrain-twice');
        assertSame(200, $status);
        assertSame(1, $count);
        [$first, $second] = explode("\n---PAGE---\n", $pages);
        assertStringContains('Retraining queued for the task worker.', $first);
        assertStringContains('Retraining is already queued.', $second);
        assertStringContains('Training queued.', $second);
        assertStringContains('>Retrain</button>', $second);
    }

    public function testRetrainIsRefusedForEveryoneElse(): void
    {
        $same = ['HTTP_SEC_FETCH_SITE' => 'same-origin', 'HTTP_ORIGIN' => 'http://forum.test'];
        $refused = [
            'signed out' => [null, $same, null],
            'identity hint only' => [null, $same, self::OPERATOR],
            'non-operator session' => ['openpgp:' . str_repeat('ab', 20), $same, null],
            'cross-site' => [self::OPERATOR, ['HTTP_SEC_FETCH_SITE' => 'cross-site', 'HTTP_ORIGIN' => 'https://evil.test'], null],
            'no origin evidence' => [self::OPERATOR, [], null],
        ];
        foreach ($refused as $name => [$identity, $server, $hint]) {
            [$status, $page, $count] = $this->post($identity, $server, 'retrain', $hint);
            assertSame(403, $status, $name);
            assertSame(0, $count, $name);
            if ($identity !== self::OPERATOR) assertStringNotContains('>Retrain</button>', $page);
        }
        [$status, , $count] = $this->post(self::OPERATOR, ['HTTP_SEC_FETCH_SITE' => 'same-origin'], 'reboot');
        assertSame(400, $status);
        assertSame(0, $count);
    }

    public function testReportReadsBackForThePage(): void
    {
        [$read, $scores] = LocalMetaTrainingTest::fixture();
        $read->exec("UPDATE threads SET thread_labels_json = '[]' WHERE root_post_id = 'support-3'");
        $read->exec("INSERT INTO posts (post_id, thread_id, subject, body) VALUES ('mislabeled', 'mislabeled', 'Bug: the button and theme page', 'please fix')");
        $read->exec("INSERT INTO threads (root_post_id, thread_labels_json) VALUES ('mislabeled', '[]')");
        $scores->exec("INSERT INTO post_fast_scores (post_id, content_hash, rubric_revision, status, source, scores_json, updated_at) SELECT 'x', 'y', 'r1', 'scored', 'llm', '{}', '2026-01-01'");
        $report = (new LocalMetaTraining($read, $scores))->run(true, '2026-11-14T00:00:00Z')['report'];
        $parsed = LocalMetaFiles::parseReport(LocalMetaFiles::reportText($report));
        $summary = array_column($parsed['summary'], 1, 0);
        assertSame('2026-11-14T00:00:00Z', $summary['Built-At']);
        assertSame('yes', $summary['Weak-Negatives']);
        assertSame((string) count($report['disagreements']), $summary['Disagreements']);
        assertSame(count($report['disagreements']), count($parsed['disagreements']));
        foreach ($parsed['disagreements'] as $item) {
            foreach (['Post', 'Label', 'Score', 'Words', 'Subject'] as $key) assertTrue(isset($item[$key]), $key);
        }
    }

    public function testTrainTaskSavesQueuesTheSweepAndExplainsRefusal(): void
    {
        $queue = new SqliteTaskQueueStore(new PDO('sqlite::memory:'));
        $saved = 0;
        [$read, $scores] = LocalMetaTrainingTest::fixture();
        $task = $queue->enqueue(SqliteTaskQueueStore::LOCAL_META_TRAIN, 'local-meta-train');
        (new TaskQueueWorker($queue, static function (): void {}, runLocalMetaTrain: static function () use ($read, $scores, $queue, &$saved): array {
            $report = (new LocalMetaTraining($read, $scores))->run(false)['report'];
            $saved++;
            $queue->enqueue(SqliteTaskQueueStore::LOCAL_SCORE_SWEEP, LocalMetaSweep::DEDUPLICATION_KEY);
            return $report;
        }))->run(1);
        assertSame('completed', $queue->findById((int) $task['id'])['status']);
        assertSame(1, $saved);
        assertSame('queued', $queue->latestByType(SqliteTaskQueueStore::LOCAL_SCORE_SWEEP)['status']);

        $refused = $queue->enqueue(SqliteTaskQueueStore::LOCAL_META_TRAIN, 'local-meta-train');
        (new TaskQueueWorker($queue, static function (): void {}, runLocalMetaTrain: static function (): array {
            throw new LocalMetaTrainingRefused([], 3, 0, false);
        }))->run(1, null, (int) $refused['id']);
        $failed = $queue->findById((int) $refused['id']);
        assertSame('failed', $failed['status']);
        assertStringContains('3 meta, 0 not meta', (string) $failed['failure_message']);
        assertSame(1, (int) $failed['attempts']);
        unset($scores);
        assertSame(null, LocalMetaScores::readOnly(sys_get_temp_dir() . '/no-such-' . bin2hex(random_bytes(4))));
    }
}
