<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Support\DevServerLog;

final class DevServerLogTest
{
    public function testParseRouterLineReadsEveryField(): void
    {
        $request = DevServerLog::parseRouterLine("V3DEV\t302\tPOST\t/login\t31.5\t-> /home\n");

        assertSame(302, $request['status']);
        assertSame('POST', $request['method']);
        assertSame('/login', $request['uri']);
        assertSame(31.5, $request['ms']);
        assertSame('-> /home', $request['why']);
    }

    public function testParseRouterLineAcceptsEmptyWhy(): void
    {
        $request = DevServerLog::parseRouterLine("V3DEV\t200\tGET\t/\t12.0\t\n");

        assertSame('', $request['why']);
    }

    public function testParseRouterLineIgnoresOtherOutput(): void
    {
        assertSame(null, DevServerLog::parseRouterLine('[Tue Oct  6 01:20:07 2026] PHP Warning: something'));
        assertSame(null, DevServerLog::parseRouterLine("V3DEV\t200\tGET\t/"));
    }

    public function testBuiltInConnectionLinesAreNoise(): void
    {
        assertTrue(DevServerLog::isBuiltInNoise('[Tue Oct  6 01:20:07 2026] 127.0.0.1:54562 Accepted'));
        assertTrue(DevServerLog::isBuiltInNoise('[Tue Oct  6 01:20:07 2026] 127.0.0.1:54562 Closing'));
        assertTrue(DevServerLog::isBuiltInNoise('[Tue Oct  6 01:20:01 2026] PHP 8.1.2 Development Server (http://0.0.0.0:8001) started'));
        assertTrue(DevServerLog::isBuiltInNoise('[Tue Oct  6 01:20:07 2026] 127.0.0.1:54570 [200]: GET /assets/a.js'));
        assertFalse(DevServerLog::isBuiltInNoise('PHP Fatal error:  Uncaught Error: something broke'));
    }

    public function testStaticRequestsAreHiddenUnlessVerbose(): void
    {
        $page = ['status' => 200, 'method' => 'GET', 'uri' => '/', 'ms' => 1.0, 'why' => ''];
        $static = ['status' => 200, 'method' => 'GET', 'uri' => '/assets/a.js', 'ms' => 0.0, 'why' => 'static'];

        assertTrue(DevServerLog::isVisible($page, DevServerLog::MODE_DEFAULT));
        assertFalse(DevServerLog::isVisible($static, DevServerLog::MODE_DEFAULT));
        assertTrue(DevServerLog::isVisible($static, DevServerLog::MODE_VERBOSE));
    }

    public function testQuietModeShowsOnlyServerErrors(): void
    {
        $notFound = ['status' => 404, 'method' => 'GET', 'uri' => '/x', 'ms' => 1.0, 'why' => ''];
        $serverError = ['status' => 500, 'method' => 'GET', 'uri' => '/x', 'ms' => 1.0, 'why' => ''];

        assertFalse(DevServerLog::isVisible($notFound, DevServerLog::MODE_QUIET));
        assertTrue(DevServerLog::isVisible($serverError, DevServerLog::MODE_QUIET));
    }

    public function testGapSeparatorAppearsOnlyAfterAPause(): void
    {
        assertFalse(DevServerLog::needsGapBefore(null, 100));
        assertFalse(DevServerLog::needsGapBefore(100, 102));
        assertTrue(DevServerLog::needsGapBefore(100, 103));
    }

    public function testFormatLineIsPlainWithoutColor(): void
    {
        $request = ['status' => 404, 'method' => 'GET', 'uri' => '/missing', 'ms' => 2.4, 'why' => ''];
        $line = DevServerLog::formatLine($request, ':8000', 0, false);

        assertStringContains(':8000  404  GET', $line);
        assertStringContains('2ms  /missing', $line);
        assertFalse(str_contains($line, "\e["));
    }

    public function testFormatLineColorsStatusWhenRequested(): void
    {
        $request = ['status' => 500, 'method' => 'POST', 'uri' => '/thread/9', 'ms' => 88.0, 'why' => ''];
        $line = DevServerLog::formatLine($request, ':8000', 0, true);

        assertStringContains("\e[31m500\e[0m", $line);
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

if (!function_exists('assertTrue')) {
    function assertTrue(bool $condition): void
    {
        if (!$condition) {
            throw new RuntimeException('Failed asserting that condition is true.');
        }
    }
}

if (!function_exists('assertFalse')) {
    function assertFalse(bool $condition): void
    {
        if ($condition) {
            throw new RuntimeException('Failed asserting that condition is false.');
        }
    }
}

if (!function_exists('assertStringContains')) {
    function assertStringContains(string $needle, string $haystack): void
    {
        if (!str_contains($haystack, $needle)) {
            throw new RuntimeException("Expected to find {$needle}.");
        }
    }
}
