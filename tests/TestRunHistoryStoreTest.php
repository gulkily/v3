<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/TestRunHistoryStore.php';

final class TestRunHistoryStoreTest
{
    public function testFirstFailureIsClassifiedAsNewFailure(): void
    {
        $store = $this->makeStore();

        $result = $store->recordResults(['ExampleTest::testFoo' => false], '2026-01-01T00:00:00+00:00');

        assertSame('new_failure', $result['ExampleTest::testFoo']['classification']);
        assertSame(1, $result['ExampleTest::testFoo']['consecutiveFailCount']);
        assertSame('2026-01-01T00:00:00+00:00', $result['ExampleTest::testFoo']['firstFailedAt']);
    }

    public function testRepeatedFailureIsClassifiedAsLongStandingWithGrowingStreak(): void
    {
        $store = $this->makeStore();

        $store->recordResults(['ExampleTest::testFoo' => false], '2026-01-01T00:00:00+00:00');
        $result = $store->recordResults(['ExampleTest::testFoo' => false], '2026-01-02T00:00:00+00:00');

        assertSame('long_standing_failure', $result['ExampleTest::testFoo']['classification']);
        assertSame(2, $result['ExampleTest::testFoo']['consecutiveFailCount']);
        assertSame('2026-01-01T00:00:00+00:00', $result['ExampleTest::testFoo']['firstFailedAt']);
    }

    public function testFailureThenPassIsClassifiedAsRecovered(): void
    {
        $store = $this->makeStore();

        $store->recordResults(['ExampleTest::testFoo' => false], '2026-01-01T00:00:00+00:00');
        $result = $store->recordResults(['ExampleTest::testFoo' => true], '2026-01-02T00:00:00+00:00');

        assertSame('recovered', $result['ExampleTest::testFoo']['classification']);
        assertSame(0, $result['ExampleTest::testFoo']['consecutiveFailCount']);
    }

    public function testPassThenPassIsClassifiedAsStillPassing(): void
    {
        $store = $this->makeStore();

        $store->recordResults(['ExampleTest::testFoo' => true], '2026-01-01T00:00:00+00:00');
        $result = $store->recordResults(['ExampleTest::testFoo' => true], '2026-01-02T00:00:00+00:00');

        assertSame('still_passing', $result['ExampleTest::testFoo']['classification']);
    }

    private function makeStore(): TestRunHistoryStore
    {
        $path = sys_get_temp_dir() . '/test-run-history-' . bin2hex(random_bytes(6)) . '.sqlite';
        register_shutdown_function(static function () use ($path): void {
            @unlink($path);
        });

        $store = new TestRunHistoryStore($path);
        $store->ensureSchema();

        return $store;
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
