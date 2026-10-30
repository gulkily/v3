<?php
require_once __DIR__.'/SignedPostIdentityTest.php';
require_once __DIR__.'/Support/FrontendTestServer.php';
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Canonical\CanonicalRecordRepository;
final class SingleRequestHttpTest
{
    public static function server(array $environment=[]): array
    {
        [$repo,$db,$artifacts]=SignedPostIdentityTest::helper('createTempEnvironment');
        $root=sys_get_temp_dir().'/signed-http-'.bin2hex(random_bytes(6)); mkdir($root); mkdir($root.'/sessions',0700); $db=$root.'/read.sqlite3';
        foreach(['public','templates','docs','prompts'] as $name) symlink(dirname(__DIR__).'/'.$name,$root.'/'.$name);
        file_put_contents($root.'/unused.php','<?php return [];');
        (new ReadModelBuilder($repo,$db,new CanonicalRecordRepository($repo)))->rebuild();
        $router='<?php ini_set("session.save_path",'.var_export($root.'/sessions',true).'); require '.var_export(dirname(__DIR__).'/autoload.php',true).'; (new ForumRewrite\\Host\\FrontController('.var_export($root,true).','.var_export($repo,true).','.var_export($db,true).','.var_export($root.'/static',true).'))->handle($_SERVER["REQUEST_METHOD"],$_SERVER["REQUEST_URI"],$_COOKIE);';
        $server=new FrontendTestServer($router,null,$environment+['FORUM_SECRETS_PATH'=>$root.'/unused.php','FORUM_STATIC_HTML_ROOT'=>$root.'/static','FORUM_PUBLIC_ARTIFACT_ROOT'=>$artifacts,'FORUM_APPROVED_MEMBERS_ONLY'=>'false','FORUM_AUTOMATIC_GUEST_KEYPAIR_ENABLED'=>'false']);
        return [$server,$repo,$db,$root];
    }
    public static function request(FrontendTestServer $server, array $input=[],string $method='POST'): array
    {
        $context=stream_context_create(['http'=>['method'=>$method,'ignore_errors'=>true,'header'=>"Content-Type: application/x-www-form-urlencoded\r\n",'content'=>http_build_query($input),'timeout'=>40]]);
        $body=file_get_contents($server->url.'/api/create_signed_post',false,$context);
        return [$http_response_header,json_decode($body,true,512,JSON_THROW_ON_ERROR)];
    }
    public function testHttpSubmissionAndRecovery(): void
    {
        [$server]=self::server();
        try {
            $input=SignedPostIdentityTest::bundle(); [$headers,$result]=self::request($server,$input);
            assertStringContains('200',$headers[0]); assertSame('ok',$result['status']);
            [$headers,$retry]=self::request($server,$input); assertSame(true,$retry['replayed']); assertSame($result['commit_sha'],$retry['commit_sha']);
            [$headers,$error]=self::request($server,[],'GET'); assertStringContains('405',$headers[0]); assertSame('method_not_allowed',$error['code']);
            [$headers,$error]=self::request($server,['canonical_record'=>['bad']]); assertStringContains('400',$headers[0]);
        } finally { $server->close(); }
    }
    public function testBusyAndStartupFailureAreJson(): void
    {
        [$server,$repo,$db]=self::server(['FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS'=>'0']);
        try {
            $lock=fopen(dirname($db).'/forum-rewrite.lock','c+'); flock($lock,LOCK_EX);
            try { [$headers,$error]=self::request($server,SignedPostIdentityTest::bundle()); }
            finally { flock($lock,LOCK_UN); fclose($lock); }
            assertStringContains('503',$headers[0]); assertSame('busy',$error['status']); assertSame(10,$error['retry_after']);
            assertSame(true,in_array('Retry-After: 10',$headers,true));
            unlink($db); mkdir($db);
            [$headers,$error]=self::request($server);
            assertStringContains('503',$headers[0]); assertSame('temporarily_unavailable',$error['code']);
            assertStringNotContains($db,$error['error']);
        } finally { $server->close(); }
    }
    public function testMembersOnlyDenialIsJson(): void
    {
        [$server]=self::server(['FORUM_APPROVED_MEMBERS_ONLY'=>'true']);
        try { [$headers,$error]=self::request($server); assertStringContains('403',$headers[0]); assertSame('access_denied',$error['code']); }
        finally { $server->close(); }
    }
}
