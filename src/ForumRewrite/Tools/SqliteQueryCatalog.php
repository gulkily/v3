<?php

declare(strict_types=1);

namespace ForumRewrite\Tools;

use RuntimeException;

final class SqliteQueryCatalog
{
    /**
     * @return list<array{id:string,label:string,description:string,category:string,order:int,sql:string,filename:string}>
     */
    public static function load(string $directory): array
    {
        $paths = glob(rtrim($directory, '/') . '/*.sql') ?: [];
        sort($paths, SORT_STRING);
        $entries = [];
        $identifiers = [];

        foreach ($paths as $path) {
            $entry = self::parseFile($path);
            if (isset($identifiers[$entry['id']])) {
                throw new RuntimeException('Duplicate SQLite query identifier: ' . $entry['id']);
            }
            $identifiers[$entry['id']] = true;
            $entries[] = $entry;
        }

        usort($entries, static fn (array $left, array $right): int => [$left['order'], $left['id']] <=> [$right['order'], $right['id']]);
        return $entries;
    }

    /**
     * @return array{id:string,label:string,description:string,category:string,order:int,sql:string,filename:string}
     */
    private static function parseFile(string $path): array
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read SQLite query file: ' . $path);
        }

        $metadata = [];
        $lines = preg_split('/\R/', $contents) ?: [];
        $sqlStart = 0;
        foreach ($lines as $index => $line) {
            if (preg_match('/^--\s*([a-z_]+):\s*(.*?)\s*$/', $line, $matches) === 1) {
                $metadata[$matches[1]] = $matches[2];
                $sqlStart = $index + 1;
                continue;
            }
            if (trim($line) === '' && $metadata !== []) {
                $sqlStart = $index + 1;
                continue;
            }
            break;
        }

        foreach (['id', 'label', 'description', 'category', 'order'] as $field) {
            if (!isset($metadata[$field]) || $metadata[$field] === '') {
                throw new RuntimeException(sprintf('Missing %s metadata in %s', $field, basename($path)));
            }
        }
        if (preg_match('/^[a-z0-9][a-z0-9_-]*$/', $metadata['id']) !== 1) {
            throw new RuntimeException('Invalid SQLite query identifier in ' . basename($path));
        }
        if (filter_var($metadata['order'], FILTER_VALIDATE_INT) === false) {
            throw new RuntimeException('Invalid SQLite query order in ' . basename($path));
        }

        $sql = trim(implode("\n", array_slice($lines, $sqlStart)));
        $withoutTrailingSemicolon = trim(preg_replace('/;\s*$/', '', $sql) ?? '');
        if ($withoutTrailingSemicolon === '' || preg_match('/^(?:SELECT|WITH)\b/i', $withoutTrailingSemicolon) !== 1 || str_contains($withoutTrailingSemicolon, ';')) {
            throw new RuntimeException('SQLite query must be one read-only SELECT statement in ' . basename($path));
        }

        return [
            'id' => $metadata['id'],
            'label' => $metadata['label'],
            'description' => $metadata['description'],
            'category' => $metadata['category'],
            'order' => (int) $metadata['order'],
            'sql' => $sql,
            'filename' => basename($path),
        ];
    }
}
