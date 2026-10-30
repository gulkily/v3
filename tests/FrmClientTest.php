<?php
require_once __DIR__.'/AgentPostingApiTest.php';
final class FrmClientTest
{
    public static function run(string $script, array $args): array
    {
        $cmd='python3 '.escapeshellarg($script).' '.implode(' ',array_map('escapeshellarg',$args));
        exec($cmd.' 2>&1',$output,$code); return [$code,implode("\n",$output)];
    }
    public function testLostResponseResumeWithoutDuplicateForBothProtocols(): void
    {
        foreach ([false,true] as $legacy) {
            [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
            try {
                $marker=$root.'/lose-response'; file_put_contents($marker,'yes');
                $router=$server->directory.'/public/router.php';
                $route=$legacy?'/api/create_prepared_post':'/api/create_signed_post';
                $hook='if ($_SERVER["REQUEST_URI"] === '.var_export($route,true).') ob_start(function($body) { if (is_file('.var_export($marker,true).')) { unlink('.var_export($marker,true).'); http_response_code(502); return "response lost"; } return $body; });';
                if ($legacy) $hook.='ob_start(fn($body) => preg_replace("/^POST \\/api\\/create_signed_post[^\\n]*\\n/m", "", $body));';
                file_put_contents($router,str_replace('<?php','<?php '.$hook,file_get_contents($router)));
                $script=$root.'/frm.py';file_put_contents($script,file_get_contents($server->url.'/frm.py'));
                file_put_contents($root.'/body.txt',"Recovery body.\n");
                $base=['--site',$server->url,'--state-dir',$root.'/client','--retries','0'];
                $write=['thread','--subject','Recovery','--body-file',$root.'/body.txt'];
                [$code,$out]=self::run($script,array_merge($base,$write)); assertSame(2,$code,$out); assertStringContains('Resume:',$out);
                $operation=glob($root.'/client/*/operation-*')[0];
                $tx=new \ForumRewrite\Write\SignedPostTransaction($repo);$head=$tx->git(['rev-parse','HEAD']);
                [$code,$out]=self::run($script,array_merge($base,$write));assertSame(2,$code,$out);assertStringContains('Unresolved operation',$out);
                [$code,$out]=self::run($script,array_merge($base,['thread','--resume',$operation]));assertSame(0,$code,$out);
                assertSame($head,$tx->git(['rev-parse','HEAD']));
                \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
            } finally {$server->close();}
        }
    }

    public function testBusyRetriesAreBoundedAndExact(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
        try {
            $counter=$root.'/attempts';$hashes=$root.'/hashes';
            $hook='if ($_SERVER["REQUEST_URI"] === "/api/create_signed_post") { $n=is_file('.var_export($counter,true).')?(int)file_get_contents('.var_export($counter,true).'):0; file_put_contents('.var_export($counter,true).', $n+1); file_put_contents('.var_export($hashes,true).',hash("sha256",file_get_contents("php://input"))."\n",FILE_APPEND); if($n<3) {http_response_code(503);header("Retry-After: 0");echo json_encode(["status"=>"busy","error"=>"busy","retry_after"=>0]);return;} }';
            $router=$server->directory.'/public/router.php';file_put_contents($router,str_replace('<?php','<?php '.$hook,file_get_contents($router)));
            $script=$root.'/frm.py';file_put_contents($script,file_get_contents($server->url.'/frm.py'));
            file_put_contents($root.'/body.txt',"Busy body.\n");
            $base=['--site',$server->url,'--state-dir',$root.'/client'];
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Busy','--body-file',$root.'/body.txt']));
            assertSame(2,$code,$out);assertSame('3',file_get_contents($counter));
            $operation=glob($root.'/client/*/operation-*')[0];
            [$code,$out]=self::run($script,array_merge($base,['thread','--resume',$operation]));assertSame(0,$code,$out);
            assertSame(1,count(array_unique(file($hashes,FILE_IGNORE_NEW_LINES))));
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
    }

    public function testCharacterCorrectionPreservesOriginalPositionsAndKey(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server(['FORUM_UNICODE_AUTHORED_TEXT'=>'true']);
        try {
            $script=$root.'/frm.py';file_put_contents($script,file_get_contents($server->url.'/frm.py'));
            $body="  Cafe\u{0301}\r\nx→\n";file_put_contents($root.'/body.txt',$body);
            $base=['--site',$server->url,'--state-dir',$root.'/client'];
            $write=['thread','--subject','Correction','--body-file',$root.'/body.txt'];
            [$code,$out]=self::run($script,array_merge($base,$write)); assertSame(2,$code,$out);assertStringContains('U+2192 at body index 10',$out);
            $operation=glob($root.'/client/*/operation-*')[0];assertSame($body,file_get_contents($operation.'/original-body.txt'));
            file_put_contents($root.'/body.txt',"Café accepted.\n");
            [$code,$out]=self::run($script,array_merge($base,$write));assertSame(0,$code,$out);
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
    }

    public function testInterruptedLegacyRegistrationResolvesSameIdentity(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
        try {
            $marker=$root.'/lose-registration';file_put_contents($marker,'yes');
            $router=$server->directory.'/public/router.php';
            $hook='ob_start(fn($body) => preg_replace("/^POST \\/api\\/create_signed_post[^\\n]*\\n/m", "", $body)); if($_SERVER["REQUEST_URI"] === "/api/create_identity") ob_start(function($body) { if(is_file('.var_export($marker,true).')) {unlink('.var_export($marker,true).');http_response_code(502);return "lost";}return $body;});';
            file_put_contents($router,str_replace('<?php','<?php '.$hook,file_get_contents($router)));
            $script=$root.'/frm.py';file_put_contents($script,file_get_contents($server->url.'/frm.py'));
            file_put_contents($root.'/body.txt',"Registered then resumed.\n");
            $base=['--site',$server->url,'--state-dir',$root.'/client'];
            $before=count(glob($repo.'/records/identity/*'));
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Recovery','--body-file',$root.'/body.txt'])); assertSame(2,$code,$out);
            $operation=glob($root.'/client/*/operation-*')[0]; assertStringContains('registration-sending',file_get_contents($operation.'/operation.txt'));
            [$code,$out]=self::run($script,array_merge($base,['thread','--resume',$operation]));assertSame(0,$code,$out);
            assertSame($before+1,count(glob($repo.'/records/identity/*')));
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
    }

    public function testLegacyMissingTokenAndUnknownReadDoNotReprepare(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
        try {
            $marker=$root.'/block';file_put_contents($marker,'yes');
            $router=$server->directory.'/public/router.php';
            $hook='ob_start(fn($body) => preg_replace("/^POST \\/api\\/create_signed_post[^\\n]*\\n/m", "", $body)); if($_SERVER["REQUEST_URI"] === "/api/create_prepared_post" && is_file('.var_export($marker,true).')) {http_response_code(503);echo "busy";return;}';
            file_put_contents($router,str_replace('<?php','<?php '.$hook,file_get_contents($router)));
            $script=$root.'/frm.py';file_put_contents($script,file_get_contents($server->url.'/frm.py'));
            file_put_contents($root.'/body.txt',"Uncertain.\n");
            $base=['--site',$server->url,'--state-dir',$root.'/client','--retries','0'];
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Recovery','--body-file',$root.'/body.txt']));assertSame(2,$code,$out);
            $operation=glob($root.'/client/*/operation-*')[0];
            $head=(new \ForumRewrite\Write\SignedPostTransaction($repo))->git(['rev-parse','HEAD']);
            [$code,$out]=self::run($script,array_merge($base,['thread','--resume',$operation]));assertSame(2,$code,$out);assertStringContains('outcome is unresolved',$out);
            // Simulate saved ready state after interruption before its first finalization, with an expired token.
            unlink($marker);$meta=$operation.'/operation.txt';file_put_contents($meta,str_replace('State: sending','State: ready',file_get_contents($meta)));file_put_contents($operation.'/prepare_token.txt','expired');
            [$code,$out]=self::run($script,array_merge($base,['thread','--resume',$operation]));assertSame(2,$code,$out);
            assertSame($head,(new \ForumRewrite\Write\SignedPostTransaction($repo))->git(['rev-parse','HEAD']));
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
    }

    public function testMemberDenialAndSigningFailurePreserveWork(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server(['FORUM_APPROVED_MEMBERS_ONLY'=>'true']);
        try {
            $script=$root.'/frm.py';file_put_contents($script,file_get_contents($server->url.'/frm.py'));
            $body="Retained draft.\n";file_put_contents($root.'/body.txt',$body);
            $base=['--site',$server->url,'--state-dir',$root.'/client'];
            $before=(new \ForumRewrite\Write\SignedPostTransaction($repo))->git(['rev-parse','HEAD']);
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Denied','--body-file',$root.'/body.txt']));
            assertSame(2,$code,$out);assertStringContains('HTTP 403',$out);
            $operation=glob($root.'/client/*/operation-*')[0];assertSame($body,file_get_contents($operation.'/original-body.txt'));
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','No key','--body-file',$root.'/body.txt','--signing-key','NO-SUCH-KEY','--gpg-home',dirname($operation).'/keyring']));
            assertSame(2,$code,$out);assertStringContains('GPG failed',$out);
            assertSame($before,(new \ForumRewrite\Write\SignedPostTransaction($repo))->git(['rev-parse','HEAD']));
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
    }

    public function testSubmissionRedirectNeverSwitchesProtocolAndRefreshPendingReplays(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
        try {
            $marker=$root.'/redirect';file_put_contents($marker,'yes');
            $router=$server->directory.'/public/router.php';
            $hook='if($_SERVER["REQUEST_URI"] === "/api/create_signed_post") { if(is_file('.var_export($marker,true).')) {http_response_code(302);header("Location: /api/create_thread");return;} ob_start(function($body) {$p=json_decode($body,true); if(is_array($p)&&($p["status"]??"")==="ok") {$p["refresh_pending"]=true;return json_encode($p);}return $body;});}';
            file_put_contents($router,str_replace('<?php','<?php '.$hook,file_get_contents($router)));
            $script=$root.'/frm.py';file_put_contents($script,file_get_contents($server->url.'/frm.py'));file_put_contents($root.'/body.txt',"Redirect draft.\n");
            $tx=new \ForumRewrite\Write\SignedPostTransaction($repo);$before=$tx->git(['rev-parse','HEAD']);
            $base=['--site',$server->url,'--state-dir',$root.'/client'];
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Redirect','--body-file',$root.'/body.txt']));assertSame(2,$code,$out);
            assertSame($before,$tx->git(['rev-parse','HEAD']));
            $operation=glob($root.'/client/*/operation-*')[0];unlink($marker);
            [$code,$out]=self::run($script,array_merge($base,['thread','--resume',$operation]));assertSame(0,$code,$out);assertStringContains('Refresh: pending',$out);
            $head=$tx->git(['rev-parse','HEAD']);
            [$code,$out]=self::run($script,array_merge($base,['thread','--resume',$operation]));assertSame(0,$code,$out);assertSame($head,$tx->git(['rev-parse','HEAD']));
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
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
