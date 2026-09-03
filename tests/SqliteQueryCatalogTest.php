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
}
