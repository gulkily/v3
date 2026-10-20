<?php

declare(strict_types=1);
require_once __DIR__.'/../autoload.php';
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry as Registry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator as Evaluator;

final class FastmodTaggingFlagTest
{
    public function testSharedPrecedenceAndDependency(): void
    {
        $key=Registry::FASTMOD_AUTOMATIC_TAGGING_ENABLED;
        $names=[$key,'FASTMOD_META_TAGGING_ENABLED',Registry::FAST_SCORING_ENABLED];$old=[];
        foreach($names as $n){$old[$n]=getenv($n);putenv($n);}
        try {
            $base=[Registry::FAST_SCORING_ENABLED=>true];
            assertSame(false,(new Evaluator(privateConfigValues:$base))->isEnabled($key));
            foreach([false,true] as $legacy) {
                $private=$base+['FASTMOD_META_TAGGING_ENABLED'=>$legacy];
                $state=(new Evaluator(privateConfigValues:$private))->evaluate($key);
                assertSame($legacy,$state->effectiveValue);assertSame(true,$state->canChangeFromSite());
                assertSame('legacy-private-config',$state->source);
                foreach([false,true] as $site) {
                    $state=(new Evaluator(siteValues:[$key=>$site],privateConfigValues:$private))->evaluate($key);
                    assertSame($site,$state->effectiveValue);assertSame('site',$state->source);
                }
            }
            putenv('FASTMOD_META_TAGGING_ENABLED=true');
            assertSame('legacy-environment',(new Evaluator(privateConfigValues:$base))->evaluate($key)->source);
            assertSame(false,(new Evaluator(siteValues:[$key=>false],privateConfigValues:$base))->isEnabled($key));
            putenv($key.'=false');
            $state=(new Evaluator(siteValues:[$key=>true],privateConfigValues:$base))->evaluate($key);
            assertSame(false,$state->effectiveValue);assertSame(true,$state->isLocked());assertSame('environment',$state->source);
            putenv($key.'=true');
            assertSame(true,(new Evaluator(siteValues:[$key=>false],privateConfigValues:$base))->isEnabled($key));
            putenv($key);putenv('FASTMOD_META_TAGGING_ENABLED');
            $state=(new Evaluator(siteValues:[$key=>true]))->evaluate($key);
            assertSame(false,$state->effectiveValue);assertSame(true,$state->isBlockedByDependency());
            $state=(new Evaluator(siteError:'invalid record',privateConfigValues:$base+['FASTMOD_META_TAGGING_ENABLED'=>true]))->evaluate($key);
            assertSame(false,$state->effectiveValue);assertSame('invalid-site-value',$state->source);
            $keys=array_map(static fn($d)=>$d->key,(new Registry())->all());
            assertSame(1,count(array_filter($keys,static fn($k)=>$k===Registry::FASTMOD_AUTOMATIC_TAGGING_ENABLED)));
            assertSame(false,in_array('FASTMOD_META_TAGGING_ENABLED',$keys,true));
        } finally {foreach($old as $n=>$value)putenv($value===false?$n:$n.'='.$value);}
    }
}
