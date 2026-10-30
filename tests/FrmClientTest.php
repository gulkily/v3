<?php
require_once __DIR__.'/AgentPostingApiTest.php';
final class FrmClientTest
{
    public static function run(string $script, array $args): array
    {
        $cmd='python3 '.escapeshellarg($script).' '.implode(' ',array_map('escapeshellarg',$args));
        exec($cmd.' 2>&1',$output,$code); return [$code,implode("\n",$output)];
    }
    public function testFirstWriteReuseAndLegacyFallback(): void
    {
        foreach ([false,true] as $legacy) {
            [$server,$repo,$db,$root]=SingleRequestHttpTest::server(['FORUM_UNICODE_AUTHORED_TEXT'=>'true']);
            $identitiesBefore=count(glob($repo.'/records/identity/*'));
            try {
                if ($legacy) {
                    $router=$server->directory.'/public/router.php';
                    $source=file_get_contents($router);
                    $source=str_replace('<?php', '<?php if (str_starts_with($_SERVER["REQUEST_URI"],"/api/text_rules")) { http_response_code(404); echo "not found"; return; } ob_start(fn($body) => preg_replace("/^POST \\/api\\/create_signed_post[^\\n]*\\n/m", "", $body));', $source);
                    file_put_contents($router,$source);
                }
                $script=$root.'/frm.py'; file_put_contents($script,file_get_contents($server->url.'/frm.py'));
                file_put_contents($root.'/body.txt', "Café — 5 µg, “quoted”, Привет\n");
                $base=['--site',$server->url,'--state-dir',$root.'/client'];
                [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Example','--body-file',$root.'/body.txt']));
                assertSame(0,$code,$out);
                preg_match('/Post-ID: (\S+)/',$out,$match); $id=$match[1];
                $state=glob($root.'/client/*')[0]; $key=file_get_contents($state.'/identity.txt');
                $operation=glob($state.'/operation-*/operation.txt')[0];
                assertStringContains('Protocol: '.($legacy?'prepared':'signed'),file_get_contents($operation));
                foreach ([[],['--parent',$id]] as $parent) {
                    [$code,$out]=self::run($script,array_merge($base,['reply','--thread',$id,'--body-file',$root.'/body.txt'],$parent));
                    assertSame(0,$code,$out);
                }
                preg_match('/Fingerprint: (\S+)/',$key,$fingerprint);
                [$code,$out]=self::run($script,['--site',$server->url,'--state-dir',$root.'/selected','reply','--thread',$id,'--body-file',$root.'/body.txt','--signing-key',$fingerprint[1],'--gpg-home',$state.'/keyring']);
                assertSame(0,$code,$out);
                assertSame($key,file_get_contents($state.'/identity.txt'));
                assertSame($identitiesBefore+1,count(glob($repo.'/records/identity/*')));
                [$code,$out]=self::run($script,array_merge($base,['read',$id])); assertSame(0,$code,$out);
                assertStringContains('Café — 5 µg, “quoted”, Привет',$out);
                \ForumRewrite\Security\GpgHomeCleaner::dispose($state.'/keyring');
            } finally {$server->close();}
        }
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
