<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Offline\PublicOfflineSnapshotBuilder;

final class PublicOfflineSnapshotBuilderTest
{
    public function testBuildsBoundedPublicSnapshotWithoutOperationalTables(): void
    {
        $suffix = bin2hex(random_bytes(6));
        $sourcePath = sys_get_temp_dir() . '/forum-offline-source-' . $suffix . '.sqlite3';
        $targetPath = sys_get_temp_dir() . '/forum-offline-target-' . $suffix . '.sqlite3';

        try {
            $this->createSource($sourcePath);
            $result = (new PublicOfflineSnapshotBuilder())->build($sourcePath, $targetPath);
            $snapshot = new PDO('sqlite:' . $targetPath);

            assertSame(55, $result['thread_count']);
            assertSame('55', (string) $snapshot->query('SELECT COUNT(*) FROM threads')->fetchColumn());
            assertSame('110', (string) $snapshot->query('SELECT COUNT(*) FROM posts')->fetchColumn());
            assertSame('thread-055', (string) $snapshot->query('SELECT root_post_id FROM threads ORDER BY last_activity_at DESC LIMIT 1')->fetchColumn());
            assertSame('1', (string) $snapshot->query("SELECT COUNT(*) FROM threads WHERE root_post_id = 'thread-001'")->fetchColumn());
            assertSame('1', (string) $snapshot->query("SELECT COUNT(*) FROM threads WHERE root_post_id = 'thread-005'")->fetchColumn());
            assertSame('0', (string) $snapshot->query("SELECT COUNT(*) FROM posts WHERE post_id = 'reply-hidden'")->fetchColumn());
            assertSame('0', (string) $snapshot->query("SELECT COUNT(*) FROM threads WHERE root_post_id IN ('thread-hidden', 'thread-bootstrap')")->fetchColumn());
            assertSame('0', (string) $snapshot->query("SELECT COUNT(*) FROM sqlite_master WHERE type = 'table' AND name = 'post_analyses'")->fetchColumn());
            assertSame('1', (string) $snapshot->query("SELECT COUNT(*) FROM metadata WHERE key = 'snapshot_version' AND value = '5'")->fetchColumn());
            assertTrue($result['size_bytes'] <= PublicOfflineSnapshotBuilder::DEFAULT_MAX_BYTES);
            assertSame((string) PublicOfflineSnapshotBuilder::DEFAULT_MAX_BYTES, (string) $snapshot->query("SELECT value FROM metadata WHERE key = 'max_bytes'")->fetchColumn());
            assertSame(3, $result['public_key_count']);
            assertSame('3', (string) $snapshot->query('SELECT COUNT(*) FROM public_keys')->fetchColumn());
            assertSame('1', (string) $snapshot->query("SELECT COUNT(*) FROM public_keys WHERE identity_id = 'approved-unrelated'")->fetchColumn());
            assertSame('1', (string) $snapshot->query("SELECT COUNT(*) FROM public_keys WHERE identity_id = 'unapproved-selected' AND is_approved = 0")->fetchColumn());
            assertSame('0', (string) $snapshot->query("SELECT COUNT(*) FROM public_keys WHERE identity_id = 'unapproved-unselected'")->fetchColumn());
            assertTrue(PublicOfflineSnapshotBuilder::DEFAULT_THREAD_LIMIT > 50);
            assertTrue(PublicOfflineSnapshotBuilder::DEFAULT_MAX_BYTES > 25 * 1024 * 1024);
        } finally {
            @unlink($sourcePath);
            @unlink($targetPath);
        }
    }

    public function testIncludesEffectiveRootMetaWithoutPrivateScoresOrChangingSource(): void
    {
        $root=sys_get_temp_dir().'/meta-snapshot-'.bin2hex(random_bytes(5));mkdir($root);
        $names=['FORUM_SECRETS_PATH','FASTMOD_AUTOMATIC_TAGGING_ENABLED','FAST_SCORING_ENABLED'];
        $old=[];foreach($names as $name){$old[$name]=getenv($name);putenv($name);}
        try {
            symlink(dirname(__DIR__).'/prompts',$root.'/prompts');
            $this->createSource($root.'/source.sqlite3');
            $source=new PDO('sqlite:'.$root.'/source.sqlite3');
            $source->exec('ALTER TABLE posts ADD COLUMN retracted_at TEXT');
            $path=$root.'/private.sqlite3';
            $store=new \ForumRewrite\Scoring\SqliteFastScoreStore(new PDO('sqlite:'.$path));
            $private=['FAST_SCORING_ENABLED'=>true,'FAST_SCORING_DATABASE_PATH'=>$path,'FAST_SCORING_RUBRICS'=>['meta']];
            file_put_contents($root.'/secrets.php','<?php return '.var_export($private,true).';');
            putenv('FORUM_SECRETS_PATH='.$root.'/secrets.php');putenv('FASTMOD_AUTOMATIC_TAGGING_ENABLED=true');
            $revision=\ForumRewrite\Scoring\FastScoringRubricRevision::fromConfig(\ForumRewrite\Scoring\FastScoringConfig::fromPrivateConfig($private),$root);
            $post=$source->query("SELECT * FROM posts WHERE post_id='thread-055'")->fetch(PDO::FETCH_ASSOC);
            $hash=(new \ForumRewrite\Scoring\FastScoreContextFactory(static fn($id)=>null))->contentHashForPost($post);
            $store->save('thread-055',$hash,$revision,['status'=>'scored','source'=>'llm','scores'=>['meta'=>0.95]]);
            (new PublicOfflineSnapshotBuilder($root))->build($root.'/source.sqlite3',$root.'/snapshot.sqlite3');
            $snapshot=new PDO('sqlite:'.$root.'/snapshot.sqlite3');
            assertSame('["meta"]',$snapshot->query("SELECT thread_labels_json FROM threads WHERE root_post_id='thread-055'")->fetchColumn());
            assertSame(false,$snapshot->query("SELECT name FROM sqlite_master WHERE name LIKE 'fastmod%' OR name='post_fast_scores'")->fetchColumn());
            assertSame('[]',$source->query("SELECT thread_labels_json FROM threads WHERE root_post_id='thread-055'")->fetchColumn());
            assertSame(false,is_file($root.'/snapshot.sqlite3.fastmod-generation'));
            putenv('FASTMOD_AUTOMATIC_TAGGING_ENABLED=false');
            (new PublicOfflineSnapshotBuilder($root))->build($root.'/source.sqlite3',$root.'/disabled.sqlite3');
            assertSame('[]',(new PDO('sqlite:'.$root.'/disabled.sqlite3'))->query("SELECT thread_labels_json FROM threads WHERE root_post_id='thread-055'")->fetchColumn());
            putenv('FASTMOD_AUTOMATIC_TAGGING_ENABLED=true');$store->pruneBefore('2100-01-01');
            (new PublicOfflineSnapshotBuilder($root))->build($root.'/source.sqlite3',$root.'/pruned.sqlite3');
            assertSame('[]',(new PDO('sqlite:'.$root.'/pruned.sqlite3'))->query("SELECT thread_labels_json FROM threads WHERE root_post_id='thread-055'")->fetchColumn());
            assertSame('["meta"]',$snapshot->query("SELECT thread_labels_json FROM threads WHERE root_post_id='thread-055'")->fetchColumn());
        } finally {
            foreach($old as $name=>$value)putenv($value===false?$name:$name.'='.$value);
            foreach(glob($root.'/*') as $file)unlink($file);rmdir($root);
        }
    }

    public function testSelectionKeepsAllPinsBeforeRecentAndPreservesVisibility(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'offline-selection-');
        $target = $source . '.snapshot';
        try {
            $this->createSource($source);
            $pdo = new PDO('sqlite:' . $source);
            $pdo->exec("UPDATE posts SET is_hidden=1 WHERE post_id='thread-055'");
            $pdo->exec("UPDATE threads SET board_tags_json='{\"tag\":\"identity\"}' WHERE root_post_id='thread-054'");
            $pdo->exec("UPDATE threads SET thread_labels_json='{\"tag\":\"pinned\"}' WHERE root_post_id='thread-010'");
            $pdo->exec("UPDATE threads SET board_tags_json='broken',thread_labels_json='broken' WHERE root_post_id='thread-053'");
            $builder = new PublicOfflineSnapshotBuilder();
            $method = new ReflectionMethod($builder, 'snapshotThreadRows');
            $method->setAccessible(true);
            assertSame(['thread-010','thread-001','thread-053','thread-052','thread-051'], array_column($method->invoke($builder,$pdo,3),'root_post_id'));
            $result = $builder->build($source,$target,3);
            assertSame(5,$result['thread_count']);
            assertSame(['thread-001','thread-010','thread-051','thread-052','thread-053'], (new PDO('sqlite:'.$target))->query('SELECT root_post_id FROM threads ORDER BY root_post_id')->fetchAll(PDO::FETCH_COLUMN));
            $pdo->exec("UPDATE threads SET thread_labels_json='[\"pinned\"]'");
            assertSame(53,count($method->invoke($builder,$pdo,3)), 'Pins must not be capped by recent-thread limit.');
            $pdo->exec('DELETE FROM threads');
            assertSame([],$method->invoke($builder,$pdo,3));
        } finally { foreach (glob($source.'*') ?: [] as $path) @unlink($path); }
    }

    public function testPinnedSizeFailureKeepsPublishedSnapshot(): void
    {
        $source = tempnam(sys_get_temp_dir(), 'offline-pin-limit-');
        $target = $source . '.snapshot';
        try {
            $this->createSource($source);
            $builder = new PublicOfflineSnapshotBuilder();
            $builder->build($source,$target,3);
            $before = hash_file('sha256',$target);
            $manifest = file_get_contents($target.'.manifest.json');
            (new PDO('sqlite:'.$source))->prepare("UPDATE posts SET body=? WHERE post_id='thread-001'")->execute([str_repeat('x',1024*1024)]);
            $failed = false;
            try {
                $builder->build($source,$target,3,65536);
            } catch (RuntimeException $error) {
                // SQLite may auto-rollback on SQLITE_FULL; existing error wording varies.
                $failed = true;
            }
            assertTrue($failed);
            assertSame($before,hash_file('sha256',$target));
            assertSame($manifest,file_get_contents($target.'.manifest.json'));
            assertSame([],glob($target.'.tmp-*'));
        } finally { foreach (glob($source.'*') ?: [] as $path) @unlink($path); }
    }

    public function testFullSnapshotMemoryStaysBoundedWithFixedPinsAndRecentSelection(): void
    {
        $peaks=[];
        foreach ([1000,10000] as $count) {
            $source=tempnam(sys_get_temp_dir(),'offline-memory-');
            try {
                $result=\ForumRewrite\Support\BoundedProcess::run([PHP_BINARY,'-d','memory_limit=32M',__DIR__.'/Support/offline_selection_memory.php',$source,(string)$count],new \ForumRewrite\Support\OperationBudget(60),8192,'offline memory regression');
                assertSame(0,$result['exit_code'],$result['stderr'].$result['stdout']);
                assertTrue(preg_match('/Peak bytes: (\d+)/',$result['stdout'],$match)===1,$result['stdout']);
                $peaks[]=(int)$match[1];
            } finally { foreach (glob($source.'*') ?: [] as $path) @unlink($path); }
        }
        assertTrue($peaks[1] <= $peaks[0]+4*1024*1024,'Fixed snapshot selection must not retain all candidate rows.');
    }

    private function createSource(string $path): void
    {
        $pdo = new PDO('sqlite:' . $path);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->exec('CREATE TABLE threads (
            root_post_id TEXT PRIMARY KEY, root_post_created_at TEXT, last_activity_at TEXT,
            subject TEXT, body_preview TEXT, reply_count INTEGER, last_post_id TEXT,
            board_tags_json TEXT, thread_labels_json TEXT, score_total INTEGER
        )');
        $pdo->exec('CREATE TABLE posts (
            post_id TEXT PRIMARY KEY, created_at TEXT, thread_id TEXT, parent_id TEXT,
            subject TEXT, body TEXT, board_tags_json TEXT, thread_type TEXT,
            author_identity_id TEXT, author_label TEXT, author_profile_slug TEXT, sequence_number INTEGER, is_hidden INTEGER
        )');
        $pdo->exec('CREATE TABLE profiles (
            identity_id TEXT PRIMARY KEY, signer_fingerprint TEXT, public_key TEXT, is_approved INTEGER
        )');
        $pdo->exec('CREATE TABLE post_analyses (post_id TEXT, raw_response_json TEXT)');
        $pdo->exec("INSERT INTO post_analyses VALUES ('thread-055', '{\"private\":true}')");
        $profile = $pdo->prepare('INSERT INTO profiles VALUES (?, ?, ?, ?)');
        $profile->execute(['approved-selected', 'A1', 'approved selected key', 1]);
        $profile->execute(['approved-unrelated', 'A2', 'approved unrelated key', 1]);
        $profile->execute(['unapproved-selected', 'U1', 'unapproved selected key', 0]);
        $profile->execute(['unapproved-unselected', 'U2', 'unapproved unselected key', 0]);

        $thread = $pdo->prepare('INSERT INTO threads VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        $post = $pdo->prepare('INSERT INTO posts VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
        for ($number = 1; $number <= 55; $number++) {
            $id = sprintf('thread-%03d', $number);
            $timestamp = sprintf('2026-02-%02dT00:00:00Z', $number);
            $labels = $number === 1 ? '["pinned"]' : '[]';
            $thread->execute([$id, $timestamp, $timestamp, 'Subject ' . $number, 'Preview ' . $number, 1, 'reply-' . $number, '["general"]', $labels, 0]);
            $identityId = $number === 55 ? 'unapproved-selected' : 'approved-selected';
            $post->execute([$id, $timestamp, $id, null, 'Subject ' . $number, 'Root ' . $number, '["general"]', null, $identityId, 'Author ' . $number, 'author-' . $number, $number * 2, 0]);
            $post->execute(['reply-' . $number, $timestamp, $id, $id, null, 'Reply ' . $number, '["general"]', null, $identityId, 'Reply author ' . $number, 'reply-author-' . $number, $number * 2 + 1, 0]);
        }
        $post->execute(['reply-hidden', '2026-03-01T00:00:00Z', 'thread-055', 'thread-055', null, 'Hidden reply', '["general"]', null, 'unapproved-unselected', 'Hidden author', null, 9998, 1]);
        $thread->execute(['thread-hidden', '2027-01-01T00:00:00Z', '2027-01-01T00:00:00Z', 'Hidden', '', 0, 'thread-hidden', '["general"]', '[]', 0]);
        $post->execute(['thread-hidden', '2027-01-01T00:00:00Z', 'thread-hidden', null, 'Hidden', '', '["general"]', null, 'unapproved-unselected', 'Hidden', null, 999, 1]);
        $thread->execute(['thread-bootstrap', '2027-01-02T00:00:00Z', '2027-01-02T00:00:00Z', 'Bootstrap', '', 0, 'thread-bootstrap', '["identity"]', '[]', 0]);
        $post->execute(['thread-bootstrap', '2027-01-02T00:00:00Z', 'thread-bootstrap', null, 'Bootstrap', '', '["identity"]', null, 'unapproved-unselected', 'Bootstrap', null, 1000, 0]);
    }
}

if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition): void
    {
        if (!$condition) {
            throw new RuntimeException('Failed asserting that condition is true.');
        }
    }
}
