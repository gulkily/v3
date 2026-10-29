<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

final class FastmodMetaTest
{
    public function testCliStatusIsReadOnlyAndRetiredActionsExplainRecovery(): void
    {
        $dir=sys_get_temp_dir().'/meta-cli-'.bin2hex(random_bytes(5));mkdir($dir);
        try {
            $path=$dir.'/scores.sqlite3';
            file_put_contents($dir.'/secrets.php','<?php return '.var_export(['FAST_SCORING_DATABASE_PATH'=>$path],true).';');
            $run=static function(string $args) use ($dir): array {
                $cmd='FORUM_SECRETS_PATH='.escapeshellarg($dir.'/secrets.php').' php '.escapeshellarg(dirname(__DIR__).'/scripts/fast_score.php').' '.$args.' 2>&1';
                exec($cmd,$output,$exit);return [$exit,implode("\n",$output)];
            };
            [$exit,$text]=$run('meta-status');assertSame(0,$exit);assertStringContains('Score state: missing',$text);
            [$exit,$text]=$run('meta-status --json --repository-root='.escapeshellarg($dir.'/selected'));
            assertSame(0,$exit);assertSame('missing',json_decode($text,true,512,JSON_THROW_ON_ERROR)['score_state']);
            assertSame(false,is_file($path));
            // Legacy corrections remain bytes-for-bytes unchanged by every retired command.
            $pdo=new PDO('sqlite:'.$path);
            $pdo->exec('CREATE TABLE fastmod_meta(post_id TEXT,suppressed INTEGER)');
            $pdo->exec("INSERT INTO fastmod_meta VALUES('old',1)");$pdo=null;
            $before=hash_file('sha256',$path);
            foreach(['meta-remove --post-id=old','meta-rollback','meta-retry'] as $command) {
                [$exit,$text]=$run($command);assertSame(1,$exit);
                assertStringContains('is retired',$text);assertStringContains('Disable Automatic Fastmod tagging',$text);
                assertSame($before,hash_file('sha256',$path));
            }
        } finally {foreach(glob($dir.'/*') as $file)unlink($file);rmdir($dir);}
    }
}
