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

    public function testNumberedPagesCoverEveryPostOnceAndEndEmpty(): void
    {
        $pdo=$this->database();$all=RecentPostRepository::page($pdo)['items'];$ids=[];$number=1;
        do {$page=RecentPostRepository::page($pdo,$number,3);$ids=array_merge($ids,array_column($page['items'],'post_id'));$number++;}while($page['has_next']);
        assertSame(array_column($all,'post_id'),$ids);
        assertSame(4,$number-1);
        $past=RecentPostRepository::page($pdo,99,3);
        assertSame([],$past['items']);assertSame(false,$past['has_next']);
        $pdo->exec("UPDATE posts SET created_at='2027-01-01T00:00:00Z' WHERE post_id='new-thread'");
        assertSame('new-thread',RecentPostRepository::page($pdo)['items'][0]['post_id']);
    }

    public function testWithFindsThePageHoldingAPost(): void
    {
        $pdo=$this->database();$number=1;
        do {$page=RecentPostRepository::page($pdo,$number,3);foreach($page['items'] as $item) assertSame($number,RecentPostRepository::pageOf($pdo,$item['post_id'],3));$number++;}while($page['has_next']);
        foreach(['hidden','removed','bootstrap','expansion','no-such-post'] as $id) assertSame(null,RecentPostRepository::pageOf($pdo,$id,3));
        assertSame('/recent/?page=2#post-reply-1',\ForumRewrite\Pagination\PageNavigation::withLocation('/recent/',[],2,'reply-1'));
        assertSame('/recent/#post-reply-1',\ForumRewrite\Pagination\PageNavigation::withLocation('/recent/',[],1,'reply-1'));
        foreach ([null,'',['x'],'a b','<x>',str_repeat('a',513)] as $bad) assertSame(null,\ForumRewrite\Pagination\PageNavigation::withId($bad));
    }

    public function testPageNumbersAreReadAsPlainPositiveIntegers(): void
    {
        foreach ([null,'','0','-1','abc','2x','01',['2'],2] as $value) assertSame(1,\ForumRewrite\Pagination\PageNavigation::pageNumber($value));
        assertSame(2,\ForumRewrite\Pagination\PageNavigation::pageNumber('2'));
        assertSame(\ForumRewrite\Pagination\PageNavigation::MAX_PAGE,\ForumRewrite\Pagination\PageNavigation::pageNumber('999999'));
        $links=\ForumRewrite\Pagination\PageNavigation::numbered('/recent/',[],2,true);
        assertSame('/recent/',$links['previous_href']);assertSame('/recent/?page=3',$links['next_href']);
        assertSame(null,\ForumRewrite\Pagination\PageNavigation::numbered('/recent/',[],1,false)['previous_href']);
    }

    public function testPriorVersionStartupRestoresIndex(): void
    {
        $file=sys_get_temp_dir().'/recent-upgrade-'.bin2hex(random_bytes(5)).'.sqlite3';
        $root=__DIR__.'/fixtures/parity_minimal_v1';
        try {
            $app=new Application(dirname(__DIR__),$root,$file);ob_start();$app->handle('GET','/recent/');ob_end_clean();
            $pdo=new PDO('sqlite:'.$file);$pdo->exec("UPDATE metadata SET value='41' WHERE key='schema_version'; DROP INDEX posts_recent_idx");
            $app=new Application(dirname(__DIR__),$root,$file);ob_start();$app->handle('GET','/recent/');ob_end_clean();
            $pdo=new PDO('sqlite:'.$file); // the startup rebuild swapped in a new file
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
        assertSame(12,substr_count($html,'class="thread-row recent-entry"'));
        assertStringContains('href="/threads/old-thread#post-reply-5"',$html);
        assertStringContains('href="/threads/new-thread"',$html);
        assertStringContains('href="/posts/missing-context"',$html);
        assertStringContains('(unsigned)',$html);
        assertStringContains('Unavailable thread',$html);
        assertStringContains('>Image</a>',$html);
        assertStringNotContains('Hidden secret',$html);
        assertStringNotContains('Removed secret',$html);
        assertStringNotContains('data-role="thread-compose"',$html);
        $pdo->exec("UPDATE posts SET body='<script>alert(1)</script>' WHERE post_id='unsigned'");
        assertStringContains('&lt;script&gt;', $render('/recent/'));
        assertSame(12,substr_count($render('/recent/?page=abc'),'class="thread-row recent-entry"'));
        assertSame(12,substr_count($render('/recent/?page[]=x'),'class="thread-row recent-entry"'));
        $old=$render('/recent/?position=eyJ2ZXJzaW9uIjoxfQ');
        assertSame(12,substr_count($old,'class="thread-row recent-entry"'));assertStringNotContains('role="alert"',$old);
        $past=$render('/recent/?page=9');
        assertStringContains('No posts on this page',$past);assertStringContains('First page',$past);
        assertStringNotContains('position=',$html);
        assertStringContains('id="post-reply-1"',$html);
        $redirect=$render('/recent/?with=reply-1');
        assertStringContains('Opening that post in Recent.',$redirect);assertStringContains('/recent/#post-reply-1',$redirect);
        assertSame(12,substr_count($render('/recent/?with=no-such-post'),'class="thread-row recent-entry"'));
        $pdo->exec('DELETE FROM posts');assertStringContains('No posts yet', $render('/recent/'));
        unset($pdo,$app);@unlink($file);
    }

    public function testHighTrafficModeFallsThroughToRecent(): void
    {
        $root = sys_get_temp_dir() . '/recent-high-traffic-' . bin2hex(random_bytes(5));
        mkdir($root . '/public', 0700, true);
        symlink(dirname(__DIR__) . '/templates', $root . '/templates');
        symlink(dirname(__DIR__) . '/public/assets', $root . '/public/assets');
        $repository = __DIR__ . '/fixtures/parity_minimal_v1';
        $cache = new \ForumRewrite\Host\HighTrafficCache($root, $repository, $root . '/read.sqlite3', $root . '/public');
        try {
            $cache->initialize();
            symlink($cache->root . '/pages', $root . '/public/.high-traffic');
            $cache->atomic($cache->root . '/state.json', json_encode(['enabled'=>true], JSON_THROW_ON_ERROR));
            assertTrue($cache->enabled());
            assertSame(null, \ForumRewrite\Host\HighTrafficRoute::canonical('/recent/'));
            $controller = new \ForumRewrite\Host\FrontController($root, $repository, $root . '/read.sqlite3', $root . '/static');
            ob_start();$controller->handle('GET', '/recent/');$html=(string)ob_get_clean();
            assertStringContains('class="thread-row recent-entry"', $html);
            assertSame(200, http_response_code());
        } finally {
            unlink($root . '/public/assets');unlink($root . '/templates');unlink($root . '/public/.high-traffic');
            \ForumRewrite\Host\HighTrafficCache::removeTemporaryTree($root);
        }
    }

}
