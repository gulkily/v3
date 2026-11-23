<?php
require_once __DIR__.'/AgentPostingApiTest.php';
final class QuillClientTest
{
    /**
     * quill.py keeps its keyring at <state-dir>/<64-hex site hash>/keyring. Under a long test root the
     * gpg-agent socket paths (the longest is S.gpg-agent.browser) pass the ~107-byte Unix socket limit and the agent cannot start unless
     * /run/user/<uid> exists (it does on desktop Linux, not in containers). With a 64-character site
     * hash and /keyring, the state dir itself must stay within 13 characters (/tmp/f + 6 hex).
     */
    private static function stateDir(string $root, string $name): string
    {
        $dir=sys_get_temp_dir().'/f'.substr(hash('sha256',$root.'/'.$name),0,6);
        static $registered=[];
        if (!isset($registered[$dir])) {
            $registered[$dir]=true;
            register_shutdown_function(static function () use ($dir): void {
                foreach (glob($dir.'/*/keyring') ?: [] as $home) { \ForumRewrite\Security\GpgHomeCleaner::dispose($home); }
                @exec('rm -rf '.escapeshellarg($dir));
            });
        }
        return $dir;
    }
    /** Every Quill version and the interpreter that runs it; the HTTP tests run each one (docs/reference/multi_language_scripts.md). */
    private const CLIENTS = ['quill.py' => 'python3', 'quill.pl' => 'perl', 'quill.php' => 'php'];
    /** The version the current check runs; quill.py outside self::each(). */
    private static string $client = 'quill.py';

    /** Runs a check once per Quill version, naming the version in any failure. */
    private static function each(callable $check): void
    {
        foreach (array_keys(self::CLIENTS) as $client) {
            self::$client=$client;
            try { $check($client); }
            catch (Throwable $error) { throw new RuntimeException($client.': '.$error->getMessage(),0,$error); }
            finally { self::$client='quill.py'; }
        }
    }

    /** Downloads the current version from the test server, as an agent would. */
    private static function download(object $server, string $root): string
    {
        $script=$root.'/'.self::$client; file_put_contents($script,file_get_contents($server->url.'/'.self::$client));
        return $script;
    }

    /** Runs a script with the interpreter for its extension unless one is given. */
    public static function run(string $script, array $args, ?string $interpreter=null): array
    {
        $interpreter??=self::CLIENTS['quill.'.pathinfo($script,PATHINFO_EXTENSION)] ?? 'python3';
        $cmd=$interpreter.' '.escapeshellarg($script).' '.implode(' ',array_map('escapeshellarg',$args));
        exec($cmd.' 2>&1',$output,$code); return [$code,implode("\n",$output)];
    }
    public function testLostResponseResumeWithoutDuplicateForBothProtocols(): void
    {
        self::each(fn () => $this->checkLostResponseResumeWithoutDuplicateForBothProtocols());
    }

    private function checkLostResponseResumeWithoutDuplicateForBothProtocols(): void
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
                $script=self::download($server,$root);
                file_put_contents($root.'/body.txt',"Recovery body.\n");
                $base=['--site',$server->url,'--state-dir',self::stateDir($root,'client'),'--retries','0'];
                $write=['thread','--subject','Recovery','--body-file',$root.'/body.txt'];
                [$code,$out]=self::run($script,array_merge($base,$write)); assertSame(2,$code,$out); assertStringContains('Resume:',$out);
                $operation=glob(self::stateDir($root,'client').'/*/operation-*')[0];
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
        self::each(fn () => $this->checkBusyRetriesAreBoundedAndExact());
    }

    private function checkBusyRetriesAreBoundedAndExact(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
        try {
            $counter=$root.'/attempts';$hashes=$root.'/hashes';
            $hook='if ($_SERVER["REQUEST_URI"] === "/api/create_signed_post") { $n=is_file('.var_export($counter,true).')?(int)file_get_contents('.var_export($counter,true).'):0; file_put_contents('.var_export($counter,true).', $n+1); file_put_contents('.var_export($hashes,true).',hash("sha256",file_get_contents("php://input"))."\n",FILE_APPEND); if($n<3) {http_response_code(503);header("Retry-After: 0");echo json_encode(["status"=>"busy","error"=>"busy","retry_after"=>0]);return;} }';
            $router=$server->directory.'/public/router.php';file_put_contents($router,str_replace('<?php','<?php '.$hook,file_get_contents($router)));
            $script=self::download($server,$root);
            file_put_contents($root.'/body.txt',"Busy body.\n");
            $base=['--site',$server->url,'--state-dir',self::stateDir($root,'client')];
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Busy','--body-file',$root.'/body.txt']));
            assertSame(2,$code,$out);assertSame('3',file_get_contents($counter));
            $operation=glob(self::stateDir($root,'client').'/*/operation-*')[0];
            [$code,$out]=self::run($script,array_merge($base,['thread','--resume',$operation]));assertSame(0,$code,$out);
            assertSame(1,count(array_unique(file($hashes,FILE_IGNORE_NEW_LINES))));
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
    }

    public function testCharacterCorrectionPreservesOriginalPositionsAndKey(): void
    {
        self::each(fn () => $this->checkCharacterCorrectionPreservesOriginalPositionsAndKey());
    }

    private function checkCharacterCorrectionPreservesOriginalPositionsAndKey(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server(['FORUM_UNICODE_AUTHORED_TEXT'=>'true']);
        try {
            $script=self::download($server,$root);
            // A no-break space is still rejected; arrows were accepted in c90eea84.
            $body="  Cafe\u{0301}\r\nx\u{00A0}\n";file_put_contents($root.'/body.txt',$body);
            $base=['--site',$server->url,'--state-dir',self::stateDir($root,'client')];
            $write=['thread','--subject','Correction','--body-file',$root.'/body.txt'];
            [$code,$out]=self::run($script,array_merge($base,$write)); assertSame(2,$code,$out);assertStringContains('U+00A0 at body index 10',$out);
            $operation=glob(self::stateDir($root,'client').'/*/operation-*')[0];assertSame($body,file_get_contents($operation.'/original-body.txt'));
            file_put_contents($root.'/body.txt',"Café accepted.\n");
            [$code,$out]=self::run($script,array_merge($base,$write));assertSame(0,$code,$out);
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
    }

    public function testStagedPostIsReportedAndReplacedUnderItsId(): void
    {
        self::each(fn () => $this->checkStagedPostIsReportedAndReplacedUnderItsId());
    }

    private function checkStagedPostIsReportedAndReplacedUnderItsId(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server(['FORUM_UNICODE_AUTHORED_TEXT'=>'true','FORUM_EMOJI_AUTHORED_TEXT'=>'false']);
        try {
            $script=self::download($server,$root);
            $base=['--site',$server->url,'--state-dir',self::stateDir($root,'staged')];
            file_put_contents($root.'/first.txt',"Registering this key.\n");
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','First','--body-file',$root.'/first.txt'])); assertSame(0,$code,$out);

            file_put_contents($root.'/body.txt',"Saturday ran long.\n");
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject',"Cypher recap \u{1F3A4}",'--body-file',$root.'/body.txt']));
            assertSame(3,$code,$out);
            assertStringContains('Staged: the server kept this post but has not published it.',$out);
            assertStringContains('U+1F3A4 at subject index 13',$out);
            assertStringContains('View: '.$server->url.'/staged/',$out);
            preg_match('/^Post-ID: (thread-\S+)$/m',$out,$id);
            assertStringContains('thread --replace '.$id[1].' --body-file FIXED_DRAFT --subject SUBJECT',$out);
            assertSame(false,str_contains($out,'Resume:'),'a staged post is not an unresolved write');
            $operation=dirname(glob(self::stateDir($root,'staged').'/*/operation-*/operation.txt')[0]);
            [$code,$out]=self::run($script,array_merge($base,['thread','--resume',dirname(self::findOperation($root,$id[1]))]));
            assertSame(2,$code,$out); assertStringContains('was staged, not lost',$out);

            // Another write is not blocked, and the fix publishes under the staged ID.
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Unrelated','--body-file',$root.'/first.txt'])); assertSame(0,$code,$out);
            sleep(1); // The fix is signed in a later second than the staged post.
            [$code,$out]=self::run($script,array_merge($base,['thread','--replace',$id[1],'--subject','Cypher recap','--body-file',$root.'/body.txt']));
            assertSame(0,$code,$out); assertStringContains('Post-ID: '.$id[1],$out);
            $post=file_get_contents($server->url.'/api/get_post?post_id='.urlencode($id[1]));
            assertStringContains('Post-ID: '.$id[1],$post); assertStringContains('Saturday ran long.',$post);
            [$code,$out]=self::run($script,array_merge($base,['thread','--replace',$id[1],'--subject','Again','--body-file',$root.'/body.txt']));
            assertSame(2,$code,$out); assertStringContains('No staged operation for '.$id[1],$out);
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
    }

    private static function findOperation(string $root, string $postId): string
    {
        foreach (glob(self::stateDir($root,'staged').'/*/operation-*/operation.txt') ?: [] as $file) {
            if (str_contains((string) file_get_contents($file), 'Post-ID: '.$postId."\n")) return $file;
        }
        throw new RuntimeException('No operation for '.$postId);
    }

    public function testInterruptedLegacyRegistrationResolvesSameIdentity(): void
    {
        self::each(fn () => $this->checkInterruptedLegacyRegistrationResolvesSameIdentity());
    }

    private function checkInterruptedLegacyRegistrationResolvesSameIdentity(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
        try {
            $marker=$root.'/lose-registration';file_put_contents($marker,'yes');
            $router=$server->directory.'/public/router.php';
            $hook='ob_start(fn($body) => preg_replace("/^POST \\/api\\/create_signed_post[^\\n]*\\n/m", "", $body)); if($_SERVER["REQUEST_URI"] === "/api/create_identity") ob_start(function($body) { if(is_file('.var_export($marker,true).')) {unlink('.var_export($marker,true).');http_response_code(502);return "lost";}return $body;});';
            file_put_contents($router,str_replace('<?php','<?php '.$hook,file_get_contents($router)));
            $script=self::download($server,$root);
            file_put_contents($root.'/body.txt',"Registered then resumed.\n");
            $base=['--site',$server->url,'--state-dir',self::stateDir($root,'client')];
            $before=count(glob($repo.'/records/identity/*'));
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Recovery','--body-file',$root.'/body.txt'])); assertSame(2,$code,$out);
            $operation=glob(self::stateDir($root,'client').'/*/operation-*')[0]; assertStringContains('registration-sending',file_get_contents($operation.'/operation.txt'));
            [$code,$out]=self::run($script,array_merge($base,['thread','--resume',$operation]));assertSame(0,$code,$out);
            assertSame($before+1,count(glob($repo.'/records/identity/*')));
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
    }

    public function testLegacyMissingTokenAndUnknownReadDoNotReprepare(): void
    {
        self::each(fn () => $this->checkLegacyMissingTokenAndUnknownReadDoNotReprepare());
    }

    private function checkLegacyMissingTokenAndUnknownReadDoNotReprepare(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
        try {
            $marker=$root.'/block';file_put_contents($marker,'yes');
            $router=$server->directory.'/public/router.php';
            $hook='ob_start(fn($body) => preg_replace("/^POST \\/api\\/create_signed_post[^\\n]*\\n/m", "", $body)); if($_SERVER["REQUEST_URI"] === "/api/create_prepared_post" && is_file('.var_export($marker,true).')) {http_response_code(503);echo "busy";return;}';
            file_put_contents($router,str_replace('<?php','<?php '.$hook,file_get_contents($router)));
            $script=self::download($server,$root);
            file_put_contents($root.'/body.txt',"Uncertain.\n");
            $base=['--site',$server->url,'--state-dir',self::stateDir($root,'client'),'--retries','0'];
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Recovery','--body-file',$root.'/body.txt']));assertSame(2,$code,$out);
            $operation=glob(self::stateDir($root,'client').'/*/operation-*')[0];
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
        self::each(fn () => $this->checkMemberDenialAndSigningFailurePreserveWork());
    }

    private function checkMemberDenialAndSigningFailurePreserveWork(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server(['FORUM_APPROVED_MEMBERS_ONLY'=>'true']);
        try {
            $script=self::download($server,$root);
            $body="Retained draft.\n";file_put_contents($root.'/body.txt',$body);
            $base=['--site',$server->url,'--state-dir',self::stateDir($root,'client')];
            $before=(new \ForumRewrite\Write\SignedPostTransaction($repo))->git(['rev-parse','HEAD']);
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Denied','--body-file',$root.'/body.txt']));
            assertSame(2,$code,$out);assertStringContains('HTTP 403',$out);
            assertStringContains('Rejected: the server refused this write',$out);
            assertSame(false,str_contains($out,'Resume:'),$out);assertSame(false,str_contains($out,'Check:'),$out);
            $operation=glob(self::stateDir($root,'client').'/*/operation-*')[0];assertSame($body,file_get_contents($operation.'/original-body.txt'));
            [$code,$out]=self::run($script,array_merge($base,['thread','--resume',$operation]));
            assertSame(2,$code,$out);assertStringContains('This operation was rejected',$out);assertSame(false,str_contains($out,'Resume:'),$out);
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','No key','--body-file',$root.'/body.txt','--signing-key','NO-SUCH-KEY','--gpg-home',dirname($operation).'/keyring']));
            assertSame(2,$code,$out);assertStringContains('GPG failed',$out);
            assertSame($before,(new \ForumRewrite\Write\SignedPostTransaction($repo))->git(['rev-parse','HEAD']));
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
    }

    public function testSubmissionRedirectNeverSwitchesProtocolAndRefreshPendingReplays(): void
    {
        self::each(fn () => $this->checkSubmissionRedirectNeverSwitchesProtocolAndRefreshPendingReplays());
    }

    private function checkSubmissionRedirectNeverSwitchesProtocolAndRefreshPendingReplays(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
        try {
            $marker=$root.'/redirect';file_put_contents($marker,'yes');
            $router=$server->directory.'/public/router.php';
            $hook='if($_SERVER["REQUEST_URI"] === "/api/create_signed_post") { if(is_file('.var_export($marker,true).')) {http_response_code(302);header("Location: /api/create_thread");return;} ob_start(function($body) {$p=json_decode($body,true); if(is_array($p)&&($p["status"]??"")==="ok") {$p["refresh_pending"]=true;return json_encode($p);}return $body;});}';
            file_put_contents($router,str_replace('<?php','<?php '.$hook,file_get_contents($router)));
            $script=self::download($server,$root);file_put_contents($root.'/body.txt',"Redirect draft.\n");
            $tx=new \ForumRewrite\Write\SignedPostTransaction($repo);$before=$tx->git(['rev-parse','HEAD']);
            $base=['--site',$server->url,'--state-dir',self::stateDir($root,'client')];
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Redirect','--body-file',$root.'/body.txt']));assertSame(2,$code,$out);
            assertSame($before,$tx->git(['rev-parse','HEAD']));
            $operation=glob(self::stateDir($root,'client').'/*/operation-*')[0];unlink($marker);
            [$code,$out]=self::run($script,array_merge($base,['thread','--resume',$operation]));assertSame(0,$code,$out);assertStringContains('Refresh: pending',$out);
            $head=$tx->git(['rev-parse','HEAD']);
            [$code,$out]=self::run($script,array_merge($base,['thread','--resume',$operation]));assertSame(0,$code,$out);assertSame($head,$tx->git(['rev-parse','HEAD']));
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
    }

    public function testFirstWriteReuseAndLegacyFallback(): void
    {
        self::each(fn () => $this->checkFirstWriteReuseAndLegacyFallback());
    }

    private function checkFirstWriteReuseAndLegacyFallback(): void
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
                $script=self::download($server,$root);
                file_put_contents($root.'/body.txt', "Café — 5 µg, “quoted”, Привет\n");
                $base=['--site',$server->url,'--state-dir',self::stateDir($root,'client')];
                [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Example','--body-file',$root.'/body.txt']));
                assertSame(0,$code,$out);
                preg_match('/Post-ID: (\S+)/',$out,$match); $id=$match[1];
                $state=glob(self::stateDir($root,'client').'/*')[0]; $key=file_get_contents($state.'/identity.txt');
                $operation=glob($state.'/operation-*/operation.txt')[0];
                assertStringContains('Protocol: '.($legacy?'prepared':'signed'),file_get_contents($operation));
                foreach ([[],['--parent',$id]] as $parent) {
                    [$code,$out]=self::run($script,array_merge($base,['reply','--thread',$id,'--body-file',$root.'/body.txt'],$parent));
                    assertSame(0,$code,$out);
                }
                preg_match('/Fingerprint: (\S+)/',$key,$fingerprint);
                [$code,$out]=self::run($script,['--site',$server->url,'--state-dir',self::stateDir($root,'selected'),'reply','--thread',$id,'--body-file',$root.'/body.txt','--signing-key',$fingerprint[1],'--gpg-home',$state.'/keyring']);
                assertSame(0,$code,$out);
                assertSame($key,file_get_contents($state.'/identity.txt'));
                assertSame($identitiesBefore+1,count(glob($repo.'/records/identity/*')));
                [$code,$out]=self::run($script,array_merge($base,['read',$id])); assertSame(0,$code,$out);
                assertStringContains('Café — 5 µg, “quoted”, Привет',$out);
                \ForumRewrite\Security\GpgHomeCleaner::dispose($state.'/keyring');
            } finally {$server->close();}
        }
    }

    public function testFailedWritePostsOneRedactedTroubleReport(): void
    {
        self::each(fn () => $this->checkFailedWritePostsOneRedactedTroubleReport());
    }

    private function checkFailedWritePostsOneRedactedTroubleReport(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
        try {
            $context=stream_context_create(['http'=>['method'=>'POST','ignore_errors'=>true,'header'=>"Content-Type: application/x-www-form-urlencoded\r\n",'content'=>http_build_query(['subject'=>'Agent trouble reports','body'=>"Reports land here.\n"]),'timeout'=>40]]);
            $created=file_get_contents($server->url.'/api/create_thread',false,$context);
            preg_match('/^thread_id=(\S+)$/m',(string)$created,$match); $thread=$match[1]??''; assertTrue($thread!=='',(string)$created);
            $script=self::download($server,$root);
            file_put_contents($root.'/body.txt',"Secret draft text.\n");
            $state=self::stateDir($root,'report');
            $write=['--site',$server->url,'--state-dir',$state,'reply','--thread','thread-missing','--body-file',$root.'/body.txt','--report-thread',$thread];
            [$code,$out]=self::run($script,$write); assertSame(2,$code,$out);
            assertTrue(str_contains($out,'Trouble-report: sent '.$server->url.'/threads/'.$thread),$out);
            assertTrue(strpos($out,'Error: ')<strpos($out,'Trouble-report:'),$out);
            assertTrue(preg_match('/\(post (\S+)\)/',$out,$posted)===1,$out);
            $report=file_get_contents($server->url.'/api/get_post?post_id='.rawurlencode($posted[1]));
            assertStringContains('Command: reply',$report); assertStringContains('Outcome: ',$report);
            foreach (['Secret draft text',$root,$state,'BEGIN PGP'] as $secret) assertStringNotContains($secret,$report);
            $operation=glob($state.'/*/operation-*')[0];
            [$code,$out]=self::run($script,['--site',$server->url,'--state-dir',$state,'reply','--resume',$operation,'--report-thread',$thread]);
            assertStringNotContains('Trouble-report: sent',$out);
            \ForumRewrite\Security\GpgHomeCleaner::dispose(dirname($operation).'/keyring');
        } finally {$server->close();}
    }

    public function testImageAttachmentNeedsApprovalThenPublishesTheSignedBytes(): void
    {
        self::each(fn () => $this->checkImageAttachmentNeedsApprovalThenPublishesTheSignedBytes());
    }

    private function checkImageAttachmentNeedsApprovalThenPublishesTheSignedBytes(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server(['FORUM_IMAGE_CONTENT_ENABLED'=>'true']);
        try {
            $script=self::download($server,$root);
            file_put_contents($root.'/pixel.png',ImageContentTest::png());
            file_put_contents($root.'/body.txt',"Screenshot attached.\n");
            $base=['--site',$server->url,'--state-dir',self::stateDir($root,'image'),'--retries','0'];
            $image=['--no-report','--image',$root.'/pixel.png','--image-description','One white pixel (test)'];
            // Unapproved key: the server refuses the image, so nothing is published and the draft is kept.
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Picture','--body-file',$root.'/body.txt'],$image));
            assertSame(2,$code,$out); assertStringContains('Rejected:',$out);
            preg_match('/Identity-ID: (openpgp:[0-9a-f]{40})/',$out,$m); $identity=$m[1];
            $seed=\ForumRewrite\Canonical\CanonicalPathResolver::approvalSeed(substr($identity,8));
            @mkdir(dirname($repo.'/'.$seed),0777,true);
            file_put_contents($repo.'/'.$seed,"Approved-Identity-ID: {$identity}\nSeed-Reason: Quill image test\n\n");
            $tx=new \ForumRewrite\Write\SignedPostTransaction($repo);$tx->git(['add',$seed]);$tx->git(['commit','-qm','Approve Quill image author']);
            (new \ForumRewrite\ReadModel\ReadModelBuilder($repo,$db,new \ForumRewrite\Canonical\CanonicalRecordRepository($repo)))->rebuild();
            // python3 -S hides site-packages, so this is the standard-library path: the prepared PNG goes as is.
            // quill.pl and quill.php always take this path.
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Picture','--body-file',$root.'/body.txt'],$image),self::$client==='quill.py'?'python3 -S':null);
            assertSame(0,$code,$out); assertStringContains('Status: accepted',$out); assertStringContains('metadata removed',$out);
            preg_match('/Post-ID: (\S+)/',$out,$m); $post=file_get_contents($server->url.'/api/get_post?post_id='.$m[1]);
            $hash=hash('sha256',ImageContentTest::png());
            assertStringContains("Screenshot attached.\n\nImage-v1: {$hash} png ".strlen(ImageContentTest::png()).' 1 1 One%20white%20pixel%20%28test%29',$post);
            assertSame(ImageContentTest::png(),file_get_contents($server->url.'/images/'.$hash.'.png'));
            // With Pillow, a large image is prepared like the browser composer before upload.
            exec('python3 -c '.escapeshellarg('import sys; from PIL import Image; Image.new("RGB",(2500,1200),"white").save(sys.argv[1])').' '.escapeshellarg($root.'/wide.png').' 2>&1',$o,$pillow);
            if ($pillow===0 && self::$client==='quill.py') {
                $wide=array_merge($base,['reply','--thread',$m[1],'--body-file',$root.'/body.txt','--no-report','--image',$root.'/wide.png','--image-description','Wide']);
                [$code,$out]=self::run($script,$wide);
                assertSame(0,$code,$out); assertStringContains('Image: 2048 x 983 PNG',$out);
                preg_match('/Post-ID: (\S+)/',$out,$r); preg_match('/Image-v1: ([0-9a-f]{64}) png \d+ 2048 983 Wide/',file_get_contents($server->url.'/api/get_post?post_id='.$r[1]),$ref);
                assertSame([2048,983],array_slice(getimagesizefromstring(file_get_contents($server->url.'/images/'.$ref[1].'.png')),0,2));
            }
            // A body that already carries a reference is refused before anything is sent.
            file_put_contents($root.'/twice.txt',"Two.\n\nImage-v1: {$hash} png 1 1 1\n");
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Twice','--body-file',$root.'/twice.txt'],$image));
            assertSame(2,$code,$out); assertStringContains('--image adds it',$out);
            foreach (glob(self::stateDir($root,'image').'/*/keyring') as $home) \ForumRewrite\Security\GpgHomeCleaner::dispose($home);
        } finally {$server->close();}
    }
    /**
     * An approved approver key and two pending keys that share one username, each with one thread.
     * Returns [server, repo, db, root, script, approverBase, [pendingBase, pendingBase], [identity => post ID]].
     */
    private static function approvalFixture(array $environment=[]): array
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server($environment);
        $script=self::download($server,$root);
        file_put_contents($root.'/body.txt',"Hello from a test key.\n");
        $bases=[]; $posts=[];
        foreach (['approver'=>'approver-agent','one'=>'shared-agent','two'=>'shared-agent'] as $label=>$name) {
            $base=['--site',$server->url,'--state-dir',self::stateDir($root,$label),'--retries','0'];
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Hi '.$label,'--body-file',$root.'/body.txt','--name',$name,'--no-report']));
            assertSame(0,$code,$out);
            preg_match('/Identity-ID: (openpgp:[0-9a-f]{40})/',$out,$i); preg_match('/Post-ID: (\S+)/',$out,$p);
            $bases[$label]=$base; $posts[$i[1]]=$p[1];
            if ($label==='approver') {
                $seed=\ForumRewrite\Canonical\CanonicalPathResolver::approvalSeed(substr($i[1],8));
                @mkdir(dirname($repo.'/'.$seed),0777,true);
                file_put_contents($repo.'/'.$seed,"Approved-Identity-ID: {$i[1]}\nSeed-Reason: Quill approval test\n\n");
                $tx=new \ForumRewrite\Write\SignedPostTransaction($repo);$tx->git(['add',$seed]);$tx->git(['commit','-qm','Approve Quill approver']);
                (new \ForumRewrite\ReadModel\ReadModelBuilder($repo,$db,new \ForumRewrite\Canonical\CanonicalRecordRepository($repo)))->rebuild();
            }
        }
        return [$server,$repo,$db,$root,$script,$bases['approver'],[$bases['one'],$bases['two']],$posts];
    }

    private static function disposeKeyrings(string $root): void
    {
        foreach (['approver','one','two'] as $label) {
            foreach (glob(self::stateDir($root,$label).'/*/keyring') as $home) \ForumRewrite\Security\GpgHomeCleaner::dispose($home);
        }
    }

    public function testPendingKeysNeedsAnApprovedSessionAndMatchesThePendingPage(): void
    {
        [$server,$repo,$db,$root,$script,$approver,$pending,$posts]=self::approvalFixture();
        try {
            [$h,$denied]=AgentPostingApiTest::request($server,'/api/pending_keys');
            assertStringContains('403',$h[0]); assertStringContains('Sign in with an approved key',$denied);
            // Sign in through quill's own helper, then read the API and the page with that session.
            $probe=$root.'/probe.py';
            file_put_contents($probe,"import argparse, importlib.util, sys\n"
                ."spec=importlib.util.spec_from_file_location('quill',sys.argv[1]); q=importlib.util.module_from_spec(spec); spec.loader.exec_module(q)\n"
                ."args=argparse.Namespace(state_dir=sys.argv[3], resume=None, signing_key=None, gpg_home=None)\n"
                ."key=q.saved_key(args,sys.argv[2]); s=q.sign_in(sys.argv[2],key)\n"
                ."for route in ('/api/pending_keys','/users/pending/'):\n"
                ."    status,text,_=q.request(sys.argv[2],route,cookie=s['cookie']); print('=== %s %d' % (route,status)); print(text)\n");
            [$code,$out]=self::run($probe,[$script,$server->url,self::stateDir($root,'approver')]);
            assertSame(0,$code,$out);
            [$api,$page]=explode('=== /users/pending/ ',explode('=== /api/pending_keys ',$out,2)[1],2);
            assertTrue(str_starts_with($api,'200'),$out); assertTrue(str_starts_with($page,'200'),$out);
            assertStringContains('Pending-Count: 2',$api);
            $approverId=array_key_first($posts);
            foreach ($posts as $identity=>$post) {
                $slug=str_replace(':','-',$identity);
                $listed=str_contains($api,'Identity-ID: '.$identity."\n");
                assertSame($identity!==$approverId,$listed,$api);
                assertSame($identity!==$approverId,str_contains($page,$slug),'The API and /users/pending/ must list the same keys');
                if ($listed) { assertStringContains('Profile-Slug: '.$slug."\nIdentity-ID: ".$identity."\nUsername: shared-agent\n",$api); assertStringContains('Latest-Post-ID: '.$post,$api); }
            }
        } finally {self::disposeKeyrings($root); $server->close();}
    }

    public function testApproveByPostPicksTheRightKeyAmongSharedNames(): void
    {
        self::each(fn () => $this->checkApproveByPostPicksTheRightKeyAmongSharedNames());
    }

    private function checkApproveByPostPicksTheRightKeyAmongSharedNames(): void
    {
        [$server,$repo,$db,$root,$script,$approver,$pending,$posts]=self::approvalFixture();
        try {
            [$approverId,$oneId,$twoId]=array_keys($posts);
            $oneSlug=str_replace(':','-',$oneId); $twoSlug=str_replace(':','-',$twoId);
            $profile=fn(string $slug): string => (string) file_get_contents($server->url.'/api/get_profile?profile_slug='.$slug);
            // A pending key can neither list nor approve.
            [$code,$out]=self::run($script,array_merge($pending[1],['approve',$posts[$oneId]]));
            assertSame(2,$code,$out); assertStringContains('is not approved here',$out);
            [$code,$out]=self::run($script,array_merge($pending[1],['pending']));
            assertSame(2,$code,$out); assertStringContains('is not approved here',$out);
            // The approver lists both shared-name keys.
            [$code,$out]=self::run($script,array_merge($approver,['pending']));
            assertSame(0,$code,$out); assertStringContains('Pending-Count: 2',$out); assertStringContains('Identity-ID: '.$oneId,$out);
            // Approving by post ID approves that post's key and leaves the other with the same username pending.
            [$code,$out]=self::run($script,array_merge($approver,['approve',$posts[$oneId]]));
            assertSame(0,$code,$out); assertStringContains('Status: approved',$out); assertStringContains('Post: '.$posts[$oneId].' by shared-agent ('.$oneId.')',$out);
            assertStringContains('Approved: yes',$profile($oneSlug)); assertStringContains('Approved: no',$profile($twoSlug));
            // Rerunning is safe.
            [$code,$out]=self::run($script,array_merge($approver,['approve',$posts[$oneId]]));
            assertSame(0,$code,$out); assertStringContains('Status: already approved',$out);
            // Self-approval, unknown targets and unsigned posts are refused with a plain message.
            [$code,$out]=self::run($script,array_merge($pending[1],['approve',$twoSlug]));
            assertSame(2,$code,$out); assertStringContains('cannot approve itself',$out);
            [$code,$out]=self::run($script,array_merge($approver,['approve','openpgp-'.str_repeat('0',40)]));
            assertSame(2,$code,$out); assertStringContains('No key openpgp:',$out);
            [$code,$out]=self::run($script,array_merge($approver,['approve','reply-20990101000000-nosuchpost']));
            assertSame(2,$code,$out); assertStringContains('No post or key called',$out);
            [$h,$reply]=AgentPostingApiTest::request($server,'/api/create_reply','POST',['thread_id'=>$posts[$twoId],'parent_id'=>$posts[$twoId],'body'=>'Unsigned.','author_identity_id'=>$twoId]);
            assertTrue(preg_match('/^post_id=(\S+)$/m',$reply,$unsigned)===1,$reply);
            [$code,$out]=self::run($script,array_merge($approver,['approve',$unsigned[1]]));
            assertSame(2,$code,$out); assertStringContains('is not signed',$out);
            assertStringContains('Approved: no',$profile($twoSlug));
            // Approving by slug works too, and the list is then empty.
            [$code,$out]=self::run($script,array_merge($approver,['approve',$twoSlug]));
            assertSame(0,$code,$out); assertStringContains('Status: approved',$out);
            [$code,$out]=self::run($script,array_merge($approver,['pending']));
            assertSame(0,$code,$out); assertStringContains('Pending-Count: 0',$out);
        } finally {self::disposeKeyrings($root); $server->close();}
    }

    public function testCheckinSignsInListsRepliesAndCaughtUpClearsThem(): void
    {
        self::each(fn () => $this->checkCheckinSignsInListsRepliesAndCaughtUpClearsThem());
    }

    private function checkCheckinSignsInListsRepliesAndCaughtUpClearsThem(): void
    {
        [$server,$repo,$db,$root,$script,$approver,$pending,$posts]=self::approvalFixture(['FORUM_INBOX_ENABLED'=>'true']);
        try {
            [$approverId,$oneId]=array_keys($posts);
            // No key: the public check-in, and the server says how to see more. Checking in creates no key.
            $nobody=['--site',$server->url,'--state-dir',self::stateDir($root,'nobody'),'--retries','0'];
            [$code,$out]=self::run($script,array_merge($nobody,['checkin']));
            assertSame(0,$code,$out); assertStringContains("Viewer: anonymous\nSign-in: replies to you need a signed-in approved key.",$out);
            assertStringContains('Newest threads: 5',$out); assertTrue(glob(self::stateDir($root,'nobody').'/*/identity.txt')===[],'checkin must not create a key');
            // A pending key signs in and is told how to get approved.
            [$code,$out]=self::run($script,array_merge($pending[0],['checkin']));
            assertSame(0,$code,$out); assertStringContains('(' . $oneId . ', not approved)',$out); assertStringContains('Approval: replies to you need an approved key.',$out);
            [$code,$out]=self::run($script,array_merge($pending[0],['caught-up','x']));
            assertSame(2,$code,$out); assertStringContains('is not approved here, so it has no Unread to mark',$out);

            // A reply to the approver's thread shows up in the approver's check-in.
            file_put_contents($root.'/reply.txt',"A reply for the approver.\n");
            [$code,$out]=self::run($script,array_merge($pending[0],['reply','--thread',$posts[$approverId],'--body-file',$root.'/reply.txt','--no-report']));
            assertSame(0,$code,$out); preg_match('/Post-ID: (\S+)/',$out,$reply);
            [$code,$out]=self::run($script,array_merge($approver,['checkin']));
            assertSame(0,$code,$out);
            assertStringContains("Viewer: approver-agent (approved)\n",$out);
            assertStringContains("Replies to you: 1\n- ".$reply[1].' by shared-agent in "Hi approver": A reply for the approver.',$out);
            assertStringContains('caught-up --section replies '.$reply[1].' (or POST /api/inbox/catch_up',$out);
            [$code,$json]=self::run($script,array_merge($approver,['checkin','--json']));
            assertSame(0,$code,$json); $data=json_decode($json,true);
            assertSame('approved',$data['viewer']['status']??null,$json); assertSame($reply[1],$data['sections'][0]['items'][0]['id']??null,$json);

            // caught-up sends the real JSON body and header; the next check-in skips the reply.
            [$code,$out]=self::run($script,array_merge($approver,['caught-up','--section','replies',$reply[1]]));
            assertSame(0,$code,$out); assertStringContains('Caught up: 1 post in replies; 0 still unread.',$out);
            [$code,$out]=self::run($script,array_merge($approver,['checkin']));
            assertSame(0,$code,$out); assertStringContains("Replies to you: 0\n",$out); assertStringNotContains($reply[1],$out);

            // A failed sign-in still prints the public check-in and says why.
            $identity=glob(self::stateDir($root,'approver').'/*/identity.txt')[0];
            $saved=(string) file_get_contents($identity);
            file_put_contents($identity,preg_replace('/^Fingerprint: .*$/m','Fingerprint: '.str_repeat('0',40),$saved));
            [$code,$out]=self::run($script,array_merge($approver,['checkin']));
            assertSame(0,$code,$out); assertStringContains('Sign-in: ',$out); assertStringContains('Showing the public check-in.',$out); assertStringContains('Viewer: anonymous',$out);
            file_put_contents($identity,$saved);
        } finally {self::disposeKeyrings($root); $server->close();}
    }

    public function testDownloadedReaderAndPublicDelivery(): void
    {
        self::each(fn () => $this->checkDownloadedReaderAndPublicDelivery());
    }

    private function checkDownloadedReaderAndPublicDelivery(): void
    {
        $name=self::$client;
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
        try {
            [$h,$script]=AgentPostingApiTest::request($server,'/'.$name);
            assertStringContains('200',$h[0]); assertTrue(in_array('Content-Type: text/plain; charset=utf-8',$h,true));
            assertSame(file_get_contents(dirname(__DIR__).'/public/'.$name),$script);
            if ($name==='quill.py') {
                [$h,$old]=AgentPostingApiTest::request($server,'/frm.py');
                assertStringContains('200',$h[0]); assertSame($script,$old);
            }
            $path=$root.'/downloaded.'.pathinfo($name,PATHINFO_EXTENSION); file_put_contents($path,$script);
            [$code,$out]=self::run($path,['--site',$server->url,'index']); assertSame(0,$code,$out);
            $input=SignedPostIdentityTest::bundle(); [, $post]=SingleRequestHttpTest::request($server,$input);
            foreach(['read','check'] as $action) {
                [$code,$out]=self::run($path,['--site',$server->url,$action,$post['post_id']]); assertSame(0,$code,$out);
                assertStringContains($post['post_id'],$out);
            }
            [$code,$out]=self::run($path,['--site',$server->url,'check','missing']); assertSame(1,$code,$out);
            assertStringContains('Status: missing',$out);
            [$h,$body]=AgentPostingApiTest::request($server,'/'.$name,'HEAD'); assertSame('',$body);
        } finally {$server->close();}
        [$server]=SingleRequestHttpTest::server(['FORUM_APPROVED_MEMBERS_ONLY'=>'true']);
        try {
            [$h,$body]=AgentPostingApiTest::request($server,'/'.$name); assertStringContains('200',$h[0]);
            [$code,$out]=self::run($path,['--site',$server->url,'check','missing']); assertSame(2,$code,$out); assertStringContains('Status: unknown',$out);
        } finally {$server->close();}
    }

    /** The served PHP client is text, never a script the web server runs. */
    public function testServedPhpClientIsNeverExecuted(): void
    {
        [$server]=SingleRequestHttpTest::server();
        try {
            [$h,$body]=AgentPostingApiTest::request($server,'/quill.php');
            assertTrue(in_array('Content-Type: text/plain; charset=utf-8',$h,true)); assertStringContains("const QUILL_VERSION = '",$body);
        } finally {$server->close();}
        exec('php-cgi -v 2>/dev/null',$o,$cgi);
        if ($cgi===0) {
            exec('php-cgi -q '.escapeshellarg(dirname(__DIR__).'/public/quill.php').' 2>&1',$output,$code);
            assertSame('',trim(implode("\n",$output)),'Outside the CLI, quill.php prints nothing');
        }
    }

    public function testUpdateNoticeComparesVersions(): void
    {
        $public=dirname(__DIR__).'/public';
        foreach (['quill'=>'quill.py','quill-pl'=>'quill.pl','quill-php'=>'quill.php'] as $agent=>$name) {
            $current=\ForumRewrite\Http\ClientDownload::currentVersion($public,$name);
            assertTrue($current!==null && preg_match('/^\d{4}-\d{2}-\d{2}(\.\d+)?$/',$current)===1, 'public/'.$name.' needs a QUILL_VERSION line');
            $notice=\ForumRewrite\Http\ClientDownload::notice($agent.'/2000-01-01',$public);
            assertSame('You are running Quill 2000-01-01; this site serves '.$current.'. Fetch it from /'.$name.'.',$notice);
            foreach ([$agent.'/'.$current,$agent.'/'.$current.'.2',$agent.'/2999-01-01',$agent.'/unknown'] as $same) {
                assertSame(null,\ForumRewrite\Http\ClientDownload::notice($same,$public),$same);
            }
        }
        foreach (['Python-urllib/3.12','curl/8.5.0','quill-rb/2000-01-01',''] as $agent) {
            assertSame(null,\ForumRewrite\Http\ClientDownload::notice($agent,$public),$agent);
        }
        $root=sys_get_temp_dir().'/quill-version-'.bin2hex(random_bytes(4)); mkdir($root);
        try {
            file_put_contents($root.'/quill.py',"#!/usr/bin/env python3\n\"\"\"Quill.\"\"\"\nQUILL_VERSION = \"2026-11-16.2\"\n");
            file_put_contents($root.'/quill.pl',"#!/usr/bin/env perl\nour \$QUILL_VERSION = '2026-11-17';\n");
            file_put_contents($root.'/quill.php',"#!/usr/bin/env php\n<?php\n\ndeclare(strict_types=1);\n\nconst QUILL_VERSION = '2026-11-18.3';\n");
            assertSame('2026-11-16.2',\ForumRewrite\Http\ClientDownload::currentVersion($root));
            assertSame('2026-11-17',\ForumRewrite\Http\ClientDownload::currentVersion($root,'quill.pl'));
            assertSame('2026-11-18.3',\ForumRewrite\Http\ClientDownload::currentVersion($root,'quill.php'));
            assertTrue(\ForumRewrite\Http\ClientDownload::notice('quill/2026-11-16',$root)!==null);
            assertSame(null,\ForumRewrite\Http\ClientDownload::notice('quill/2026-11-16.2',$root));
            // Each version is compared with its own file: a Perl copy as new as quill.pl is current even though quill.py is older.
            assertSame(null,\ForumRewrite\Http\ClientDownload::notice('quill-pl/2026-11-17',$root));
            assertTrue(str_ends_with((string) \ForumRewrite\Http\ClientDownload::notice('quill-php/2026-11-18.2',$root),'Fetch it from /quill.php.'));
            assertSame(null,\ForumRewrite\Http\ClientDownload::notice('quill/2026-11-16',$root.'/missing'));
        } finally { foreach (['py','pl','php'] as $ext) @unlink($root.'/quill.'.$ext); @rmdir($root); }
    }

    public function testOutdatedCopyIsToldOnEveryResponse(): void
    {
        self::each(fn () => $this->checkOutdatedCopyIsToldOnEveryResponse());
    }

    private function checkOutdatedCopyIsToldOnEveryResponse(): void
    {
        $name=self::$client;
        $agent=['quill.py'=>'quill','quill.pl'=>'quill-pl','quill.php'=>'quill-php'][$name];
        $versionLine=['quill.py'=>'/^QUILL_VERSION = "[^"]+"/m','quill.pl'=>'/^our \$QUILL_VERSION = \'[^\']+\'/m','quill.php'=>'/^const QUILL_VERSION = \'[^\']+\'/m'][$name];
        $oldLine=['quill.py'=>'QUILL_VERSION = "2000-01-01"','quill.pl'=>'our $QUILL_VERSION = \'2000-01-01\'','quill.php'=>'const QUILL_VERSION = \'2000-01-01\''][$name];
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server();
        try {
            $get=static function (string $path, string $agent) use ($server): array {
                $context=stream_context_create(['http'=>['ignore_errors'=>true,'header'=>'User-Agent: '.$agent."\r\n",'timeout'=>40]]);
                $body=file_get_contents($server->url.$path,false,$context);
                return [$http_response_header,$body];
            };
            foreach (['/api/','/api/list_index','/'.$name,'/no-such-page'] as $path) {
                [$h]=$get($path,$agent.'/2000-01-01');
                assertTrue((bool) preg_grep('/^X-Quill-Update: You are running Quill 2000-01-01; .*Fetch it from \/'.preg_quote($name,'/').'\.$/i',$h),$path);
                [$h]=$get($path,'curl/8.5.0');
                assertSame([],preg_grep('/^X-Quill-Update:/i',$h),$path);
            }
            [, $script]=$get('/'.$name,'curl/8.5.0');
            $current=\ForumRewrite\Http\ClientDownload::currentVersion(dirname(__DIR__).'/public',$name);
            [$h]=$get('/api/',$agent.'/'.$current); assertSame([],preg_grep('/^X-Quill-Update:/i',$h));
            $path=$root.'/'.($name==='quill.py'?'frm.py':$name); file_put_contents($path,preg_replace($versionLine,$oldLine,$script,1));
            assertStringContains('2000-01-01',(string) file_get_contents($path));
            [$code,$out]=self::run($path,['--site',$server->url,'index']); assertSame(0,$code,$out);
            assertSame(1,substr_count($out,'Update: You are running Quill 2000-01-01; this site serves '.$current.'. Fetch it from /'.$name.'.'),$out);
            file_put_contents($path,$script);
            [$code,$out]=self::run($path,['--site',$server->url,'index']); assertSame(0,$code,$out);
            assertTrue(!str_contains($out,'Update:'),$out);
        } finally {$server->close();}
    }

    /**
     * The three versions share one state directory: a key made by one posts from the others under
     * the same identity, and a write one version saved is resumed by another without a duplicate.
     */
    public function testVersionsShareKeysAndResumeEachOthersOperations(): void
    {
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server(['FORUM_UNICODE_AUTHORED_TEXT'=>'true']);
        try {
            $marker=$root.'/lose-response';
            $router=$server->directory.'/public/router.php';
            $hook='if ($_SERVER["REQUEST_URI"] === "/api/create_signed_post") ob_start(function($body) { if (is_file('.var_export($marker,true).')) { unlink('.var_export($marker,true).'); http_response_code(502); return "response lost"; } return $body; });';
            file_put_contents($router,str_replace('<?php','<?php '.$hook,file_get_contents($router)));
            $scripts=[];
            foreach (array_keys(self::CLIENTS) as $name) { self::$client=$name; $scripts[$name]=self::download($server,$root); }
            self::$client='quill.py';
            file_put_contents($root.'/body.txt',"Shared key, three clients. Café.\n");
            $base=['--site',$server->url,'--state-dir',self::stateDir($root,'shared'),'--retries','0'];
            $identitiesBefore=count(glob($repo.'/records/identity/*'));
            [$code,$out]=self::run($scripts['quill.py'],array_merge($base,['thread','--subject','Shared','--body-file',$root.'/body.txt','--no-report']));
            assertSame(0,$code,$out); preg_match('/Post-ID: (\S+)/',$out,$thread); preg_match('/Identity-ID: (\S+)/',$out,$identity);
            assertStringContains("\nURL: ".$server->url.'/threads/'.$thread[1]."\n",$out."\n");
            foreach (['quill.pl','quill.php'] as $name) {
                [$code,$out]=self::run($scripts[$name],array_merge($base,['reply','--thread',$thread[1],'--body-file',$root.'/body.txt','--no-report']));
                assertSame(0,$code,$name.': '.$out); assertStringContains('Identity-ID: '.$identity[1],$out);
                preg_match('/Post-ID: (\S+)/',$out,$reply);
                assertStringContains("\nURL: ".$server->url.'/threads/'.$thread[1].'#'.$reply[1]."\n",$out."\n");
                $post=(string) file_get_contents($server->url.'/api/get_post?post_id='.rawurlencode($reply[1]));
                assertStringContains('Author-Identity-ID: '.$identity[1],$post); assertStringContains('Shared key, three clients. Café.',$post);
            }
            assertSame($identitiesBefore+1,count(glob($repo.'/records/identity/*')));
            // A lost response in one version, resumed by the next: same bytes, one post.
            $tx=new \ForumRewrite\Write\SignedPostTransaction($repo);
            foreach ([['quill.pl','quill.py'],['quill.py','quill.php'],['quill.php','quill.pl']] as [$writer,$resumer]) {
                file_put_contents($marker,'yes');
                [$code,$out]=self::run($scripts[$writer],array_merge($base,['reply','--thread',$thread[1],'--body-file',$root.'/body.txt','--no-report']));
                assertSame(2,$code,$writer.': '.$out); assertStringContains('Resume: ',$out);
                preg_match('/^Saved-operation: (.+)$/m',$out,$operation);
                $head=$tx->git(['rev-parse','HEAD']);
                [$code,$out]=self::run($scripts[$resumer],array_merge($base,['reply','--resume',$operation[1],'--no-report']));
                assertSame(0,$code,$resumer.' resuming '.$writer.': '.$out); assertSame($head,$tx->git(['rev-parse','HEAD']));
                assertSame(1,preg_match('#^URL: '.preg_quote($server->url,'#').'/threads/'.preg_quote($thread[1],'#').'\#\S+$#m',$out),$resumer.': '.$out);
            }
            foreach (glob(self::stateDir($root,'shared').'/*/keyring') as $home) \ForumRewrite\Security\GpgHomeCleaner::dispose($home);
        } finally {$server->close();}
    }

    /** Without the intl extension, quill.php sends non-ASCII drafts through the prepared route; the server composes NFC. */
    public function testPhpWithoutIntlStillPostsUnicode(): void
    {
        exec('php -n -r '.escapeshellarg('echo class_exists("Normalizer") ? "yes" : "no";'),$o,$code);
        if (($o[0]??'')!=='no') return; // intl is compiled in; nothing to check
        [$server,$repo,$db,$root]=SingleRequestHttpTest::server(['FORUM_UNICODE_AUTHORED_TEXT'=>'true']);
        try {
            self::$client='quill.php'; $script=self::download($server,$root); self::$client='quill.py';
            file_put_contents($root.'/body.txt',"Cafe\u{0301} without intl.\n");
            $base=['--site',$server->url,'--state-dir',self::stateDir($root,'nointl')];
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','Café','--body-file',$root.'/body.txt','--no-report']),'php -n');
            assertSame(0,$code,$out); assertStringContains('intl extension unavailable',$out);
            preg_match('/Post-ID: (\S+)/',$out,$id);
            $post=(string) file_get_contents($server->url.'/api/get_post?post_id='.rawurlencode($id[1]));
            assertStringContains("Caf\u{00E9} without intl.",$post);
            assertStringContains('Protocol: prepared',(string) file_get_contents(glob(self::stateDir($root,'nointl').'/*/operation-*/operation.txt')[0]));
            file_put_contents($root.'/ascii.txt',"Plain ASCII.\n");
            [$code,$out]=self::run($script,array_merge($base,['thread','--subject','ASCII','--body-file',$root.'/ascii.txt','--no-report']),'php -n');
            assertSame(0,$code,$out); assertStringNotContains('intl extension unavailable',$out);
            foreach (glob(self::stateDir($root,'nointl').'/*/keyring') as $home) \ForumRewrite\Security\GpgHomeCleaner::dispose($home);
        } finally {$server->close();}
    }
}
