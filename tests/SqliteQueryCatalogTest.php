<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Tools\SqliteQueryCatalog;

final class SqliteQueryCatalogTest
{
    public function testCatalogLoadsAndSortsMetadataFiles(): void
    {
        $directory = sys_get_temp_dir() . '/sqlite-query-catalog-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);
        file_put_contents($directory . '/second.sql', "-- id: second\n-- label: Second\n-- description: Second query\n-- category: stats\n-- order: 20\n\nSELECT 2");
        file_put_contents($directory . '/first.sql', "-- id: first\n-- label: First\n-- description: First query\n-- category: board\n-- order: 10\n\nSELECT 1;");

        try {
            $catalog = SqliteQueryCatalog::load($directory);
            assertSame(['first', 'second'], array_column($catalog, 'id'));
            assertSame('first.sql', $catalog[0]['filename']);
            assertSame('SELECT 1;', $catalog[0]['sql']);
        } finally {
            @unlink($directory . '/first.sql');
            @unlink($directory . '/second.sql');
            @rmdir($directory);
        }
    }

    public function testCatalogRejectsWritesAndMissingMetadata(): void
    {
        $directory = sys_get_temp_dir() . '/sqlite-query-catalog-' . bin2hex(random_bytes(6));
        mkdir($directory, 0777, true);
        file_put_contents($directory . '/write.sql', "-- id: write\n-- label: Write\n-- description: Write query\n-- category: test\n-- order: 1\n\nUPDATE posts SET subject = 'bad'");

        try {
            $thrown = false;
            try {
                SqliteQueryCatalog::load($directory);
            } catch (RuntimeException $exception) {
                $thrown = true;
                assertStringContains('read-only SELECT', $exception->getMessage());
            }
            assertTrue($thrown);
        } finally {
            @unlink($directory . '/write.sql');
            @rmdir($directory);
        }
    }

    public function testRepositoryCatalogContainsTheExistingPresets(): void
    {
        $catalog = SqliteQueryCatalog::load(__DIR__ . '/../queries/sqlite');

        assertSame(
            ['board-liked-newest', 'recent-posts', 'threads-by-reply-count', 'approved-profiles', 'recent-activity'],
            array_column($catalog, 'id')
        );
        assertSame('Board: Liked + Newest', $catalog[0]['label']);
        assertStringContains('JOIN posts ON posts.post_id = threads.root_post_id', $catalog[0]['sql']);
        assertStringContains("json_each.value = 'pinned'", $catalog[0]['sql']);
    }

    public function testBrowserAssetUsesGeneratedCatalogEntries(): void
    {
        $browserSource = file_get_contents(__DIR__ . '/../public/assets/sqlite_viewer.js');
        assertTrue($browserSource !== false);
        assertStringContains('"id": "board-liked-newest"', $browserSource);
        assertStringContains('"id": "recent-activity"', $browserSource);
        assertStringNotContains('var presetQueries = [\n      {', $browserSource);
    }
}
