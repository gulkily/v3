<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Support\PrivateConfigSchema;

final class RailspikeFeatureFlagTest
{
    public function testDefaultSiteValueAndEnvironmentOverride(): void
    {
        $key=FeatureFlagRegistry::RAILSPIKE_ENABLED; $old=getenv($key);
        try {
            putenv($key);
            assertSame(false,(new FeatureFlagEvaluator())->isEnabled($key));
            assertTrue((new FeatureFlagEvaluator(siteValues:[$key=>true]))->isEnabled($key));
            putenv($key.'=false');
            $state=(new FeatureFlagEvaluator(siteValues:[$key=>true]))->evaluate($key);
            assertSame(false,$state->effectiveValue); assertTrue($state->isLocked());
            assertTrue(in_array($key,PrivateConfigSchema::environmentKeys(),true));
            assertSame(false,array_key_exists($key,PrivateConfigSchema::templateDefaults()));
        } finally {putenv($old===false?$key:$key.'='.$old);}
    }

    public function testStaticPolicyAndEligibleMounts(): void
    {
        $key=FeatureFlagRegistry::RAILSPIKE_ENABLED; $old=getenv($key); putenv($key);
        try {
            foreach ([false,true] as $enabled) {
                $flags=new FeatureFlagEvaluator(siteValues:[$key=>$enabled]);
                $renderer=new \ForumRewrite\View\TemplateRenderer(dirname(__DIR__).'/templates',featureFlags:$flags);
                foreach ([[],['board_tags_json'=>'["railspike"]']] as $tags) {
                    $html=$renderer->renderFragment('partials/railspike_workflow.php',[
                        'post'=>array_merge(['post_id'=>'one','thread_id'=>'one'],$tags),
                        'railspikeOperators'=>[str_repeat('a',40)], 'railspikeThread'=>[],
                    ]);
                    assertSame($enabled && $tags!==[],str_contains($html,'data-railspike-post'));
                }
                $availability=\ForumRewrite\Support\FeatureFlags\RailspikeAvailability::class;
                assertSame(false,$availability::matchesHtml('<script src="/assets/railspike_workflow.js"></script>',$flags));
                assertTrue($availability::matchesHtml('<html data-railspike-enabled="'.($enabled?'1':'0').'">',$flags));
                assertSame(false,$availability::matchesHtml('<html data-railspike-enabled="'.($enabled?'0':'1').'">',$flags));
            }
        } finally {putenv($old===false?$key:$key.'='.$old);}
    }

    public function testDisabledRoutesRefuseBeforeWorkflowStorageAndEnabledRequiresOperator(): void
    {
        $w=new ImportTestWorkspace();$root=$w->repository('source',true);$w->git($root);
        $code='require $argv[1]."/autoload.php"; http_response_code(200); $_SERVER["CONTENT_TYPE"]="application/json"; $_SERVER["HTTP_X_REQUESTED_WITH"]="ForumRailspike"; (new ForumRewrite\\Application($argv[1],$argv[2],$argv[3]))->handle($argv[4],"/api/railspike_workflow",json_decode($argv[5],true)); fwrite(STDOUT,"\nHTTP_STATUS=".http_response_code());';
        foreach ([['GET',['thread_id'=>'root-001']],['GET',['activity'=>'1']],['POST',['operation'=>'prepare','action'=>'start']],['POST',['signed_text'=>'old preparation','signature'=>'test']]] as [$method,$query]) {
            foreach ([false,true,false] as $enabled) {
                [$status,$body]=$w->command(['env','FORUM_APPROVED_MEMBERS_ONLY=false','FORUM_RAILSPIKE_ENABLED='.($enabled?'true':'false'), 'FORUM_TASK_QUEUE_DATABASE_PATH='.$w->root.'/queue.sqlite3',PHP_BINARY,'-r',$code,dirname(__DIR__),$root,$w->root.'/index.sqlite3',$method,json_encode($query)]);
                assertSame(0,$status,$body);assertStringContains('HTTP_STATUS=403',$body);
                assertStringContains($enabled?'Approved operator authentication required':'railspike_disabled',$body);
                assertSame(false,is_file($w->root.'/queue.sqlite3'));
            }
        }
    }
}
