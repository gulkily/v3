<?php
require_once __DIR__.'/AgentPostingApiTest.php';
final class FrmClientTest
{
    public static function run(string $script, array $args): array
    {
        $cmd='python3 '.escapeshellarg($script).' '.implode(' ',array_map('escapeshellarg',$args));
        exec($cmd.' 2>&1',$output,$code); return [$code,implode("\n",$output)];
    }
    public function testDownloadedReaderAndPublicDelivery(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
        try {
            [$h,$script]=AgentPostingApiTest::request($server,'/frm.py');
            assertStringContains('200',$h[0]); assertTrue(in_array('Content-Type: text/plain; charset=utf-8',$h,true));
            assertSame(file_get_contents(dirname(__DIR__).'/public/frm.py'),$script);
            $path=$root.'/downloaded.py'; file_put_contents($path,$script);
            [$code,$out]=self::run($path,['--site',$server->url,'index']); assertSame(0,$code,$out);
            $input=SignedPostIdentityTest::bundle(); [, $post]=SingleRequestHttpTest::request($server,$input);
            foreach(['read','check'] as $action) {
                [$code,$out]=self::run($path,['--site',$server->url,$action,$post['post_id']]); assertSame(0,$code,$out);
                assertStringContains($post['post_id'],$out);
            }
            [$code,$out]=self::run($path,['--site',$server->url,'check','missing']); assertSame(1,$code,$out);
            assertStringContains('Status: missing',$out);
            [$h,$body]=AgentPostingApiTest::request($server,'/frm.py','HEAD'); assertSame('',$body);
        } finally {$server->close();}
        [$server]=SingleRequestHttpTest::server(['FORUM_APPROVED_MEMBERS_ONLY'=>'true']);
        try {
            [$h,$body]=AgentPostingApiTest::request($server,'/frm.py'); assertStringContains('200',$h[0]);
            [$code,$out]=self::run($path,['--site',$server->url,'check','missing']); assertSame(2,$code,$out); assertStringContains('Status: unknown',$out);
        } finally {$server->close();}
    }
}
