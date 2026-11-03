<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Qdb\QdbBoardPolicy;
use ForumRewrite\ReadModel\QdbPage;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\ReadModel\ThreadRepository;

final class QdbPageBodyCounter extends PDOStatement
{
    public static int $bodies = 0;
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $row = parent::fetch($mode, $cursorOrientation, $cursorOffset);
        if (is_array($row) && array_key_exists('root_post_body', $row)) self::$bodies++;
        return $row;
    }
}

final class QdbPageTest
{
    private function fixture(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        foreach (ReadModelSchema::statements() as $sql) $pdo->exec($sql);
        $post = $pdo->prepare('INSERT INTO posts(post_id,thread_id,created_at,body,board_tags_json,sequence_number) VALUES(?,?,?, ?,?,?)');
        $thread = $pdo->prepare('INSERT INTO threads(root_post_id,root_post_created_at,last_activity_at,body_preview,reply_count,last_post_id,board_tags_json,thread_labels_json,retracted_at) VALUES(?,?,?,?,0,?,?,?,?)');
        $tags = ['["news"]', '{"x":"news","n":3}', '["identity","news"]', 'broken', '"news"', '["News"]', '["general"]'];
        for ($i = 0; $i < 100; $i++) {
            $id = sprintf('thread-%03d%s', $i, $i % 4 === 0 ? '-qdb-bad' : '-qdb-' . $i);
            $date = '2026-10-' . sprintf('%02d', 1 + $i % 10);
            $activity = '2026-10-' . sprintf('%02d', 20 - $i % 10);
            $post->execute([$id,$id,$date,'Body '.$id,$tags[$i % 7],$i]);
            $thread->execute([$id,$date,$activity,'Preview '.$id,$id,$tags[$i % 7],$i % 3 ? '[]' : '["pinned"]',$i % 11 ? null : $date]);
        }
        return $pdo;
    }

    public function testLatestMatchesLegacyAndLoadsOnlySelectedBodies(): void
    {
        $pdo = $this->fixture();
        $policy = new QdbBoardPolicy('/unused', static fn () => null);
        $expected = $policy->eligibleQuotes(ThreadRepository::fetchThreads($pdo));
        usort($expected, static fn ($a, $b) =>
            (int) in_array('pinned', $b['thread_labels'], true) <=> (int) in_array('pinned', $a['thread_labels'], true)
            ?: strcmp($b['root_post_created_at'], $a['root_post_created_at'])
            ?: strcmp($b['root_post_id'], $a['root_post_id']));
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [QdbPageBodyCounter::class]);
        foreach ([-1, 1, 2, 3, 999] as $page) {
            QdbPageBodyCounter::$bodies = 0;
            $actual = QdbPage::page($pdo, 'latest', $page);
            $legacy = $policy->paginate($expected, 'latest', $page);
            assertSame($legacy['threads'], $actual['threads']);
            assertSame(count($expected), $actual['count']);
            assertSame($legacy['pagination'], $policy->pagination($actual['count'], $actual['page'], '/latest'));
            assertSame(count($actual['threads']), QdbPageBodyCounter::$bodies);
            assertTrue(QdbPageBodyCounter::$bodies <= 25);
            assertTrue(!$pdo->inTransaction());
        }
        $pdo->beginTransaction();
        QdbPage::page($pdo, 'latest', 1);
        assertTrue($pdo->inTransaction());
        $pdo->rollBack();
        $pdo->exec('DELETE FROM threads');
        assertSame(['threads'=>[], 'count'=>0, 'page'=>1], QdbPage::page($pdo, 'latest', 999));
    }

    public function testScorePagesMatchLegacyPinsAndTies(): void
    {
        $pdo = $this->fixture();
        $pdo->exec("UPDATE threads SET score_total = CASE CAST(substr(root_post_id, 8, 3) AS INTEGER) % 5 WHEN 0 THEN -10 WHEN 1 THEN 1336 WHEN 2 THEN 1338 WHEN 3 THEN 1337 ELSE 0 END");
        $policy = new QdbBoardPolicy('/unused', static fn () => null);
        $rows = $policy->eligibleQuotes(ThreadRepository::fetchThreads($pdo));
        foreach (['top', 'leetness'] as $section) {
            $expected = $rows;
            usort($expected, static function ($a, $b) use ($section, $policy) {
                $pin = (int) in_array('pinned', $b['thread_labels'], true) <=> (int) in_array('pinned', $a['thread_labels'], true);
                $newest = strcmp($b['root_post_created_at'], $a['root_post_created_at']) ?: strcmp($b['root_post_id'], $a['root_post_id']);
                return $pin ?: ($section === 'top' ? ($b['score_total'] <=> $a['score_total']) ?: $newest : $policy->compareLeetness($a, $b, $newest));
            });
            foreach ([1, 2, 999] as $page) {
                $selected = QdbPage::page($pdo, $section, $page);
                assertSame($policy->paginate($expected, $section, $page)['threads'], $selected['threads']);
                assertSame(count($expected), $selected['count']);
            }
        }
    }

    public function testSearchPreservesLiteralMatchingAndActivityOrder(): void
    {
        $pdo = $this->fixture();
        $pdo->exec("UPDATE posts SET body = body || CASE sequence_number % 3 WHEN 0 THEN ' Alpha %_ äÄ' WHEN 1 THEN ' beta 100% ' ELSE ' under_score' END");
        $policy = new QdbBoardPolicy('/unused', static fn () => null);
        $rows = $policy->eligibleQuotes(ThreadRepository::fetchThreads($pdo));
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [QdbPageBodyCounter::class]);
        foreach (['', '   ', ' % ', '_', 'ALPHA', 'ä', 'Ä', 'not-present', "' OR 1=1", 'Body'] as $term) {
            $needle = trim($term);
            $expected = $needle === '' ? [] : array_values(array_filter($rows, static fn ($row) => stripos($row['root_post_body'], $needle) !== false));
            foreach ([0, 1, 2, 999] as $page) {
                QdbPageBodyCounter::$bodies = 0;
                $actual = QdbPage::page($pdo, 'search', $page, $term);
                assertSame($policy->paginate($expected, 'search', $page)['threads'], $actual['threads']);
                assertSame(count($expected), $actual['count']);
                assertSame(count($actual['threads']), QdbPageBodyCounter::$bodies);
            }
        }
    }

    public function testSearchLargeBodyAndFailureRetry(): void
    {
        $pdo = $this->fixture();
        $id = $pdo->query("SELECT root_post_id FROM threads WHERE retracted_at IS NULL AND root_post_id LIKE '%-qdb-1'")->fetchColumn();
        $stmt = $pdo->prepare('UPDATE posts SET body=? WHERE post_id=?');
        $stmt->execute([str_repeat('x', 16 * 1024 * 1024 - 20) . ' UniqueLargeNeedle', $id]);
        $result = QdbPage::page($pdo, 'search', 1, 'UniqueLargeNeedle');
        assertSame(1, $result['count']);
        assertSame($id, $result['threads'][0]['root_post_id']);
        assertTrue(strlen($result['threads'][0]['root_post_body']) > 15 * 1024 * 1024);
        unset($result);
        $pdo->exec('ALTER TABLE posts RENAME TO missing_posts');
        try {
            QdbPage::page($pdo, 'search', 1, 'x');
            throw new RuntimeException('Expected missing-table failure');
        } catch (PDOException $error) {
            assertTrue(!$pdo->inTransaction());
        }
        $pdo->exec('ALTER TABLE missing_posts RENAME TO posts');
        assertSame(1, QdbPage::page($pdo, 'search', 1, 'UniqueLargeNeedle')['count']);
    }

    public function testSelectedPagesKeepViewerReactionState(): void
    {
        $pdo = $this->fixture();
        $first = QdbPage::page($pdo, 'latest', 1)['threads'];
        $second = QdbPage::page($pdo, 'latest', 2)['threads'];
        $ids = [$first[0]['root_post_id'], $second[0]['root_post_id']];
        $insert = $pdo->prepare('INSERT INTO viewer_tags (record_family, target_id, author_identity_id, tag) VALUES(?,?,?,?)');
        foreach ($ids as $id) {
            $insert->execute(['thread-labels', $id, 'viewer-identity', 'upvote']);
            $insert->execute(['post-reactions', $id, 'viewer-identity', 'flag']);
        }
        $anonymous = new QdbBoardPolicy('/unused', static fn () => null, null, $pdo);
        assertSame(['voted'=>[], 'flagged'=>[]], $anonymous->viewerReactionState($first));
        foreach ([0, 1] as $approved) {
            $viewer = new QdbBoardPolicy('/unused', static fn () => ['identity_id'=>'viewer-identity', 'is_approved'=>$approved], null, $pdo);
            foreach ([$first, $second] as $index => $threads) {
                assertSame(['voted'=>[$ids[$index]=>true], 'flagged'=>[$ids[$index]=>true]], $viewer->viewerReactionState($threads));
            }
        }
    }

    public function testPriorVersionStartupRebuildsBeforeLatest(): void
    {
        $this->assertStartupRebuild(40);
        $this->assertStartupRebuild(42);
    }

    private function assertStartupRebuild(int $version): void
    {
        $database = tempnam(sys_get_temp_dir(), 'qdb-page-startup-');
        $prior = getenv('FORUM_SITE_ID'); putenv('FORUM_SITE_ID=qdb');
        try {
            $render = static function (Application $app): string { ob_start(); $app->handle('GET','/latest'); return (string)ob_get_clean(); };
            $app = new Application(dirname(__DIR__), __DIR__.'/fixtures/parity_minimal_v1', $database);
            $render($app);
            $pdo = new PDO('sqlite:'.$database);
            $originalBody = $pdo->query("SELECT body FROM posts WHERE post_id='root-001'")->fetchColumn();
            $pdo->exec("UPDATE metadata SET value='$version' WHERE key='schema_version'");
            $pdo->exec("UPDATE posts SET body='STALE QDB BODY'");
            $fresh = new Application(dirname(__DIR__), __DIR__.'/fixtures/parity_minimal_v1', $database);
            $html = $render($fresh);
            assertStringNotContains('STALE QDB BODY', $html);
            assertSame(ReadModelMetadata::SCHEMA_VERSION, $pdo->query("SELECT value FROM metadata WHERE key='schema_version'")->fetchColumn());
            assertSame($originalBody, $pdo->query("SELECT body FROM posts WHERE post_id='root-001'")->fetchColumn());
            assertStringContains('Latest', $html);
        } finally {
            putenv($prior === false ? 'FORUM_SITE_ID' : 'FORUM_SITE_ID='.$prior);
            foreach (glob($database.'*') ?: [] as $path) @unlink($path);
        }
    }
}
