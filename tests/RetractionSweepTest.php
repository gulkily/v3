<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
use ForumRewrite\Retraction\RetractionSweep;
use ForumRewrite\Llm\LlmExchangeRecorder;
use ForumRewrite\Llm\ExchangeDependencies;

final class RetractionSweepTest
{
    public function fixture(): array
    {
        $root = sys_get_temp_dir() . '/sweep-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0700, true);
        mkdir($root . '/records/post-retractions');
        file_put_contents($root . '/records/posts/target.txt', "Post-ID: target\nCreated-At: 2026-10-14T11:00:00Z\nBoard-Tags: general\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\nOriginal prose\n");
        file_put_contents($root . '/records/post-retractions/post-retraction-target.txt', "Record-ID: post-retraction-target\nCreated-At: 2026-10-14T12:00:00Z\nPost-ID: target\nOperation: retract\nAuthor-Identity-ID: openpgp:" . str_repeat('a', 40) . "\n\n");
        $requestPath = $root . '/records/post-retractions/post-retraction-target.txt';
        $request = (new \ForumRewrite\Canonical\PostRetractionRecordParser())->parse(file_get_contents($requestPath));
        $post = (new \ForumRewrite\Canonical\PostRecordParser())->parse(file_get_contents($root . '/records/posts/target.txt'));
        $record = \ForumRewrite\Canonical\AcceptedRetraction::prepare($post, $request, file_get_contents($requestPath), 'fixture signature', 'author');
        foreach (\ForumRewrite\Canonical\AcceptedRetraction::files($record) as $file => $contents) {
            if (!is_dir(dirname($root . '/' . $file))) mkdir(dirname($root . '/' . $file), 0775, true);
            file_put_contents($root . '/' . $file, $contents);
        }
        unlink($root . '/records/posts/target.txt');
        $pdo = new PDO('sqlite:' . $root . '/exchanges.sqlite3');
        $recorder = new LlmExchangeRecorder($pdo);
        foreach ([['target'], ['other', 'target'], ['other'], null] as $ids) {
            $ctx = ['post_id' => $ids[0] ?? 'unknown', 'arbitrary' => 'SECRET-CONTEXT'];
            if ($ids !== null) $ctx['exchange_dependencies'] = ExchangeDependencies::manifest($ids);
            $recorder->record($ctx, ['body' => 'SECRET-REQUEST'], ['body' => 'SECRET-RESPONSE'], 'completed', 1, ['message' => 'SECRET-ERROR']);
        }
        return [$root, $pdo, new RetractionSweep($root, $root . '/exchanges.sqlite3', $root . '/state.json')];
    }


    public function testCliRecognizesNewReceiptAndDoesNotOfferLegacyMigration(): void
    {
        [$root, $pdo] = $this->fixture();
        mkdir($root . '/static/threads', 0775, true); mkdir($root . '/public');
        file_put_contents($root . '/static/threads/target.html', 'old artifact');
        touch($root . '/static/threads/target.html', time() - 10);
        $run = static function (string $mode, bool $json = false) use ($root): string {
            $args = [dirname(__DIR__) . '/v3', 'retraction-sweep', $mode, '--repository-root=' . $root,
                '--static-html-root=' . $root . '/static', '--exchange-database-path=' . $root . '/exchanges.sqlite3', '--state-path=' . $root . '/cli-state.json'];
            if ($json) $args[] = '--json';
            $pipes = []; $p = proc_open($args, [1=>['pipe','w'],2=>['pipe','w']], $pipes, null,
                array_merge(getenv(), ['FORUM_PUBLIC_ARTIFACT_ROOT' => $root . '/public']));
            $out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);
            assertSame(0,proc_close($p),$err);return $out;
        };
        $preview = $run('preview');
        assertStringContains('Retracted posts: 1', $preview);
        assertStringNotContains('migration', $preview);
        $status = json_decode($run('status', true), true, 512, JSON_THROW_ON_ERROR);
        assertSame(1,$status['artifacts']['affected']);
        $run('run');
        assertSame(false,is_file($root . '/static/threads/target.html'));
        assertSame(0,json_decode($run('preview',true),true)['affected']);
        assertSame(false,is_file($root . '/cli-state.json'));
    }

    public function testFullScanUsesBatchSizeWithoutLedgerAndPreservesKnownUnaffectedRows(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $before = hash_file('sha256', $root . '/exchanges.sqlite3');
        $other = $pdo->query('SELECT * FROM llm_exchanges WHERE id=3')->fetch(PDO::FETCH_ASSOC);
        assertSame(3, $sweep->preview(1)['affected']);
        assertSame($before, hash_file('sha256', $root . '/exchanges.sqlite3'));
        assertSame(3, $sweep->run(1)['cleaned']);
        assertSame($other, $pdo->query('SELECT * FROM llm_exchanges WHERE id=3')->fetch(PDO::FETCH_ASSOC));
        assertSame(0, $sweep->run(1)['cleaned']);
        assertSame(false, file_exists($root . '/state.json'));
    }

    public function testLatePayloadIsVisibleUntilNextSuccessfulSweep(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $sweep->run();
        $id = (new LlmExchangeRecorder($pdo, true, $root))->record(['post_id' => 'target', 'exchange_dependencies' => ExchangeDependencies::manifest(['target'])], ['body' => 'LATE-SECRET'], [], 'provider_error', 1);
        $store = new \ForumRewrite\Llm\SqliteLlmExchangeStore($pdo, new \ForumRewrite\Llm\ExchangeVisibility($root));
        assertSame('LATE-SECRET', $store->find($id)['request']['body']);
        assertSame(1, $sweep->run()['cleaned']);
        assertSame(null, $store->find($id));
    }

    public function testQueueOwnsCadenceAndFailuresWithoutSweepState(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $queuePdo = new PDO('sqlite::memory:');
        $store = new \ForumRewrite\TaskQueue\SqliteTaskQueueStore($queuePdo);
        \ForumRewrite\Retraction\RetractionSweepFactory::reconcile($sweep, $store);
        \ForumRewrite\Retraction\RetractionSweepFactory::reconcile($sweep, $store);
        assertSame(1, $store->counts()['queued']);
        $worker = new \ForumRewrite\TaskQueue\TaskQueueWorker($store, static function (): void {}, runRetractionSweep: fn () => $sweep->run(1));
        $worker->run();
        assertSame(1, $store->counts()['completed']);
        \ForumRewrite\Retraction\RetractionSweepFactory::reconcile($sweep, $store);
        assertSame(0, $store->counts()['queued']);
        $queuePdo->exec("UPDATE internal_tasks SET completed_at='2000-01-01T00:00:00Z'");
        \ForumRewrite\Retraction\RetractionSweepFactory::reconcile($sweep, $store);
        assertSame(1, $store->counts()['queued']);
        assertSame(false, file_exists($root . '/state.json'));
    }
    public function testCleanupZerosPayloadAndTruncatesWal(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $pdo->exec('DELETE FROM llm_exchanges WHERE id >= 3');
        $pdo->exec('VACUUM');
        $pdo->exec('PRAGMA journal_mode=WAL');
        $pdo->exec("UPDATE llm_exchanges SET request_json=" . $pdo->quote(json_encode(['body' => 'UNIQUE-SWEPT-SECRET'])));
        $sweep->run();
        foreach ([$root . '/exchanges.sqlite3', $root . '/exchanges.sqlite3-wal'] as $path) {
            if (is_file($path)) assertSame(false, str_contains(file_get_contents($path), 'UNIQUE-SWEPT-SECRET'));
        }
    }

    public function testCronCommandDiscoversAndExecutesMissedRetractionCleanup(): void
    {
        [$root, $pdo] = $this->fixture();
        mkdir($root . '/static');
        mkdir($root . '/public');
        file_put_contents($root . '/secrets.php', '<?php return ' . var_export(['LLM_EXCHANGE_DATABASE_PATH' => $root . '/exchanges.sqlite3'], true) . ';');
        $env = ['FORUM_REPOSITORY_ROOT' => $root, 'FORUM_DATABASE_PATH' => $root . '/index.sqlite3',
            'FORUM_STATIC_HTML_ROOT' => $root . '/static', 'FORUM_PUBLIC_ARTIFACT_ROOT' => $root . '/public',
            'FORUM_TASK_QUEUE_DATABASE_PATH' => $root . '/queue.sqlite3', 'FORUM_SECRETS_PATH' => $root . '/secrets.php',
            'FORUM_RETRACTION_SWEEP_STATE_PATH' => $root . '/cron-state.json'];
        $prefix = 'env';
        foreach ($env as $key => $value) $prefix .= ' ' . escapeshellarg($key . '=' . $value);
        $output = []; $code = 0;
        exec($prefix . ' ' . escapeshellarg(dirname(__DIR__) . '/v3') . ' task-queue cron --log=' . escapeshellarg($root . '/cron.log'), $output, $code);
        assertSame(0, $code);
        $line = null;
        foreach ($output as $row) if (str_contains($row, '* * * * * ')) $line = trim($row);
        assertSame(true, is_string($line));
        $command = substr($line, strlen('* * * * * '));
        exec($prefix . ' bash -c ' . escapeshellarg($command), $ignored, $code);
        assertSame(0, $code);
        assertSame(false, is_file($root . '/cron-state.json'));
        assertSame(3, (int) $pdo->query("SELECT count(*) FROM llm_exchanges WHERE status='retracted'")->fetchColumn());
        $store = new \ForumRewrite\TaskQueue\SqliteTaskQueueStore(new PDO('sqlite:' . $root . '/queue.sqlite3'));
        assertSame('completed', $store->latestByType($store::RETRACTION_SWEEP)['status']);
    }


    public function testWalReaderBlocksCompletionUntilCheckpointCanRetirePayload(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $pdo->exec('PRAGMA journal_mode=WAL');
        $reader = new PDO('sqlite:' . $root . '/exchanges.sqlite3');
        $reader->beginTransaction();
        $reader->query('SELECT * FROM llm_exchanges')->fetchAll();
        $failed = false;
        try { $sweep->run(); } catch (RuntimeException) { $failed = true; }
        assertSame(true, $failed);
        assertSame(false, is_file($root . '/state.json'));
        $reader->rollBack();
        $reader = null;
        assertSame('complete', $sweep->run()['status']);
        clearstatcache();
        assertSame(true, !is_file($root . '/exchanges.sqlite3-wal') || filesize($root . '/exchanges.sqlite3-wal') === 0);
    }


    public function testKilledTransactionRecoversAndCleansOnRetry(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $code = 'require ' . var_export(dirname(__DIR__) . '/autoload.php', true) . ';'
            . '$pdo = new PDO(' . var_export('sqlite:' . $root . '/exchanges.sqlite3', true) . ');'
            . '\ForumRewrite\Retraction\ExchangeScrubber::prepare($pdo); $pdo->exec("BEGIN IMMEDIATE");'
            . '\ForumRewrite\Retraction\ExchangeScrubber::scrub($pdo, 1, ["target"]);'
            . 'fwrite(STDOUT, "ready\n"); fflush(STDOUT); sleep(30);';
        $process = proc_open([PHP_BINARY, '-r', $code], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
        assertSame(true, is_resource($process));
        assertSame("ready\n", fgets($pipes[1]));
        proc_terminate($process, 9);
        foreach ($pipes as $pipe) fclose($pipe);
        proc_close($process);
        assertSame(3, $sweep->run()['cleaned']);
        assertSame(0, $sweep->state()['affected']);
        assertSame(false, file_exists($root . '/exchanges.sqlite3-journal'));
    }


    public function testScrubMarkerDoesNotExcuseResidualPayloadOrMetadata(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $sweep->run();
        $pdo->exec("UPDATE llm_exchanges SET provider_model='RESIDUAL-SECRET', request_json=" . $pdo->quote(json_encode(['body' => 'RESIDUAL-SECRET'])) . ' WHERE id=1');
        assertSame(1, $sweep->preview()['affected']);
        assertSame(1, $sweep->run()['cleaned']);
        assertSame(false, str_contains(json_encode($pdo->query('SELECT * FROM llm_exchanges WHERE id=1')->fetch(PDO::FETCH_ASSOC)), 'RESIDUAL-SECRET'));
    }


}
