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
}
