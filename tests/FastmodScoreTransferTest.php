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

    public function testRejectsWholeMalformedSupplement(): void
    {
        $valid = FastmodScoreTransfer::encode([self::row()]);
        foreach ([str_replace('v1', 'v2', $valid), $valid . "Secret: leak\n", str_replace('0.9', '1.2', $valid), str_replace('0.9', 'NaN', $valid), str_replace('2025-01-02', '2025-02-30', $valid), $valid . "Post: post-1\n", str_repeat('x', FastmodScoreTransfer::MAX_BYTES + 1)] as $text) {
            try { FastmodScoreTransfer::decode($text); throw new RuntimeException('Accepted invalid supplement'); }
            catch (InvalidArgumentException) {}
        }
    }
}
