<?php

declare(strict_types=1);

final class TestRunHistoryStore
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly string $databasePath,
    ) {
    }

    public function ensureSchema(): void
    {
        $this->open()->exec(
            'CREATE TABLE IF NOT EXISTS test_run_results (
                test_name TEXT PRIMARY KEY,
                last_status TEXT NOT NULL,
                consecutive_fail_count INTEGER NOT NULL DEFAULT 0,
                first_failed_at TEXT,
                last_changed_at TEXT NOT NULL,
                last_run_at TEXT NOT NULL
            )'
        );
    }

    /**
     * @param array<string, bool> $results test name => passed
     * @return array<string, array{classification: string, consecutiveFailCount: int, firstFailedAt: ?string}>
     */
    public function recordResults(array $results, string $runAt): array
    {
        $pdo = $this->open();
        $classifications = [];

        $select = $pdo->prepare('SELECT last_status, consecutive_fail_count, first_failed_at FROM test_run_results WHERE test_name = :test_name');
        $upsert = $pdo->prepare(
            'INSERT INTO test_run_results (test_name, last_status, consecutive_fail_count, first_failed_at, last_changed_at, last_run_at)
             VALUES (:test_name, :last_status, :consecutive_fail_count, :first_failed_at, :last_changed_at, :last_run_at)
             ON CONFLICT(test_name) DO UPDATE SET
                last_status = excluded.last_status,
                consecutive_fail_count = excluded.consecutive_fail_count,
                first_failed_at = excluded.first_failed_at,
                last_changed_at = excluded.last_changed_at,
                last_run_at = excluded.last_run_at'
        );

        foreach ($results as $testName => $passed) {
            $select->execute(['test_name' => $testName]);
            $previous = $select->fetch();

            [$classification, $consecutiveFailCount, $firstFailedAt, $changed] = $this->classify($previous, $passed, $runAt);

            $upsert->execute([
                'test_name' => $testName,
                'last_status' => $passed ? 'pass' : 'fail',
                'consecutive_fail_count' => $consecutiveFailCount,
                'first_failed_at' => $firstFailedAt,
                'last_changed_at' => $changed ? $runAt : ($previous['last_changed_at'] ?? $runAt),
                'last_run_at' => $runAt,
            ]);

            $classifications[$testName] = [
                'classification' => $classification,
                'consecutiveFailCount' => $consecutiveFailCount,
                'firstFailedAt' => $firstFailedAt,
            ];
        }

        return $classifications;
    }

    /**
     * @param array<string, mixed>|false $previous
     * @return array{0: string, 1: int, 2: ?string, 3: bool}
     */
    private function classify(array|false $previous, bool $passed, string $runAt): array
    {
        $previousStatus = $previous['last_status'] ?? null;

        if ($passed) {
            if ($previousStatus === 'fail') {
                return ['recovered', 0, null, true];
            }

            return ['still_passing', 0, null, false];
        }

        if ($previousStatus === 'fail') {
            $consecutiveFailCount = ((int) $previous['consecutive_fail_count']) + 1;
            $firstFailedAt = $previous['first_failed_at'] ?? $runAt;

            return ['long_standing_failure', $consecutiveFailCount, $firstFailedAt, false];
        }

        if ($previousStatus === 'pass') {
            return ['new_failure', 1, $runAt, true];
        }

        return ['first_seen_failure', 1, $runAt, true];
    }

    private function open(): PDO
    {
        if ($this->connection !== null) {
            return $this->connection;
        }

        $directory = dirname($this->databasePath);
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $pdo = new PDO('sqlite:' . $this->databasePath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        $this->connection = $pdo;

        return $pdo;
    }
}
