<?php

declare(strict_types=1);

// Loaded only by subprocess behavior checks, never registered in the main suite.
final class RunnerTimingFixtureTest
{
    public function __construct()
    {
        usleep(100_000);
    }

    public function testPass(): void
    {
        usleep(30_000);
        fwrite(STDOUT, "fixture output\n");
    }

    public function testFast(): void
    {
    }

    public function testFailure(): void
    {
        throw new RuntimeException("First failure line\nDiagnostic detail\n");
    }
}

require dirname(__DIR__) . '/run.php';
