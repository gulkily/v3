<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\ReadModel\TagGrouping;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\Support\BoundedProcess;
use ForumRewrite\Support\OperationBudget;

final class TagsIndexMemoryTest
{
    public function testStreamingPreviewsMatchExistingGrouping(): void
    {
        $pdo = (new BoardListingQueryTest())->fixture();
        $pdo->exec("UPDATE threads SET board_tags_json = '[\"PHP\",\"php\",\"01\",\"1\"]', thread_labels_json = '[\"Php\",\"other\"]' WHERE root_post_id > 'thread-003'");
        $expected = TagGrouping::limitPreview(TagGrouping::byTag(ThreadRepository::fetchThreads($pdo)), 5);
        foreach ($expected as &$group) {
            unset($group['threads']);
            foreach ($group['preview_threads'] as &$thread) {
                unset($thread['root_post_body']);
            }
            unset($thread);
        }
        unset($group);
        $actual = TagGrouping::previewsFromUniqueThreads(ThreadRepository::iterateThreads($pdo), 5);
        // Compare values independently of associative key insertion order.
        assertTrue($expected == $actual);
        assertSame(array_column($expected, 'tag'), array_column($actual, 'tag'));
        assertSame([], TagGrouping::previewsFromUniqueThreads([], 5));
    }

    public function testTagsRouteStaysBoundedAsQuoteCorpusGrows(): void
    {
        $peaks = [];
        foreach ([1000, 40000] as $count) {
            $database = tempnam(sys_get_temp_dir(), 'tags-memory-');
            try {
                $result = BoundedProcess::run(
                    [PHP_BINARY, '-d', 'memory_limit=32M', __DIR__ . '/Support/tags_index_memory.php', $database, (string) $count],
                    new OperationBudget(60), 8192, 'tags memory regression',
                );
                assertSame(0, $result['exit_code'], $result['stderr'] . $result['stdout']);
                assertTrue(preg_match('/Peak bytes: (\d+)/', $result['stdout'], $match) === 1, $result['stdout']);
                $peaks[] = (int) $match[1];
            } finally {
                foreach (glob($database . '*') ?: [] as $path) { @unlink($path); }
            }
        }
        assertTrue($peaks[1] <= $peaks[0] + 4 * 1024 * 1024, 'Fixed tag/preview counts must not retain the growing corpus.');
    }
}
