<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelSchema;

/**
 * dropSchema() derives its table list from ReadModelSchema::statements() rather
 * than a hand-maintained list, so an in-place rebuild recreates every schema
 * table without a "table already exists" collision — including tables added to
 * the schema after this test was written.
 */
final class ReadModelDropSchemaTest
{
    public function testTableNamesMatchTheSchemaCreateTableStatements(): void
    {
        $expected = [];
        foreach (ReadModelSchema::statements() as $statement) {
            if (preg_match('/^\s*CREATE TABLE\s+([A-Za-z_][A-Za-z0-9_]*)/', $statement, $m) === 1) {
                $expected[] = $m[1];
            }
        }

        assertSame($expected, ReadModelSchema::tableNames());
        // Sanity: the known core tables are present (guards against a parser regression).
        foreach (['metadata', 'posts', 'threads', 'profiles', 'activity'] as $core) {
            assertSame(true, in_array($core, ReadModelSchema::tableNames(), true), "missing core table {$core}");
        }
    }

    public function testInPlaceRebuildRecreatesEverySchemaTableWithoutCollision(): void
    {
        $repositoryRoot = $this->createTempFixtureRoot();
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-dropschema-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);

        try {
            $builder = fn (): ReadModelBuilder => new ReadModelBuilder(
                $repositoryRoot,
                $databasePath,
                new CanonicalRecordRepository($repositoryRoot),
                'dropschema_test',
            );

            // First build creates the schema.
            $builder()->rebuild();
            $afterFirst = $this->tableSet($databasePath);
            foreach (ReadModelSchema::tableNames() as $table) {
                assertSame(true, isset($afterFirst[$table]), "table {$table} should exist after first build");
            }

            // Second build runs dropSchema() + createSchema() over the populated DB.
            // Before the derived-drop-list fix a table missing from the hardcoded
            // DROP list would throw "table already exists" here.
            $builder()->rebuild();
            $afterSecond = $this->tableSet($databasePath);
            assertSame($afterFirst, $afterSecond);
            foreach (ReadModelSchema::tableNames() as $table) {
                assertSame(true, isset($afterSecond[$table]), "table {$table} should exist after in-place rebuild");
            }
        } finally {
            @unlink($databasePath);
            @unlink($databasePath . '-journal');
            $this->deleteTree($repositoryRoot);
        }
    }

    /** @return array<string, true> */
    private function tableSet(string $databasePath): array
    {
        $pdo = new PDO('sqlite:' . $databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $set = [];
        foreach ($pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN) as $name) {
            $set[(string) $name] = true;
        }

        return $set;
    }

    private function createTempFixtureRoot(): string
    {
        $tempRoot = sys_get_temp_dir() . '/forum-rewrite-dropschema-fixture-' . bin2hex(random_bytes(6));
        mkdir($tempRoot, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $tempRoot);

        return $tempRoot;
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) {
                    mkdir($targetPath, 0777, true);
                }
                continue;
            }
            copy($item->getPathname(), $targetPath);
        }
    }

    private function deleteTree(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $items = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
        }
        @rmdir($path);
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                ($message !== '' ? $message . ': ' : '')
                . 'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}
