<?php

declare(strict_types=1);

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class ReadModelBuilderTimingTest
{
    public function testRebuildCapturesExpectedTimingPhases(): void
    {
        $repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-builder-timing-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);

        $builder = new ReadModelBuilder(
            $repositoryRoot,
            $databasePath,
            new CanonicalRecordRepository($repositoryRoot),
            'timing_test',
        );
        $builder->rebuild();

        $timings = $builder->timings();

        assertSame(true, isset($timings['drop_schema']));
        assertSame(true, isset($timings['create_schema']));
        assertSame(true, isset($timings['index_posts']));
        assertSame(true, isset($timings['index_profiles']));
        assertSame(true, isset($timings['derive_approval_state']));
        assertSame(true, isset($timings['link_post_authors']));
        assertSame(true, isset($timings['index_instance']));
        assertSame(true, isset($timings['index_activity']));
        assertSame(true, isset($timings['write_metadata']));
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
