<?php

declare(strict_types=1);

use ForumRewrite\ReadModel\IncrementalReadModelUpdater;
use ForumRewrite\ReadModel\ReadModelMetadata;

final class ReadModelMetadataTest
{
    public function testExpectedSchemaIdentityRequiresVersionAndFingerprint(): void
    {
        $identity = ReadModelMetadata::expectedSchemaIdentity();

        assertSame(true, ReadModelMetadata::hasExpectedSchemaIdentity($identity));
        assertSame(false, ReadModelMetadata::hasExpectedSchemaIdentity([
            'schema_version' => $identity['schema_version'],
        ]));
        assertSame(false, ReadModelMetadata::hasExpectedSchemaIdentity([
            'schema_version' => 'unexpected',
            'schema_fingerprint' => $identity['schema_fingerprint'],
        ]));
        assertSame(false, ReadModelMetadata::hasExpectedSchemaIdentity([
            'schema_version' => $identity['schema_version'],
            'schema_fingerprint' => 'unexpected',
        ]));
    }

    public function testIncrementalUpdatesWriteTheExpectedSchemaIdentity(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE metadata (key TEXT PRIMARY KEY, value TEXT NOT NULL)');

        (new IncrementalReadModelUpdater('/tmp/read-model.sqlite3', '/repository'))->writeMetadata($pdo, 'commit-sha');

        $metadata = $pdo->query('SELECT key, value FROM metadata')->fetchAll(PDO::FETCH_KEY_PAIR);
        assertSame(true, ReadModelMetadata::hasExpectedSchemaIdentity($metadata));
        assertSame('/repository', $metadata['repository_root']);
        assertSame('commit-sha', $metadata['repository_head']);
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
