<?php

declare(strict_types=1);

use ForumRewrite\ReadModel\ReadModelSchema;

final class ReadModelSchemaTest
{
    public function testCanonicalStatementsCreateExpectedTablesAndIndexes(): void
    {
        $pdo = new PDO('sqlite::memory:');
        foreach (ReadModelSchema::statements() as $statement) {
            $pdo->exec($statement);
        }

        $objects = $pdo->query(
            "SELECT name FROM sqlite_master WHERE type IN ('table', 'index') AND name NOT LIKE 'sqlite_%' ORDER BY name"
        )->fetchAll(PDO::FETCH_COLUMN);

        assertSame([
            'activity',
            'activity_action_key_idx',
            'activity_post_id_idx',
            'activity_recent_idx',
            'commits',
            'commits_committed_at_idx',
            'instance_public',
            'metadata',
            'posts',
            'profiles',
            'threads',
            'username_routes',
        ], $objects);
    }

    public function testFingerprintIsDeterministicForCanonicalSchema(): void
    {
        $fingerprint = ReadModelSchema::fingerprint();

        assertSame($fingerprint, ReadModelSchema::fingerprint());
        assertSame(64, strlen($fingerprint));
        assertSame(1, preg_match('/^[a-f0-9]{64}$/', $fingerprint));
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}
