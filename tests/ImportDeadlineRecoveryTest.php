<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';
use ForumRewrite\Import\{ContentImportRunner, ImportedContentPublisher};
final class ImportDeadlineRecoveryTest
{
    public function testHardDeadlineRecoveryAcrossWriteAndPublicationBoundaries(): void
    {
        foreach (['prepared', 'copy_prepared', 'copied', 'staged', 'committed', 'before_build', 'built', 'promoted', 'offline_snapshot'] as $phase) {
            $w = new ImportTestWorkspace(); $source = $w->repository('source', true); $target = $w->repository('target');
            $w->put($target . '/records/instance/public.txt', file_get_contents($source . '/records/instance/public.txt')); $w->git($target);
            $label = $w->signedLabel($source, ['.asc', '.sig']);
            $bytes = file_get_contents($source . '/' . $label . '.asc');
            $database = $w->root . '/cache/db.sqlite3'; $static = $w->root . '/static';
            $context = ['static_root' => $static];
            $script = '<?php require ' . var_export(__DIR__ . '/../autoload.php', true) . ';'
                . '$target=' . var_export($target, true) . ';$source=' . var_export($source, true) . ';$db=' . var_export($database, true) . ';$static=' . var_export($static, true) . ';'
                . '$phase=' . var_export($phase, true) . ';'
                . 'exit(ForumRewrite\\Support\\InvocationSupervisor::run(static function()use($target,$source,$db,$static,$phase){'
                . '$checkpoint=static function($at)use($phase){if($at===$phase){ForumRewrite\\Support\\InvocationSupervisor::stage("checkpoint:".$at);sleep(30);}};'
                . '$publisher=new ForumRewrite\\Import\\ImportedContentPublisher(' . var_export(dirname(__DIR__), true) . ',$target,$db,$static,checkpoint:$checkpoint);'
                . '(new ForumRewrite\\Import\\ContentImportRunner($target,$db,checkpoint:$checkpoint,publicationContext:["static_root"=>$static]))->run($source,$publisher->publishWhileLocked(...));return 0;},1.0,$target));';
            $w->put($w->root . '/run.php', $script);
            $start = microtime(true); [$code, $output] = $w->command([PHP_BINARY, $w->root . '/run.php']);
            assertSame(1, $code, $phase . ': ' . $output);
            assertStringNotContains('Warning:', $output);
            assertStringNotContains('Notice:', $output);
            assertStringContains('checkpoint:' . $phase, $output);
            assertStringContains('--resume', $output);
            assertTrue(microtime(true) - $start < 4);
            if (in_array($phase, ['prepared', 'copy_prepared'], true)) assertTrue(!is_file($target . '/records/posts/root-001.txt'));
            // A killed copy may leave arbitrary partial scratch bytes, never a partial canonical record.
            if ($phase === 'copy_prepared') $w->put($target . '/.git/instance-import/copy.tmp', 'partial copy');
            $w->command(['rm', '-rf', '--', $source]);
            $publisher = new ImportedContentPublisher(dirname(__DIR__), $target, $database, $static);
            $result = (new ContentImportRunner($target, $database, publicationContext: $context))->run(null, $publisher->publishWhileLocked(...));
            assertSame('complete', $result['status'], $phase);
            assertSame($bytes, file_get_contents($target . '/' . $label . '.asc'));
            assertSame('2', trim($w->command(['git', '-C', $target, 'rev-list', '--count', 'HEAD'])[1]));
            assertSame('', trim($w->command(['git', '-C', $target, 'status', '--porcelain'])[1]));
            assertTrue(!file_exists($target . '/.git/instance-import/pending.json'));
            assertSame('ok', (new PDO('sqlite:' . $database))->query('PRAGMA quick_check')->fetchColumn());
            assertSame('ok', (new PDO('sqlite:' . $static . '/offline/snapshot.sqlite3'))->query('PRAGMA quick_check')->fetchColumn());
        }
    }
    public function testInterruptedGitHookResumesAndForeignLockIsPreserved(): void
    {
        $w = new ImportTestWorkspace(); $source = $w->repository('source'); $target = $w->repository('target'); $w->git($target);
        $w->put($source . '/records/posts/root.txt', $w->post('root'));
        $w->put($target . '/.git/hooks/pre-commit', "#!/bin/sh\nsleep 30\n"); chmod($target . '/.git/hooks/pre-commit', 0700);
        $database = $w->root . '/cache/db.sqlite3';
        $script = '<?php require ' . var_export(__DIR__ . '/../autoload.php', true) . ';exit(ForumRewrite\\Support\\InvocationSupervisor::run(static function(){'
            . '(new ForumRewrite\\Import\\ContentImportRunner(' . var_export($target, true) . ',' . var_export($database, true) . '))->run(' . var_export($source, true) . ',static fn()=>null);return 0;},0.5,' . var_export($target, true) . '));';
        $w->put($w->root . '/run.php', $script); assertSame(1, $w->command([PHP_BINARY, $w->root . '/run.php'])[0]);
        unlink($target . '/.git/hooks/pre-commit');
        $w->put($target . '/.git/index.lock', 'foreign lock');
        try { (new ContentImportRunner($target, $database))->run(null, static fn()=>null); throw new LogicException('Expected lock failure'); }
        catch (RuntimeException $e) { assertStringContains('index.lock', $e->getMessage()); }
        assertSame('foreign lock', file_get_contents($target . '/.git/index.lock'));
        unlink($target . '/.git/index.lock');
        assertSame('complete', (new ContentImportRunner($target, $database))->run(null, static fn()=>null)['status']);
    }
}
