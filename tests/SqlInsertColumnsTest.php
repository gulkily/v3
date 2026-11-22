<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

/**
 * Every SQL insert in `src/`, `scripts/` and `tests/` names its columns (CONTRIBUTING.md,
 * "SQL inserts name their columns"). An insert without a column list breaks as
 * soon as its table gains a column, which makes rollbacks and migrations unsafe.
 */
final class SqlInsertColumnsTest
{
    /**
     * Statements in the read-model schema list. ReadModelSchema::fingerprint() hashes that
     * list, so editing them forces a full rebuild on every instance. They run only beside
     * the CREATE TABLE in the same list. Name their columns the next time SCHEMA_VERSION
     * changes for another reason, then remove them here. Do not add entries for new code.
     *
     * path => number of inserts without a column list
     */
    private const READ_MODEL_SCHEMA_EXCEPTIONS = [
        'src/ForumRewrite/ReadModel/ActivityCounterSchema.php' => 2,
        'src/ForumRewrite/Messaging/PrivateMessageHistoryRevision.php' => 2,
    ];

    public function testEveryInsertNamesItsColumns(): void
    {
        $root = dirname(__DIR__);
        $found = [];
        foreach (['src', 'scripts', 'tests'] as $directory) {
            $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root . '/' . $directory, FilesystemIterator::SKIP_DOTS));
            foreach ($files as $file) {
                if (!$file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }
                $path = substr($file->getPathname(), strlen($root) + 1);
                if ($path === 'tests/SqlInsertColumnsTest.php') {
                    continue; // holds the pattern's own bad examples
                }
                $lines = self::insertsWithoutColumns((string) file_get_contents($file->getPathname()));
                if ($lines !== []) {
                    $found[$path] = $lines;
                }
            }
        }

        $problems = [];
        foreach ($found as $path => $lines) {
            if (count($lines) !== (self::READ_MODEL_SCHEMA_EXCEPTIONS[$path] ?? 0)) {
                $problems[] = $path . ':' . implode(',', $lines);
            }
        }
        assertSame([], $problems, 'Name the columns: INSERT INTO t (a, b) VALUES (?, ?). Found without a column list: ' . implode('; ', $problems));

        $stale = array_values(array_diff(array_keys(self::READ_MODEL_SCHEMA_EXCEPTIONS), array_keys($found)));
        assertSame([], $stale, 'Remove fixed files from READ_MODEL_SCHEMA_EXCEPTIONS: ' . implode(', ', $stale));
    }

    public function testPatternFlagsOnlyInsertsWithoutColumns(): void
    {
        $flagged = [
            'INSERT INTO t VALUES (?, ?)',
            'INSERT OR REPLACE INTO t VALUES(?,?)',
            'REPLACE INTO t VALUES (1)',
            'insert into temp.t values (1)',
            'INSERT INTO t SELECT a, b FROM u',
            "'INSERT INTO ' . \$table . ' VALUES (?,?)'",
            'INSERT INTO {$prefix}_revision VALUES (1)',
            "INSERT INTO t\n    VALUES (1)",
        ];
        foreach ($flagged as $sql) {
            assertSame(1, count(self::insertsWithoutColumns($sql)), 'Should be flagged: ' . $sql);
        }
        $allowed = [
            'INSERT INTO t (a, b) VALUES (?, ?)',
            'INSERT INTO t(a,b) VALUES(?,?)',
            "INSERT INTO t\n    (a, b)\n    VALUES (:a, :b)",
            'INSERT INTO t (a, b) SELECT a, b FROM u',
            'INSERT INTO t DEFAULT VALUES',
            "'INSERT INTO ' . \$table . ' (a, b) VALUES (?,?)'",
        ];
        foreach ($allowed as $sql) {
            assertSame([], self::insertsWithoutColumns($sql), 'Should be allowed: ' . $sql);
        }
    }

    /**
     * Line numbers of INSERT/REPLACE statements whose table name is followed by VALUES,
     * SELECT or WITH instead of a column list.
     *
     * @return list<int>
     */
    private static function insertsWithoutColumns(string $source): array
    {
        $lines = [];
        $pattern = '/\b(?:INSERT(?:\s+OR\s+[A-Z]+)?|REPLACE)\s+INTO\s+([^(;]{1,120}?)\s*\b(?:VALUES|SELECT|WITH)\b/i';
        if (preg_match_all($pattern, $source, $matches, PREG_OFFSET_CAPTURE) > 0) {
            foreach ($matches[0] as $i => [$text, $offset]) {
                if (preg_match('/\bDEFAULT\s*$/i', $matches[1][$i][0]) === 1) {
                    continue;
                }
                $lines[] = substr_count($source, "\n", 0, $offset) + 1;
            }
        }

        return $lines;
    }
}
