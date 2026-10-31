<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Qdb\QdbBoardPolicy;
use ForumRewrite\Qdb\QdbWelcomeSelection;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\Support\BoundedProcess;
use ForumRewrite\Support\OperationBudget;

final class WelcomeBodyCounter extends PDOStatement
{
    public static int $bodies = 0;
    public function fetch(int $mode = PDO::FETCH_DEFAULT, int $cursorOrientation = PDO::FETCH_ORI_NEXT, int $cursorOffset = 0): mixed
    {
        $row = parent::fetch($mode, $cursorOrientation, $cursorOffset);
        if (is_array($row) && array_key_exists('root_post_body', $row)) self::$bodies++;
        return $row;
    }
}

final class QdbWelcomeSelectionTest
{
    public function testMatchesLegacyWelcomeForMixedEligibilityAndNewsOrdering(): void
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
        $policy = new QdbBoardPolicy('/unused', static fn () => null);
        $threads = ThreadRepository::fetchThreads($pdo);
        $news = $policy->newsThreads($threads);
        $expected = ['qdbQuoteCount'=>count($policy->eligibleQuotes($threads)), 'recentThreads'=>array_slice($policy->eligibleQuotes($threads),0,5), 'newsThreads'=>array_slice($news,0,3), 'hasMoreNews'=>count($news)>3];
        $pdo->setAttribute(PDO::ATTR_STATEMENT_CLASS, [WelcomeBodyCounter::class]);
        WelcomeBodyCounter::$bodies = 0;
        assertSame($expected, QdbWelcomeSelection::read($pdo,$policy));
        $distinct = array_unique(array_column([...$expected['recentThreads'], ...$expected['newsThreads']], 'root_post_id'));
        assertSame(count($distinct), WelcomeBodyCounter::$bodies);
        assertTrue(WelcomeBodyCounter::$bodies <= 8);
        $pdo->exec('DELETE FROM threads');
        assertSame(['qdbQuoteCount'=>0,'recentThreads'=>[],'newsThreads'=>[],'hasMoreNews'=>false], QdbWelcomeSelection::read($pdo,$policy));
    }

    public function testVersion39StartupRebuildsBeforeWelcomeAndRendersNews(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'welcome-startup-');
        $prior = getenv('FORUM_SITE_ID'); putenv('FORUM_SITE_ID=qdb');
        try {
            $render = static function (Application $app): string { ob_start(); $app->handle('GET','/'); return (string)ob_get_clean(); };
            $app = new Application(dirname(__DIR__), __DIR__.'/fixtures/parity_minimal_v1', $database);
            assertStringContains('Welcome!', $render($app));
            $pdo = new PDO('sqlite:'.$database);
            $pdo->exec("UPDATE metadata SET value='39' WHERE key='schema_version'");
            $pdo->exec("UPDATE posts SET body='STALE WELCOME BODY'; UPDATE threads SET board_tags_json='[\"news\"]'");
            $fresh = new Application(dirname(__DIR__), __DIR__.'/fixtures/parity_minimal_v1', $database);
            $html = $render($fresh);
            assertStringNotContains('STALE WELCOME BODY',$html);
            assertSame(ReadModelMetadata::SCHEMA_VERSION,$pdo->query("SELECT value FROM metadata WHERE key='schema_version'")->fetchColumn());
            // Ordinary route and template, after rebuilding, consume selected news bodies.
            $pdo->exec("UPDATE threads SET board_tags_json='[\"news\"]' WHERE root_post_id='root-001'");
            $html = $render($fresh);
            assertStringContains('qdb-news-item',$html);
            assertStringContains('Hello world',$html);
        } finally {
            putenv($prior === false ? 'FORUM_SITE_ID' : 'FORUM_SITE_ID='.$prior);
            foreach (glob($database.'*') ?: [] as $path) @unlink($path);
        }
    }

    public function testWelcomeRouteStaysBoundedAsQuoteCorpusGrows(): void
    {
        $peaks=[];
        foreach ([1000,10000] as $count) {
            $database=tempnam(sys_get_temp_dir(),'welcome-memory-');
            try {
                $result=BoundedProcess::run([PHP_BINARY,'-d','memory_limit=32M',__DIR__.'/Support/qdb_welcome_memory.php',$database,(string)$count],new OperationBudget(60),8192,'welcome memory regression');
                assertSame(0,$result['exit_code'],$result['stderr'].$result['stdout']);
                assertTrue(preg_match('/Peak bytes: (\d+)/',$result['stdout'],$match)===1,$result['stdout']);
                $peaks[]=(int)$match[1];
            } finally { foreach (glob($database.'*') ?: [] as $path) @unlink($path); }
        }
        assertTrue($peaks[1] <= $peaks[0]+4*1024*1024,'Fixed welcome selection must not retain the corpus.');
    }
}
