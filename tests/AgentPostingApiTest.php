<?php
require_once __DIR__ . '/SingleRequestHttpTest.php';
final class AgentPostingApiTest
{
    public static function request($server, string $path, string $method = 'GET', array $input = []): array
    {
        $context = stream_context_create(['http'=>['method'=>$method,'ignore_errors'=>true,'header'=>"Content-Type: application/x-www-form-urlencoded\r\n",'content'=>http_build_query($input),'timeout'=>40]]);
        $body = file_get_contents($server->url.$path, false, $context);
        return [$http_response_header, $body];
    }
    public function testRulesErrorsAndLegacyCompatibility(): void
    {
        [$server] = SingleRequestHttpTest::server(['FORUM_UNICODE_AUTHORED_TEXT'=>'true']);
        try {
            [$h,$body] = self::request($server, '/api/text_rules');
            assertSame('unicode', json_decode($body,true)['mode']);
            [$h,$body] = self::request($server, '/api/prepare_thread','POST',['body'=>"  a\r\né→",'subject'=>'test']);
            $error=json_decode($body,true); assertSame(6,$error['chars'][0]['index']); assertSame('body',$error['field']);
            [$h,$body] = self::request($server, '/api/no_such_route?x=1');
            assertStringContains('404',$h[0]); assertSame('error',json_decode($body,true)['status']);
            [$h,$body] = self::request($server, '/api/create_thread');
            assertStringContains('405',$h[0]); assertSame("method not allowed\n",$body);
        } finally { $server->close(); }
    }
    public function testMemberDiscoveryAndJsonDenial(): void
    {
        [$server] = SingleRequestHttpTest::server(['FORUM_APPROVED_MEMBERS_ONLY'=>'true']);
        try {
            [$h,$body] = self::request($server, '/api/text_rules'); assertStringContains('200',$h[0]);
            [$h,$body] = self::request($server, '/api/prepare_thread','POST');
            assertStringContains('403',$h[0]); assertSame('access_denied',json_decode($body,true)['code']);
        } finally { $server->close(); }
    }
    public function testHostFailuresAndCaughtBusy(): void
    {
        [$server,$repo,$db] = SingleRequestHttpTest::server(['FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS'=>'0']);
        try {
            $lock=fopen(dirname($db).'/forum-rewrite.lock','c+'); flock($lock,LOCK_EX);
            try {
                foreach (['/api/prepare_thread','/api/create_thread'] as $path) {
                    [$h,$body]=self::request($server,$path,'POST',['subject'=>'test','body'=>'hello']);
                    assertStringContains('503',$h[0]); assertSame('busy',json_decode($body,true)['status']);
                    assertTrue(in_array('Retry-After: 10',$h,true));
                }
            } finally { flock($lock,LOCK_UN); fclose($lock); }
            unlink($db); mkdir($db);
            foreach (['/api','/api/','/api/get_post?post_id=missing'] as $path) {
                [$h,$body]=self::request($server,$path); assertStringContains('503',$h[0]);
                assertSame('error',json_decode($body,true)['status']); assertStringNotContains($db,$body);
            }
            [$h,$body]=self::request($server,'/'); assertStringContains('<html',$body);
        } finally { $server->close(); }
    }
}
