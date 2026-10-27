<?php
require_once __DIR__ . '/../autoload.php';
use ForumRewrite\Support\{BoundedProcess, OperationBudget, OperationLimitException};
final class BoundedProcessTest
{
    public function testOutputExitAndExpiredBudget(): void
    {
        $result = BoundedProcess::run([PHP_BINARY, '-r', 'fwrite(STDOUT,"out");fwrite(STDERR,"err");exit(3);'], new OperationBudget(2), 100, 'fixture');
        assertSame(3, $result['exit_code']); assertSame('out', $result['stdout']); assertSame('err', $result['stderr']);
        $budget = new OperationBudget(0.001); usleep(2000);
        try { BoundedProcess::run(['true'], $budget, 100, 'expired'); throw new LogicException('Expected expiry'); }
        catch (OperationLimitException $e) { assertStringContains('expired', $e->getMessage()); }
    }
    public function testNoisyAndStalledDescendantsAreBounded(): void
    {
        $pidFile = tempnam(sys_get_temp_dir(), 'bounded-descendant-');
        foreach (['while(true)echo str_repeat("x",8192);', 'if(pcntl_fork()===0){file_put_contents(' . var_export($pidFile, true) . ',getmypid());sleep(30);}else{sleep(30);}'] as $code) {
            $start = microtime(true);
            try { BoundedProcess::run([PHP_BINARY, '-r', $code], new OperationBudget(0.15), 1024, 'fixture'); throw new LogicException('Expected limit'); }
            catch (OperationLimitException $e) { assertStringContains('fixture', $e->getMessage()); }
            assertTrue(microtime(true) - $start < 2);
        }
        $pid = trim(file_get_contents($pidFile));
        assertTrue(ctype_digit($pid));
        $stat = @file_get_contents('/proc/' . $pid . '/stat');
        assertTrue($stat === false || preg_match('/\) [ZX] /', $stat) === 1, 'Descendant must not remain running');
        unlink($pidFile);
    }
}
