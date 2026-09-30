<?php

declare(strict_types=1);

final class TestRunHistoryStore
{
    private ?PDO $connection = null;

    public function __construct(
        private readonly string $databasePath,
        private readonly int $recoveryHighlightWindowRuns = 5,
    ) {
    }

    public function ensureSchema(): void
    {
        $pdo = $this->open();
        $pdo->exec(
            'CREATE TABLE IF NOT EXISTS test_run_results (
                test_name TEXT PRIMARY KEY,
                last_status TEXT NOT NULL,
                consecutive_fail_count INTEGER NOT NULL DEFAULT 0,
                first_failed_at TEXT,
                consecutive_pass_count INTEGER NOT NULL DEFAULT 0,
                recovered_at TEXT,
                last_changed_at TEXT NOT NULL,
                last_run_at TEXT NOT NULL
            )'
        );

        // Migrate pre-existing databases (created before the recovery-window
        // columns existed) in place rather than requiring a fresh file.
        $existingColumns = [];
        foreach ($pdo->query('PRAGMA table_info(test_run_results)') as $column) {
            $existingColumns[] = $column['name'];
        }

        if (!in_array('consecutive_pass_count', $existingColumns, true)) {
            $pdo->exec('ALTER TABLE test_run_results ADD COLUMN consecutive_pass_count INTEGER NOT NULL DEFAULT 0');
        }

        if (!in_array('recovered_at', $existingColumns, true)) {
            $pdo->exec('ALTER TABLE test_run_results ADD COLUMN recovered_at TEXT');
        }
    }

    /**
     * @param array<string, bool> $results test name => passed
     * @return array<string, array{classification: string, consecutiveFailCount: int, firstFailedAt: ?string, consecutivePassCount: int, recoveredAt: ?string}>
     */
    public function recordResults(array $results, string $runAt): array
    {
        $pdo = $this->open();
        $classifications = [];

        $select = $pdo->prepare(
            'SELECT last_status, consecutive_fail_count, first_failed_at, consecutive_pass_count, recovered_at
             FROM test_run_results WHERE test_name = :test_name'
        );
        $upsert = $pdo->prepare(
            'INSERT INTO test_run_results (
                test_name, last_status, consecutive_fail_count, first_failed_at,
                consecutive_pass_count, recovered_at, last_changed_at, last_run_at
             )
             VALUES (
                :test_name, :last_status, :consecutive_fail_count, :first_failed_at,
                :consecutive_pass_count, :recovered_at, :last_changed_at, :last_run_at
             )
             ON CONFLICT(test_name) DO UPDATE SET
                last_status = excluded.last_status,
                consecutive_fail_count = excluded.consecutive_fail_count,
                first_failed_at = excluded.first_failed_at,
                consecutive_pass_count = excluded.consecutive_pass_count,
                recovered_at = excluded.recovered_at,
                last_changed_at = excluded.last_changed_at,
                last_run_at = excluded.last_run_at'
        );

        foreach ($results as $testName => $passed) {
            $select->execute(['test_name' => $testName]);
            $previous = $select->fetch();

            [$classification, $consecutiveFailCount, $firstFailedAt, $changed, $consecutivePassCount, $recoveredAt]
                = $this->classify($previous, $passed, $runAt);

            $upsert->execute([
                'test_name' => $testName,
                'last_status' => $passed ? 'pass' : 'fail',
                'consecutive_fail_count' => $consecutiveFailCount,
                'first_failed_at' => $firstFailedAt,
                'consecutive_pass_count' => $consecutivePassCount,
                'recovered_at' => $recoveredAt,
                'last_changed_at' => $changed ? $runAt : ($previous['last_changed_at'] ?? $runAt),
                'last_run_at' => $runAt,
            ]);

            $classifications[$testName] = [
                'classification' => $classification,
                'consecutiveFailCount' => $consecutiveFailCount,
                'firstFailedAt' => $firstFailedAt,
                'consecutivePassCount' => $consecutivePassCount,
                'recoveredAt' => $recoveredAt,
            ];
        }

        return $classifications;
    }

    /**
     * Deletes rows for tests that no longer exist in the suite (renamed or
     * removed test methods), which otherwise stay in the table forever since
     * it's keyed by test name and only ever upserted, never pruned.
     *
     * @param list<string> $currentTestNames every test name the current full
     *        run executed - only call this from a full, unfiltered run, or
     *        it will prune tests that simply weren't included this time.
     */
    public function pruneStaleEntries(array $currentTestNames): int
    {
        if ($currentTestNames === []) {
            return 0;
        }

        $pdo = $this->open();
        $placeholders = implode(',', array_fill(0, count($currentTestNames), '?'));

        $countStatement = $pdo->prepare("SELECT COUNT(*) FROM test_run_results WHERE test_name NOT IN ({$placeholders})");
        $countStatement->execute($currentTestNames);
        $staleCount = (int) $countStatement->fetchColumn();

        if ($staleCount === 0) {
            return 0;
        }

        $deleteStatement = $pdo->prepare("DELETE FROM test_run_results WHERE test_name NOT IN ({$placeholders})");
        $deleteStatement->execute($currentTestNames);

        return $staleCount;
    }

    /**
     * @param array<string, mixed>|false $previous
     * @return array{0: string, 1: int, 2: ?string, 3: bool, 4: int, 5: ?string}
     */
    private function classify(array|false $previous, bool $passed, string $runAt): array
    {
        $previousStatus = $previous['last_status'] ?? null;
        $previousPassStreak = $previous !== false ? (int) ($previous['consecutive_pass_count'] ?? 0) : 0;
        $previousRecoveredAt = $previous['recovered_at'] ?? null;

        if ($passed) {
            if ($previousStatus === 'fail') {
                // The run that flips a test from failing to passing - the
                // streak starts here, whether or not it stays highlighted
                // long enough for a second run to see it.
                return ['recovered', 0, null, true, 1, $runAt];
            }

            // A streak only continues (and stays "recently recovered") for
            // up to recoveryHighlightWindowRuns runs; a streak of 0 means
            // this test was never in a recovery streak (or its window
            // already expired), so it's just an ordinary still-passing test.
            if ($previousPassStreak > 0 && $previousPassStreak < $this->recoveryHighlightWindowRuns) {
                return ['recently_recovered', 0, null, false, $previousPassStreak + 1, $previousRecoveredAt];
            }

            return ['still_passing', 0, null, false, 0, null];
        }

        if ($previousStatus === 'fail') {
            $consecutiveFailCount = ((int) $previous['consecutive_fail_count']) + 1;
            $firstFailedAt = $previous['first_failed_at'] ?? $runAt;

            return ['long_standing_failure', $consecutiveFailCount, $firstFailedAt, false, 0, null];
        }

        if ($previousStatus === 'pass') {
            return ['new_failure', 1, $runAt, true, 0, null];
        }

        return ['first_seen_failure', 1, $runAt, true, 0, null];
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
