<?php

declare(strict_types=1);

use ForumRewrite\Application;

final class ApplicationServerTimingTest
{
    public function testServerTimingHeadersFormatsKnownTimingMetrics(): void
    {
        $application = new Application(
            dirname(__DIR__),
            __DIR__ . '/fixtures/parity_minimal_v1',
            sys_get_temp_dir() . '/forum-rewrite-server-timing-' . bin2hex(random_bytes(6)) . '.sqlite3',
        );

        $method = new ReflectionMethod($application, 'serverTimingHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($application, [
            'timings' => [
                'write_file' => 1.2,
                'git_commit' => 34.56,
                'read_model_index_posts' => 128.4,
                'ignored-bad-name' => 99,
                'ignored_value' => 'oops',
            ],
        ]);

        assertSame(
            ['Server-Timing: write_file;dur=1.2, git_commit;dur=34.6, read_model_index_posts;dur=128.4'],
            $headers
        );
    }

    public function testNoStoreTimingHeadersCombinesCacheAndTimingHeaders(): void
    {
        $application = new Application(
            dirname(__DIR__),
            __DIR__ . '/fixtures/parity_minimal_v1',
            sys_get_temp_dir() . '/forum-rewrite-server-timing-' . bin2hex(random_bytes(6)) . '.sqlite3',
        );

        $method = new ReflectionMethod($application, 'noStoreTimingHeaders');
        $method->setAccessible(true);

        $headers = $method->invoke($application, [
            'request_data' => 0.2,
            'post_analysis' => 42.25,
            'agent_reply' => 8.0,
            'total' => 55.6,
        ]);

        assertSame(
            [
                'Cache-Control: no-store, no-cache, must-revalidate, max-age=0',
                'Pragma: no-cache',
                'Expires: 0',
                'Server-Timing: request_data;dur=0.2, post_analysis;dur=42.2, agent_reply;dur=8.0, total;dur=55.6',
            ],
            $headers
        );
    }

    public function testMergeResultTimingsPreservesWriterTotalAsWriteTotal(): void
    {
        $application = new Application(
            dirname(__DIR__),
            __DIR__ . '/fixtures/parity_minimal_v1',
            sys_get_temp_dir() . '/forum-rewrite-server-timing-' . bin2hex(random_bytes(6)) . '.sqlite3',
        );

        $method = new ReflectionMethod($application, 'mergeResultTimings');
        $method->setAccessible(true);

        $startedAt = hrtime(true);
        usleep(1000);
        $result = $method->invoke($application, [
            'status' => 'ok',
            'timings' => [
                'lock_wait' => 0.1,
                'total' => 12.3,
            ],
        ], [
            'request_data' => 0.2,
        ], $startedAt);

        assertSame('ok', $result['status']);
        assertSame(0.2, $result['timings']['request_data']);
        assertSame(0.1, $result['timings']['lock_wait']);
        assertSame(12.3, $result['timings']['write_total']);
        if (!isset($result['timings']['total']) || !is_float($result['timings']['total'])) {
            throw new RuntimeException('Expected merged total timing.');
        }
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
