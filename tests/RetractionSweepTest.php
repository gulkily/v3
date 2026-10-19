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
        $pdo = new PDO('sqlite:' . $root . '/exchanges.sqlite3');
        $recorder = new LlmExchangeRecorder($pdo);
        foreach ([['target'], ['other', 'target'], ['other'], null] as $ids) {
            $ctx = ['post_id' => $ids[0] ?? 'unknown', 'arbitrary' => 'SECRET-CONTEXT'];
            if ($ids !== null) $ctx['exchange_dependencies'] = ExchangeDependencies::manifest($ids);
            $recorder->record($ctx, ['body' => 'SECRET-REQUEST'], ['body' => 'SECRET-RESPONSE'], 'completed', 1, ['message' => 'SECRET-ERROR']);
        }
        return [$root, $pdo, new RetractionSweep($root, $root . '/exchanges.sqlite3', $root . '/state.json')];
    }

    public function testPreviewClassifiesWithoutMutation(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $before = hash_file('sha256', $root . '/exchanges.sqlite3');
        $result = $sweep->preview();
        assertSame(2, $result['affected']);
        assertSame(1, $result['uncertain']);
        assertSame(4, $result['examined']);
        assertSame($before, hash_file('sha256', $root . '/exchanges.sqlite3'));
        assertSame(false, file_exists($root . '/state.json'));
        assertSame(true, $sweep->preview(1)['remaining']);
    }
    public function testBoundedCleanupPreservesUnknownAndUnaffectedRows(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $preserved = $pdo->query('SELECT * FROM llm_exchanges WHERE id >= 3')->fetchAll(PDO::FETCH_ASSOC);
        assertSame(true, $sweep->run(1)['remaining']);
        assertSame(1, $sweep->state()['cursor']);
        do { $result = $sweep->run(1); } while ($result['remaining']);
        foreach ([1, 2] as $id) {
            $row = $pdo->query('SELECT * FROM llm_exchanges WHERE id=' . $id)->fetch(PDO::FETCH_ASSOC);
            assertSame('{}', $row['request_json']);
            assertSame('{}', $row['response_json']);
            assertSame('{}', $row['error_json']);
            assertSame(false, str_contains($row['context_json'], 'SECRET'));
            assertSame('retracted', $row['status']);
        }
        assertSame($preserved, $pdo->query('SELECT * FROM llm_exchanges WHERE id >= 3')->fetchAll(PDO::FETCH_ASSOC));
        assertSame(1, $sweep->state()['totals']['uncertain']);
        assertSame(0, $sweep->preview()['affected']);
        assertSame(0, $sweep->run()['cleaned']);
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
        file_put_contents($root . '/records/post-retractions/post-retraction-target.txt', 'bad');
        try { $sweep->run(); throw new RuntimeException('Expected failure'); }
        catch (RuntimeException $error) { assertSame('failed', $sweep->state()['status']); }
    }
    public function testLateResultsScrubAtRecordingAndUncoordinatedWritesBecomePending(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $sweep->run();
        $recorder = new LlmExchangeRecorder($pdo, true, $root);
        $id = $recorder->record(['post_id' => 'target', 'provider' => 'SECRET', 'exchange_dependencies' => ExchangeDependencies::manifest(['target'])],
            ['body' => 'LATE-SECRET'], ['body' => 'LATE-SECRET'], 'provider_error', 1, ['message' => 'LATE-SECRET']);
        $row = $pdo->query('SELECT * FROM llm_exchanges WHERE id=' . $id)->fetch(PDO::FETCH_ASSOC);
        assertSame(false, str_contains(json_encode($row), 'SECRET'));
        assertSame('pending', $sweep->state()['status']);
        $sweep->run();
        assertSame('complete', $sweep->state()['status']);
        (new LlmExchangeRecorder($pdo))->record(['post_id' => 'target', 'exchange_dependencies' => ExchangeDependencies::manifest(['target'])], ['body' => 'old writer'], [], 'completed', 1);
        assertSame('pending', $sweep->state()['status']);
        assertSame(1, $sweep->run()['cleaned']);
    }
    public function testArtifactSweepResumesAndKeepsCurrentGeneration(): void
    {
        [$root, $pdo] = $this->fixture();
        $artifacts = $root . '/static';
        mkdir($artifacts . '/releases/old/posts', 0700, true);
        mkdir($artifacts . '/.candidate-old/offline', 0700, true);
        mkdir($artifacts . '/posts', 0700, true);
        file_put_contents($artifacts . '/releases/old/posts/target.html', 'OLD-SECRET');
        file_put_contents($artifacts . '/.candidate-old/offline/snapshot.sqlite3', 'OLD-SECRET');
        file_put_contents($artifacts . '/posts/fresh.html', 'Clean content');
        symlink('releases/old', $artifacts . '/current');
        $gen = \ForumRewrite\Retraction\PublicationGuard::generation($root);
        file_put_contents($artifacts . '/posts/fresh.html.retraction-generation', $gen);
        $sweep = new RetractionSweep($root, $root . '/exchanges.sqlite3', $root . '/artifacts.json', [$artifacts]);
        assertSame(true, $sweep->preview(100)['artifacts']['affected'] > 0);
        $runs = 0;
        do { $result = $sweep->run(2); assertSame(true, ++$runs < 100); } while ($result['remaining']);
        assertSame(false, is_link($artifacts . '/current'));
        assertSame(false, is_file($artifacts . '/releases/old/posts/target.html'));
        assertSame(false, is_file($artifacts . '/.candidate-old/offline/snapshot.sqlite3'));
        assertSame('Clean content', file_get_contents($artifacts . '/posts/fresh.html'));
        assertSame(0, $sweep->run(100)['artifacts']['cleaned']);
    }

    public function testStalePublicationAndDirectorySymlinksAreRejected(): void
    {
        [$root] = $this->fixture();
        $old = ['root' => $root, 'generation' => 'old'];
        try {
            \ForumRewrite\Retraction\PublicationGuard::publish($old, $root . '/output.html', function () use ($root): void { file_put_contents($root . '/output.html', 'STALE'); });
            throw new RuntimeException('Expected rejection');
        } catch (RuntimeException $error) { assertSame(false, is_file($root . '/output.html')); }
        mkdir($root . '/static');
        symlink($root . '/records', $root . '/static/posts');
        $sweep = new RetractionSweep($root, $root . '/exchanges.sqlite3', $root . '/unsafe.json', [$root . '/static']);
        try { $sweep->run(); throw new RuntimeException('Expected rejection'); }
        catch (RuntimeException $error) { assertSame('failed', $sweep->state()['status']); }
        assertSame(true, is_file($root . '/records/posts/target.txt'));
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
        $state = json_decode(file_get_contents($root . '/cron-state.json'), true);
        assertSame('complete', $state['status']);
        assertSame(2, $state['cleaned']);
        $store = new \ForumRewrite\TaskQueue\SqliteTaskQueueStore(new PDO('sqlite:' . $root . '/queue.sqlite3'));
        assertSame('completed', $store->latestByType($store::RETRACTION_SWEEP)['status']);
    }

    public function testSweepQueueCoalescesContinuesAndKeepsTerminalFailuresVisible(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $store = new \ForumRewrite\TaskQueue\SqliteTaskQueueStore(new PDO('sqlite::memory:'));
        \ForumRewrite\Retraction\RetractionSweepFactory::reconcile($sweep, $store);
        \ForumRewrite\Retraction\RetractionSweepFactory::reconcile($sweep, $store);
        assertSame(1, $store->counts()['queued']);
        $worker = new \ForumRewrite\TaskQueue\TaskQueueWorker($store, static function (): void {}, runRetractionSweep: fn () => $sweep->run(1));
        assertSame(1, $worker->run()['continued']);
        for ($i = 0; $i < 5 && $store->counts()['queued'] > 0; ++$i) $worker->run();
        assertSame(1, $store->counts()['completed']);
        file_put_contents($root . '/records/post-retractions/post-retraction-target.txt', 'broken');
        $store->enqueue($store::RETRACTION_SWEEP, 'retraction-sweep', 1);
        $worker->run();
        assertSame(1, $store->counts()['failed']);
        \ForumRewrite\Retraction\RetractionSweepFactory::reconcile($sweep, $store);
        assertSame(0, $store->counts()['queued']);
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
        assertSame('failed', $sweep->state()['status']);
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
        assertSame(2, $sweep->run()['cleaned']);
        assertSame('complete', $sweep->state()['status']);
        assertSame(false, file_exists($root . '/exchanges.sqlite3-journal'));
    }

    public function testPublicationLockSerializesIndependentPublishers(): void
    {
        [$root] = $this->fixture();
        $code = 'require ' . var_export(dirname(__DIR__) . '/autoload.php', true) . '; putenv("FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=0");'
            . '$root=' . var_export($root, true) . ';'
            . 'try { \ForumRewrite\Retraction\PublicationGuard::publish(["root"=>$root,"generation"=>\ForumRewrite\Retraction\PublicationGuard::generation($root)], $root . "/output.html", function () use ($root) { file_put_contents($root . "/output.html", "unsafe"); }); } catch (RuntimeException) { exit(7); }';
        $result = \ForumRewrite\Retraction\PublicationGuard::lock($root)->withExclusiveLock(function () use ($code): int {
            $process = proc_open([PHP_BINARY, '-r', $code], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
            foreach ($pipes as $pipe) fclose($pipe);
            return proc_close($process);
        });
        assertSame(7, $result);
        assertSame(false, is_file($root . '/output.html'));
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

    public function testResumedTraversalRejectsAnAncestorReplacedBySymlink(): void
    {
        [$root] = $this->fixture();
        mkdir($root . '/static', 0700);
        mkdir($root . '/outside/nested', 0700, true);
        file_put_contents($root . '/outside/nested/keep.html', 'Keep this');
        symlink($root . '/outside', $root . '/static/posts');
        $failed = false;
        try {
            \ForumRewrite\Retraction\ArtifactSweep::run([$root . '/static'], \ForumRewrite\Retraction\PublicationGuard::generation($root),
                [['path' => $root . '/static/posts/nested', 'after' => '', 'top' => false]], 250);
        } catch (RuntimeException) { $failed = true; }
        assertSame(true, $failed);
        assertSame('Keep this', file_get_contents($root . '/outside/nested/keep.html'));
    }

    public function testPublicationRequiresSuppressionInItsSourceModel(): void
    {
        [$root] = $this->fixture();
        $path = $root . '/source.sqlite3';
        $source = new PDO('sqlite:' . $path);
        $source->exec('CREATE TABLE posts (post_id TEXT, retracted_at TEXT)');
        $source->exec("INSERT INTO posts VALUES ('target', NULL)");
        $failed = false;
        try { \ForumRewrite\Retraction\PublicationGuard::begin($path, $root); }
        catch (RuntimeException) { $failed = true; }
        assertSame(true, $failed);
        $source->exec("UPDATE posts SET retracted_at='2026-10-14'");
        $token = \ForumRewrite\Retraction\PublicationGuard::begin($path, $root);
        \ForumRewrite\Retraction\PublicationGuard::publish($token, $root . '/fresh.html', function () use ($root): void { file_put_contents($root . '/fresh.html', 'safe'); });
        assertSame('safe', file_get_contents($root . '/fresh.html'));
    }
    public function testCompletedCoverageIsPeriodicallyRediscoveredWithoutFlooding(): void
    {
        [$root, $pdo, $sweep] = $this->fixture();
        $sweep->run();
        $store = new \ForumRewrite\TaskQueue\SqliteTaskQueueStore(new PDO('sqlite::memory:'));
        \ForumRewrite\Retraction\RetractionSweepFactory::reconcile($sweep, $store);
        assertSame(0, $store->counts()['queued']);
        $state = json_decode(file_get_contents($root . '/state.json'), true);
        $state['updated_at'] = '2000-01-01T00:00:00Z';
        file_put_contents($root . '/state.json', json_encode($state));
        \ForumRewrite\Retraction\RetractionSweepFactory::reconcile($sweep, $store);
        \ForumRewrite\Retraction\RetractionSweepFactory::reconcile($sweep, $store);
        assertSame(1, $store->counts()['queued']);
    }
    public function testManualCliPreviewRunAndStatusUseTheSameResumablePolicy(): void
    {
        [$root] = $this->fixture();
        mkdir($root . '/static');
        mkdir($root . '/public');
        $invoke = static function (string $command, bool $json = true) use ($root): array|string {
            $process = proc_open([dirname(__DIR__) . '/v3', 'retraction-sweep', $command,
                '--repository-root=' . $root, '--exchange-database-path=' . $root . '/exchanges.sqlite3',
                '--state-path=' . $root . '/cli-state.json', '--static-html-root=' . $root . '/static', '--limit=1', ...($json ? ['--json'] : [])],
                [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, null,
                [...getenv(), 'FORUM_PUBLIC_ARTIFACT_ROOT' => $root . '/public']);
            fclose($pipes[0]);
            $output = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            assertSame(0, proc_close($process), $error);
            return $json ? json_decode($output, true, 512, JSON_THROW_ON_ERROR) : $output;
        };
        $hash = hash_file('sha256', $root . '/exchanges.sqlite3');
        assertStringContains('Retraction sweep: preview', $invoke('preview', false));
        assertStringContains('Exchanges to clean: 1', $invoke('preview', false));
        assertSame(1, $invoke('preview')['affected']);
        assertSame($hash, hash_file('sha256', $root . '/exchanges.sqlite3'));
        assertSame(false, file_exists($root . '/cli-state.json'));
        assertSame('pending', $invoke('run')['status']);
        assertSame(1, $invoke('status')['cursor']);
        for ($i = 0; $i < 10 && $invoke('status')['remaining']; ++$i) $invoke('run');
        assertSame('complete', $invoke('status')['status']);
        assertStringContains('Retraction sweep: complete', $invoke('status', false));
        assertStringContains('Exchanges cleaned: 2', $invoke('status', false));
        assertSame(2, $invoke('status')['totals']['cleaned']);
        assertSame(1, $invoke('status')['totals']['uncertain']);
        assertStringContains('Retraction sweep: pending', $invoke('run', false));
    }
}
