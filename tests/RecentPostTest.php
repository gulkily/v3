<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/RecentPostFixture.php';
use ForumRewrite\ReadModel\RecentPostRepository;
use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\Application;

final class RecentPostTest
{
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        foreach (ReadModelSchema::statements() as $statement) $pdo->exec($statement);
        RecentPostFixture::seed($pdo);
        return $pdo;
    }

    public function testEligibilityContextAndBoundedText(): void
    {
        $pdo=$this->database();
        $pdo->exec("UPDATE posts SET body='<' || replace(hex(zeroblob(10000)),'0','x') WHERE post_id='unsigned'");
        $items=RecentPostRepository::page($pdo)['items'];
        assertSame(12,count($items));
        $rows=array_column($items,null,'post_id');
        foreach(['hidden','removed','bootstrap','expansion'] as $id) assertTrue(!isset($rows[$id]));
        assertSame(1024,strlen($rows['unsigned']['body']));
        assertSame(0,(int)$rows['unsigned']['attribution_counted']);
        assertSame(null,$rows['hidden-context']['context_subject']);
        assertSame(null,$rows['missing-context']['context_id']);
        assertSame('old-thread',$rows['reply-1']['context_id']);
    }

    public function testPagingTiesDeletionArrivalAndEmptyRecovery(): void
    {
        $pdo=$this->database();$all=RecentPostRepository::page($pdo)['items'];$ids=[];$cursor=null;
        do {$page=RecentPostRepository::page($pdo,$cursor,3);$ids=array_merge($ids,array_column($page['items'],'post_id'));$cursor=$page['next']===null?null:['direction'=>'next','boundary'=>$page['next']];}while($cursor!==null);
        assertSame(array_column($all,'post_id'),$ids);
        $first=RecentPostRepository::page($pdo,null,3);
        $second=RecentPostRepository::page($pdo,['direction'=>'next','boundary'=>$first['next']],3);
        $back=RecentPostRepository::page($pdo,['direction'=>'previous','boundary'=>$second['previous']],3);
        assertSame($first['items'],$back['items']);
        $pdo->prepare('DELETE FROM posts WHERE post_id=?')->execute([$first['next']['post_id']]);
        assertSame($second['items'],RecentPostRepository::page($pdo,['direction'=>'next','boundary'=>$first['next']],3)['items']);
        $pdo->exec("UPDATE posts SET created_at='2027-01-01T00:00:00Z' WHERE post_id='new-thread'");
        assertSame('new-thread',RecentPostRepository::page($pdo)['items'][0]['post_id']);
        $empty=RecentPostRepository::page($pdo,['direction'=>'next','boundary'=>['created_at'=>'2000','post_id'=>'gone']]);
        assertSame([],$empty['items']);assertTrue($empty['previous']!==null);
    }

    public function testMalformedBoundariesAreRejected(): void
    {
        foreach ([[],['direction'=>'bad','boundary'=>[]],['direction'=>'next','boundary'=>['created_at'=>[],'post_id'=>'x']]] as $cursor) {
            $caught=false;try{RecentPostRepository::page($this->database(),$cursor);}catch(InvalidArgumentException){$caught=true;}
            assertTrue($caught);
        }
    }

    public function testPriorVersionStartupRestoresIndex(): void
    {
        $file=sys_get_temp_dir().'/recent-upgrade-'.bin2hex(random_bytes(5)).'.sqlite3';
        $root=__DIR__.'/fixtures/parity_minimal_v1';
        try {
            $app=new Application(dirname(__DIR__),$root,$file);ob_start();$app->handle('GET','/recent/');ob_end_clean();
            $pdo=new PDO('sqlite:'.$file);$pdo->exec("UPDATE metadata SET value='38' WHERE key='schema_version'; DROP INDEX posts_recent_idx");
            $app=new Application(dirname(__DIR__),$root,$file);ob_start();$app->handle('GET','/recent/');ob_end_clean();
            assertSame(1,(int)$pdo->query("SELECT count(*) FROM sqlite_master WHERE name='posts_recent_idx'")->fetchColumn());
            RecentPostRepository::page($pdo);
        } finally {unset($pdo,$app);@unlink($file);}
    }
    public function testNormalPageRendersSafePreviewsAndRecovery(): void
    {
        $file=sys_get_temp_dir().'/recent-page-'.bin2hex(random_bytes(5)).'.sqlite3';
        $app=new Application(dirname(__DIR__),__DIR__.'/fixtures/parity_minimal_v1',$file);
        $render=static function(string $uri) use ($app): string {ob_start();$app->handle('GET',$uri);return (string)ob_get_clean();};
        $render('/recent/');$pdo=new PDO('sqlite:'.$file);$pdo->exec('DELETE FROM posts');RecentPostFixture::seed($pdo);
        $html=$render('/recent/');
        assertSame(12,substr_count($html,'class="recent-entry"'));
        assertStringContains('/posts/reply-5',$html);
        assertStringContains('(unsigned)',$html);
        assertStringContains('Unavailable thread',$html);
        assertStringContains('>Image</a>',$html);
        assertStringNotContains('Hidden secret',$html);
        assertStringNotContains('Removed secret',$html);
        assertStringNotContains('data-role="thread-compose"',$html);
        $pdo->exec("UPDATE posts SET body='<script>alert(1)</script>' WHERE post_id='unsigned'");
        assertStringContains('&lt;script&gt;', $render('/recent/'));
        assertStringContains('First page',$render('/recent/?position=broken'));
        assertStringContains('role="alert"',$render('/recent/?position[]=x'));
        $pdo->exec('DELETE FROM posts');assertStringContains('No posts yet.', $render('/recent/'));
        unset($pdo,$app);@unlink($file);
    }

}
