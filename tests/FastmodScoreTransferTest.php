<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
use ForumRewrite\Scoring\FastmodScoreTransfer;

final class FastmodScoreTransferTest
{
    public static function row(): array
    {
        return ['post_id' => 'post-1', 'content_hash' => str_repeat('a', 64), 'rubric_revision' => str_repeat('b', 64), 'created_at' => '2025-01-01T00:00:00+00:00', 'updated_at' => '2025-01-02T00:00:00+00:00', 'scores' => ['moderation' => 0.9, 'quality' => 0.000005]];
    }

    public function testRoundTripAndAllowlist(): void
    {
        $row = self::row();
        $text = FastmodScoreTransfer::encode([$row + ['secret' => 'never-export']]);
        assertSame(true, [$row] == FastmodScoreTransfer::decode($text));
        assertSame(false, str_contains($text, 'never-export'));
        assertSame([], FastmodScoreTransfer::decode(FastmodScoreTransfer::encode([])));
    }

    public function testDestinationWinsAndOriginalAgesSurviveRetry(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new \ForumRewrite\Scoring\SqliteFastScoreStore($pdo);
        $row = self::row(); $hashes = [$row['post_id'] => $row['content_hash']]; $ids = array_keys($row['scores']);
        $preview = $store->importScoredRows([$row, $row], $hashes, $row['rubric_revision'], $ids, true);
        assertSame(1, $preview['imported']); assertSame(1, $preview['duplicate']);
        assertSame(null, $store->find($row['post_id'], $row['content_hash'], $row['rubric_revision']));
        assertSame(1, $store->importScoredRows([$row], $hashes, $row['rubric_revision'], $ids)['imported']);
        $saved = $store->find($row['post_id'], $row['content_hash'], $row['rubric_revision']);
        assertSame($row['created_at'], $saved['created_at']); assertSame($row['updated_at'], $saved['updated_at']);
        assertSame('import', $saved['source']);
        $row['scores']['quality'] = 0.8;
        assertSame(1, $store->importScoredRows([$row], $hashes, $row['rubric_revision'], $ids)['existing']);
        assertSame($saved, $store->find($row['post_id'], $row['content_hash'], $row['rubric_revision']));
        assertSame(1, $store->importScoredRows([$row], [], $row['rubric_revision'], $ids)['incompatible']);
        assertSame(1, $store->importScoredRows([$row], $hashes, str_repeat('c', 64), $ids)['incompatible']);
        assertSame(1, $store->importScoredRows([$row], $hashes, $row['rubric_revision'], ['moderation'])['incompatible']);
        assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM fast_score_work')->fetchColumn());
    }

    public function testFailureRowsWinAndTransactionRollsBack(): void
    {
        $pdo = new PDO('sqlite::memory:'); $store = new \ForumRewrite\Scoring\SqliteFastScoreStore($pdo);
        $row = self::row(); $ids = array_keys($row['scores']);
        $store->save($row['post_id'], $row['content_hash'], $row['rubric_revision'], ['status' => 'provider_error']);
        assertSame(1, $store->importScoredRows([$row], ['post-1' => $row['content_hash']], $row['rubric_revision'], $ids)['existing']);
        $pdo->exec('DELETE FROM post_fast_scores');
        $pdo->exec("CREATE TRIGGER interrupt_import BEFORE INSERT ON post_fast_scores WHEN NEW.post_id = 'post-2' BEGIN SELECT RAISE(ABORT, 'interrupted'); END");
        $second = $row; $second['post_id'] = 'post-2';
        try { $store->importScoredRows([$row, $second], ['post-1' => $row['content_hash'], 'post-2' => $row['content_hash']], $row['rubric_revision'], $ids); throw new RuntimeException('Expected interruption'); }
        catch (PDOException) {}
        assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM post_fast_scores')->fetchColumn());
    }

    public function testBothExportsRefreshForWalScoresWithoutGitChanges(): void
    {
        require_once __DIR__ . '/Support/ImportTestWorkspace.php';
        $w = new ImportTestWorkspace(); $root = $w->repository('source');
        $w->put($root . '/records/posts/post-1.txt', $w->post('post-1'));
        $w->put($root . '/fastmod-scores.txt', 'reserved path must be replaced'); $w->git($root);
        $backup = new \ForumRewrite\Images\ImageBackup($root, $w->root . '/index.sqlite3', $w->root);
        $empty = $backup->export('tar.gz'); fclose($empty['stream']);
        mkdir($w->root . '/state/private', 0700, true);
        $pdo = new PDO('sqlite:' . $w->root . '/state/private/fast_scores.sqlite3'); $pdo->exec('PRAGMA journal_mode=WAL'); $pdo->exec('PRAGMA wal_autocheckpoint=0');
        $store = new \ForumRewrite\Scoring\SqliteFastScoreStore($pdo);
        $post = (new \ForumRewrite\Canonical\CanonicalRecordRepository($root))->loadPost('records/posts/post-1.txt');
        $row = self::row(); $row['content_hash'] = (new \ForumRewrite\Scoring\FastScoreContextFactory(static fn ($id) => null))->contentHashForPost(['post_id' => $post->postId, 'subject' => $post->subject, 'body' => $post->body]);
        $images = $backup->export('images'); fclose($images['stream']);
        $store->importScoredRows([$row], ['post-1' => $row['content_hash']], $row['rubric_revision'], array_keys($row['scores']));
        $tar = $backup->export('tar.gz'); fclose($tar['stream']);
        assertSame(false, $tar['path'] === $empty['path']);
        $text = (new PharData($tar['path']))['repository/fastmod-scores.txt']->getContent();
        assertSame(true, [$row] == FastmodScoreTransfer::decode($text));
        $zip = $backup->export('zip'); fclose($zip['stream']);
        $archive = new ZipArchive(); $archive->open($zip['path']); assertSame($text, $archive->getFromName('repository/fastmod-scores.txt')); $archive->close();
        $again = $backup->export('images'); fclose($again['stream']); assertSame($images['path'], $again['path']);
        $pdo->exec('DELETE FROM post_fast_scores');
        $pruned = $backup->export('tar.gz'); fclose($pruned['stream']); assertSame($empty['path'], $pruned['path']);
    }

    public function testArchivePreviewAcceptsOnlyPlannedPostsAndDoesNotCreatePrivateState(): void
    {
        require_once __DIR__ . '/Support/ImportTestWorkspace.php';
        $w = new ImportTestWorkspace(); $root = $w->repository('source'); $destination = $w->repository('destination');
        $w->put($root . '/records/posts/post-1.txt', $w->post('post-1')); $w->git($root); $w->git($destination);
        $row = self::row();
        $config = \ForumRewrite\Scoring\FastScoringConfig::fromPrivateConfig(\ForumRewrite\Support\PrivateConfig::load(dirname(__DIR__)));
        $composition = (new \ForumRewrite\Scoring\FastmodSelection(dirname(__DIR__)))->composition($config);
        $post = (new \ForumRewrite\Canonical\CanonicalRecordRepository($root))->loadPost('records/posts/post-1.txt');
        $row['content_hash'] = (new \ForumRewrite\Scoring\FastScoreContextFactory(static fn ($id) => null))->contentHashForPost(['post_id' => $post->postId, 'subject' => $post->subject, 'body' => $post->body]);
        $row['rubric_revision'] = $composition['revision']; $row['scores'] = array_fill_keys($composition['ids'], 0.8);
        $w->put($root . '/fastmod-scores.txt', FastmodScoreTransfer::encode([$row]));
        $prior = getenv('FAST_SCORING_DATABASE_PATH'); putenv('FAST_SCORING_DATABASE_PATH=' . $w->root . '/private/scores.sqlite3');
        try {
            $runner = new \ForumRewrite\Import\ContentImportRunner($destination, $w->root . '/index.sqlite3');
            $report = $runner->run($root, static function () { throw new RuntimeException('Preview published'); }, true);
            assertSame(1, $report['scores']['counts']['imported']); assertSame(false, is_dir($w->root . '/private'));
            $w->put($destination . '/records/posts/post-1.txt', $w->post('post-1', body: 'local conflict')); $w->git($destination);
            $report = $runner->run($root, static function () {}, true); assertSame(1, $report['scores']['counts']['incompatible']);
            $w->put($root . '/fastmod-scores.txt', 'Format: future-version');
            $archive = $w->root . '/source.tar.gz';
            assertSame(0, $w->command(['tar', '-czf', $archive, '-C', $w->root, 'source'])[0]);
            $extracted = (new \ForumRewrite\Import\RepositoryArchive())->extract($archive, $w->root . '/extracted');
            $report = $runner->run($extracted['root'], static function () {}, true);
            assertSame('rejected', $report['scores']['status']); assertSame('partial', $report['status']);
        } finally { $prior === false ? putenv('FAST_SCORING_DATABASE_PATH') : putenv('FAST_SCORING_DATABASE_PATH=' . $prior); }
    }

    public function testRecoveryRetainsPayloadAndRejectsChangedInputOrDestination(): void
    {
        require_once __DIR__ . '/Support/ImportTestWorkspace.php';
        foreach (['resume', 'changed', 'missing', 'destination'] as $case) {
            $w = new ImportTestWorkspace(); $source = $w->repository('source'); $target = $w->repository('target'); $w->git($target);
            $w->put($source . '/records/posts/post-1.txt', $w->post('post-1'));
            $w->put($source . '/fastmod-scores.txt', FastmodScoreTransfer::encode([self::row()]));
            $db = $w->root . '/index.sqlite3';
            try {
                (new \ForumRewrite\Import\ContentImportRunner($target, $db, static function ($phase) { if ($phase === 'prepared') throw new RuntimeException('stop'); }))->run($source, static function () {});
                throw new LogicException('Expected stop');
            } catch (RuntimeException $error) { assertSame('stop', $error->getMessage()); }
            $pending = json_decode(file_get_contents($target . '/.git/instance-import/pending.json'), true);
            $saved = $target . '/.git/instance-import/' . $pending['id'] . '/fastmod-scores.txt';
            assertSame(true, is_file($saved));
            $w->command(['rm', '-rf', '--', $source]);
            if ($case === 'changed') file_put_contents($saved, 'changed');
            if ($case === 'missing') unlink($saved);
            $prior = getenv('FAST_SCORING_DATABASE_PATH');
            if ($case === 'destination') putenv('FAST_SCORING_DATABASE_PATH=' . $w->root . '/other.sqlite3');
            try {
                $report = (new \ForumRewrite\Import\ContentImportRunner($target, $db))->run(null, static function () {});
                assertSame('resume', $case); assertSame('complete', $report['status']);
            } catch (RuntimeException $error) {
                if ($case === 'resume') throw $error;
                assertSame(false, is_file($target . '/records/posts/post-1.txt'));
                assertSame(true, is_file($target . '/.git/instance-import/pending.json'));
            } finally { $prior === false ? putenv('FAST_SCORING_DATABASE_PATH') : putenv('FAST_SCORING_DATABASE_PATH=' . $prior); }
        }
    }

    public static function compatibleRow(string $source): array
    {
        $config = \ForumRewrite\Scoring\FastScoringConfig::fromPrivateConfig(\ForumRewrite\Support\PrivateConfig::load(dirname(__DIR__)));
        $composition = (new \ForumRewrite\Scoring\FastmodSelection(dirname(__DIR__)))->composition($config);
        $post = (new \ForumRewrite\Canonical\CanonicalRecordRepository($source))->loadPost('records/posts/post-1.txt');
        $row = self::row();
        $row['content_hash'] = (new \ForumRewrite\Scoring\FastScoreContextFactory(static fn ($id) => null))->contentHashForPost(['post_id' => $post->postId, 'subject' => $post->subject, 'body' => $post->body]);
        $row['rubric_revision'] = $composition['revision']; $row['scores'] = array_fill_keys($composition['ids'], 0.99);
        return $row;
    }

    public function testMergeAndOfflinePublicationResumeWithoutSourceOrRescoring(): void
    {
        require_once __DIR__ . '/Support/ImportTestWorkspace.php';
        $prior = [];
        foreach (['FAST_SCORING_DATABASE_PATH', 'FAST_SCORING_ENABLED', 'FASTMOD_AUTOMATIC_TAGGING_ENABLED'] as $key) $prior[$key] = getenv($key);
        putenv('FAST_SCORING_ENABLED=true'); putenv('FASTMOD_AUTOMATIC_TAGGING_ENABLED=true');
        try {
            foreach (['before_scores', 'scores_merged', 'offline_snapshot'] as $phase) {
                $w = new ImportTestWorkspace(); $source = $w->repository('source'); $target = $w->repository('target', true); $w->git($target);
                $w->put($source . '/records/posts/post-1.txt', $w->post('post-1'));
                $row = self::compatibleRow($source); $w->put($source . '/fastmod-scores.txt', FastmodScoreTransfer::encode([$row]));
                $scorePath = $w->root . '/private/scores.sqlite3'; putenv('FAST_SCORING_DATABASE_PATH=' . $scorePath);
                $db = $w->root . '/index.sqlite3'; $static = $w->root . '/static';
                $fail = static function ($at) use ($phase) { if ($at === $phase) throw new RuntimeException('interrupted'); };
                $runner = new \ForumRewrite\Import\ContentImportRunner($target, $db, checkpoint: $fail);
                $publisher = new \ForumRewrite\Import\ImportedContentPublisher(dirname(__DIR__), $target, $db, $static, checkpoint: $fail);
                try { $runner->run($source, $publisher->publishWhileLocked(...)); throw new LogicException('Expected interruption'); }
                catch (RuntimeException $error) { assertSame('interrupted', $error->getMessage()); }
                assertSame(true, is_file($target . '/.git/instance-import/pending.json'));
                $w->command(['rm', '-rf', '--', $source]);
                $publisher = new \ForumRewrite\Import\ImportedContentPublisher(dirname(__DIR__), $target, $db, $static);
                $result = (new \ForumRewrite\Import\ContentImportRunner($target, $db))->run(null, $publisher->publishWhileLocked(...));
                assertSame('complete', $result['status']);
                assertSame(1, $result['scores']['counts'][$phase === 'before_scores' ? 'imported' : 'existing']);
                $pdo = new PDO('sqlite:' . $scorePath); $saved = $pdo->query('SELECT * FROM post_fast_scores')->fetch(PDO::FETCH_ASSOC);
                assertSame($row['updated_at'], $saved['updated_at']); assertSame($row['created_at'], $saved['created_at']); assertSame('import', $saved['source']);
                assertSame(0, (int) $pdo->query('SELECT COUNT(*) FROM fast_score_work')->fetchColumn());
                $offline = new PDO('sqlite:' . $static . '/offline/snapshot.sqlite3');
                assertStringContains('meta', $offline->query("SELECT thread_labels_json FROM threads WHERE root_post_id='post-1'")->fetchColumn());
                $live = \ForumRewrite\Scoring\FastmodMetaRead::forApplication(new PDO('sqlite:' . $db), dirname(__DIR__), $target);
                assertStringContains('meta', $live->query("SELECT thread_labels_json FROM threads WHERE root_post_id='post-1'")->fetchColumn());
                assertSame(false, is_file($target . '/.git/instance-import/pending.json'));
                assertSame('2', trim($w->command(['git', '-C', $target, 'rev-list', '--count', 'HEAD'])[1]));
            }
        } finally { foreach ($prior as $key => $value) putenv($value === false ? $key : $key . '=' . $value); }
    }

    public function testMalformedSupplementDoesNotBlockContentAndLegacySuppressionStaysLocal(): void
    {
        require_once __DIR__ . '/Support/ImportTestWorkspace.php';
        $w = new ImportTestWorkspace(); $source = $w->repository('source'); $target = $w->repository('target'); $w->git($target);
        $w->put($source . '/records/posts/post-1.txt', $w->post('post-1'));
        $w->put($source . '/fastmod-scores.txt', 'Format: unsupported');
        $path = $w->root . '/scores.sqlite3'; $prior = getenv('FAST_SCORING_DATABASE_PATH'); putenv('FAST_SCORING_DATABASE_PATH=' . $path);
        try {
            $runner = new \ForumRewrite\Import\ContentImportRunner($target, $w->root . '/index.sqlite3');
            $report = $runner->run($source, static function () {});
            assertSame('partial', $report['status']); assertSame('rejected', $report['scores']['status']);
            assertSame(true, is_file($target . '/records/posts/post-1.txt')); assertSame(false, is_file($path));
            $row = self::compatibleRow($source); $w->put($source . '/fastmod-scores.txt', FastmodScoreTransfer::encode([$row]));
            // The same canonical post can have a different dated path on the source.
            mkdir($source . '/records/posts/2026/04/10', 0700, true);
            rename($source . '/records/posts/post-1.txt', $source . '/records/posts/2026/04/10/post-1.txt');
            $pdo = new PDO('sqlite:' . $path); new \ForumRewrite\Scoring\SqliteFastScoreStore($pdo);
            $pdo->exec('CREATE TABLE fastmod_meta(post_id TEXT, suppressed INTEGER)'); $pdo->exec("INSERT INTO fastmod_meta VALUES('post-1', 1)");
            $head = trim($w->command(['git', '-C', $target, 'rev-parse', 'HEAD'])[1]);
            $report = $runner->run($source, static function () {}); assertSame(1, $report['scores']['counts']['imported']);
            assertSame($head, trim($w->command(['git', '-C', $target, 'rev-parse', 'HEAD'])[1]));
            assertSame([], \ForumRewrite\Scoring\FastmodDerivedTags::candidates($path, $row['rubric_revision'], \ForumRewrite\Scoring\FastmodDerivedTags::mappings([])));
            assertSame(1, (int) $pdo->query("SELECT suppressed FROM fastmod_meta WHERE post_id='post-1'")->fetchColumn());
        } finally { putenv($prior === false ? 'FAST_SCORING_DATABASE_PATH' : 'FAST_SCORING_DATABASE_PATH=' . $prior); }
    }

    public function testScoreChangeDuringCompressionRejectsStaleArchive(): void
    {
        require_once __DIR__ . '/Support/ImportTestWorkspace.php';
        $w = new ImportTestWorkspace(); $source = $w->repository('source');
        $w->put($source . '/records/posts/post-1.txt', $w->post('post-1')); $w->git($source);
        mkdir($w->root . '/state/private', 0700, true);
        $path = $w->root . '/state/private/fast_scores.sqlite3'; $pdo = new PDO('sqlite:' . $path); $pdo->exec('PRAGMA journal_mode=WAL');
        $row = self::compatibleRow($source);
        (new \ForumRewrite\Scoring\SqliteFastScoreStore($pdo))->importScoredRows([$row], ['post-1' => $row['content_hash']], $row['rubric_revision'], array_keys($row['scores']));
        $changed = array_fill_keys(array_keys($row['scores']), 0.5);
        $update = '<?php $pdo=new PDO(' . var_export('sqlite:' . $path, true) . '); $pdo->prepare("UPDATE post_fast_scores SET scores_json=?")->execute([' . var_export(json_encode($changed), true) . ']);';
        $w->put($w->root . '/update.php', $update);
        $tar = trim($w->command(['which', 'tar'])[1]);
        $w->put($w->root . '/bin/tar', "#!/bin/sh\n" . escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($w->root . '/update.php') . "\nexec " . escapeshellarg($tar) . ' "$@"' . "\n");
        chmod($w->root . '/bin/tar', 0700);
        $prior = getenv('PATH'); putenv('PATH=' . $w->root . '/bin:' . $prior);
        $backup = new \ForumRewrite\Images\ImageBackup($source, $w->root . '/index.sqlite3', $w->root);
        try {
            $backup->export('tar.gz'); throw new LogicException('Stale scores published');
        } catch (\ForumRewrite\Images\ArchiveConflictException) {
            assertSame([], glob($w->root . '/archive-exports/*/metadata'));
        } finally { putenv('PATH=' . $prior); }
        $fresh = $backup->export('tar.gz'); fclose($fresh['stream']);
        $rows = FastmodScoreTransfer::decode((new PharData($fresh['path']))['repository/fastmod-scores.txt']->getContent());
        assertSame(true, $changed == $rows[0]['scores']);
        assertSame([], glob($w->root . '/archive-exports/.build-*'));
    }

    public function testRejectsWholeMalformedSupplement(): void
    {
        $valid = FastmodScoreTransfer::encode([self::row()]);
        foreach ([str_replace('v1', 'v2', $valid), $valid . "Secret: leak\n", str_replace('0.9', '1.2', $valid), str_replace('0.9', 'NaN', $valid), str_replace('2025-01-02', '2025-02-30', $valid), $valid . "Post: post-1\n", str_repeat('x', FastmodScoreTransfer::MAX_BYTES + 1), FastmodScoreTransfer::HEADER . str_repeat("\n\n", FastmodScoreTransfer::MAX_ROWS + 1) . "Post: x", $valid . str_repeat("Score-x: 0.1\n", 70)] as $text) {
            try { FastmodScoreTransfer::decode($text); throw new RuntimeException('Accepted invalid supplement'); }
            catch (InvalidArgumentException) {}
        }
    }
}
