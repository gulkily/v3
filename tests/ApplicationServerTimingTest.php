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
                'ignored-bad-name' => 99,
                'ignored_value' => 'oops',
            ],
        ]);

        assertSame(
            ['Server-Timing: write_file;dur=1.2, git_commit;dur=34.6'],
            $headers
        );
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
