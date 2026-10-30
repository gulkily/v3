<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/WriteApiSmokeTest.php';
use ForumRewrite\Write\{SignedPostIdentity,SignedPostBundleValidator};
use ForumRewrite\Security\OpenPgpKeyInspector;
final class SignedPostIdentityTest
{
    public static string $home;
    public static function helper(string $method, ...$args): mixed { return (new ReflectionMethod(WriteApiSmokeTest::class,$method))->invoke(new WriteApiSmokeTest(),...$args); }
    public static function bundle(): array
    {
        $key=self::helper('createSigningKey','bundle-agent'); self::$home=$key['home'];
        $f=(new OpenPgpKeyInspector())->inspect($key['public_key'])['fingerprint'];
        $time=gmdate('Y-m-d\TH:i:s\Z'); $idtime=gmdate('YmdHis');
        $post='Post-ID: thread-'.$idtime.'-'.bin2hex(random_bytes(16))."\nCreated-At: {$time}\nBoard-Tags: general\nAuthor-Identity-ID: openpgp:".strtolower($f)."\nSubject: Test\n\nHello\n";
        $bootstrap='Post-ID: bootstrap-'.$idtime.'-'.bin2hex(random_bytes(16))."\nCreated-At: {$time}\nBoard-Tags: identity internal\nAuthor-Identity-ID: openpgp:".strtolower($f)."\nSubject: account bootstrap\n\nAutomatic account bootstrap anchor.\n";
        return ['canonical_record'=>$post,'detached_signature'=>self::helper('signCanonicalRecord',$key['home'],$post),'public_key'=>$key['public_key'],'bootstrap_record'=>$bootstrap,'bootstrap_signature'=>self::helper('signCanonicalRecord',$key['home'],$bootstrap)];
    }
    public function testResolutionAndKnownKeyReuse(): void
    {
        $root=sys_get_temp_dir().'/bundle-resolve-'.bin2hex(random_bytes(6)); mkdir($root);
        $input=self::bundle(); $resolver=new SignedPostIdentity($root,new SignedPostBundleValidator());
        $extra=$input; $extra['public_key'].="-----BEGIN PGP PRIVATE KEY BLOCK-----\nprivate\n-----END PGP PRIVATE KEY BLOCK-----\n";
        try { $resolver->resolve($extra); throw new LogicException('Expected extra material refusal'); } catch(RuntimeException $e) { assertStringContains('extra material',$e->getMessage()); }
        $first=$resolver->resolve($input); assertSame(false,$first['known']); assertSame(4,count($first['files']));
        foreach($first['files'] as $path=>$bytes) { @mkdir(dirname($root.'/'.$path),0777,true); file_put_contents($root.'/'.$path,$bytes); }
        $known=$resolver->resolve($input); assertSame(true,$known['known']); assertSame([],$known['files']);
        unset($input['public_key'],$input['bootstrap_record'],$input['bootstrap_signature']);
        assertSame($first['identity']->bootstrapByPost,$resolver->resolve($input)['identity']->bootstrapByPost);
        $input['canonical_record']=str_replace('Hello','Tampered',$input['canonical_record']);
        try { $resolver->resolve($input); throw new LogicException('Expected bad signature'); } catch(RuntimeException $e) { assertStringContains('signature',$e->getMessage()); }
    }
}
