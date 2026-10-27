<?php
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';
require_once __DIR__ . '/Support/ImportHttpServer.php';
final class InvocationSupervisorTest
{
    public function testIncompleteProcessStatsDoNotProduceWarningsOrInventIdentity(): void
    {
        $parse = new ReflectionMethod(\ForumRewrite\Support\InvocationSupervisor::class, 'parseStat');
        $fields = ['S', '42', '43', ...array_fill(0, 16, '0'), '123456'];
        $valid = "123 (worker ) name\nwith parentheses) " . implode(' ', $fields) . "\n";
        set_error_handler(static function (int $severity, string $message): never {
            throw new ErrorException($message, 0, $severity);
        });
        try {
            foreach (['', '123 (worker', '123 (worker) ', '123 (worker) S',
                '123 (worker) S 42 43', substr($valid, 0, strrpos($valid, ' ')),
                str_replace('123456', 'invalid', $valid), str_replace('S 42', 'S invalid', $valid),
                str_replace('42 43', '42 invalid', $valid)] as $raw) {
                assertSame(null, $parse->invoke(null, $raw));
            }
            assertSame(['state' => 'S', 'parent' => 42, 'group' => 43, 'birth' => '123456'], $parse->invoke(null, $valid));
            $live = $parse->invoke(null, file_get_contents('/proc/self/stat'));
            assertSame(posix_getppid(), $live['parent']);
            assertSame(posix_getpgrp(), $live['group']);
        } finally {
            restore_error_handler();
        }
    }

    public function testUnexpectedWorkerExitRetainsFailureExitContract(): void
    {
        $w = new ImportTestWorkspace();
        $w->put($w->root . '/exit.php', '<?php require ' . var_export(__DIR__ . '/../autoload.php', true) . ';exit(ForumRewrite\\Support\\InvocationSupervisor::run(static fn()=>77,1));');
        [$code, $output] = $w->command([PHP_BINARY, $w->root . '/exit.php']);
        assertSame(1, $code); assertStringContains('status 77', $output);
    }

    public function testNestedProcessAndLockAreReleasedOnDeadline(): void
    {
        $w = new ImportTestWorkspace();
        $child = $w->root . '/child.php'; $pidFile = $w->root . '/pid'; $lockFile = $w->root . '/lock';
        $w->put($child, '<?php file_put_contents(' . var_export($pidFile, true) . ',getmypid());sleep(30);');
        $script = '<?php require ' . var_export(__DIR__ . '/../autoload.php', true) . ';'
            . 'exit(ForumRewrite\\Support\\InvocationSupervisor::run(static function(){'
            . '$lock=fopen(' . var_export($lockFile, true) . ',"c+");flock($lock,LOCK_EX);'
            . 'ForumRewrite\\Support\\InvocationSupervisor::stage("nested child");'
            . 'ForumRewrite\\Support\\BoundedProcess::run([PHP_BINARY,' . var_export($child, true) . '],new ForumRewrite\\Support\\OperationBudget(30),100,"nested");return 0;},0.3));';
        $w->put($w->root . '/supervise.php', $script);
        $start = microtime(true); [$code, $output] = $w->command([PHP_BINARY, $w->root . '/supervise.php']);
        assertSame(1, $code); assertStringContains('nested child', $output); assertTrue(microtime(true)-$start < 3);
        $pid = trim(file_get_contents($pidFile));
        $stat = @file_get_contents('/proc/' . $pid . '/stat');
        assertTrue($stat === false || preg_match('/\) [ZX] /', $stat) === 1, 'Nested child still running');
        $lock = fopen($lockFile, 'c+'); assertTrue(flock($lock, LOCK_EX | LOCK_NB)); fclose($lock);
    }
    public function testCliDeadlineCoversBlockedDownloadAndRejectsInvalidOverrides(): void
    {
        $w = new ImportTestWorkspace(); $target = $w->repository('target', true); $w->git($target);
        $w->put($w->root . '/router.php', '<?php sleep(30);');
        $server = new ImportHttpServer($w->root . '/router.php', $w->root . '/http.log');
        $command = [__DIR__ . '/../v3', 'import-instance', $server->url, '--repository-root=' . $target, '--database-path=' . $w->root . '/cache/db.sqlite3'];
        $start = microtime(true);
        [$code, $output] = $w->command([...$command, '--time-limit=0.2']);
        assertSame(1, $code); assertStringContains('time limit exceeded', $output); assertStringContains('Retry', $output);
        assertTrue(microtime(true)-$start < 3);
        assertSame('1', trim($w->command(['git', '-C', $target, 'rev-list', '--count', 'HEAD'])[1]));
        foreach (['0', '-1', 'INF', 'NaN', '1e999', ''] as $bad) {
            [$code, $output] = $w->command([...$command, '--time-limit=' . $bad]); assertSame(1, $code);
        }
    }
}
