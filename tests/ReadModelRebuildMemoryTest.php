<?php

declare(strict_types=1);

use ForumRewrite\Support\BoundedProcess;
use ForumRewrite\Support\OperationBudget;

final class ReadModelRebuildMemoryTest
{
    /** qdb's rebuild ran out of 128 MB because the post corpus was held twice. */
    public function testFullRebuildHoldsTheQuoteCorpusOnce(): void
    {
        $count = 8000;
        // Before the fix this corpus peaked near 58 MB; it now needs about 32 MB.
        $result = BoundedProcess::run(
            [PHP_BINARY, '-d', 'memory_limit=44M', __DIR__ . '/Support/read_model_rebuild_memory.php', (string) $count],
            new OperationBudget(120), 8192, 'read model rebuild memory regression',
        );
        assertSame(0, $result['exit_code'], $result['stderr'] . $result['stdout']);
        // The fixture's own posts and threads come on top of the generated ones.
        assertTrue(preg_match('/Posts: (\d+)\nThreads: (\d+)/', $result['stdout'], $match) === 1, $result['stdout']);
        assertTrue((int) $match[1] > $count && (int) $match[2] > $count, $result['stdout']);
    }
}
