<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Support\DatabaseLockProbe;

final class DatabaseLockProbeTest
{
    public function testReportsMissingWithoutCreatingFile(): void
    {
        $path = sys_get_temp_dir() . '/forum-lock-probe-missing-' . bin2hex(random_bytes(6)) . '.sqlite3';

        $result = (new DatabaseLockProbe())->probe($path, 0.1);

        assertSame('missing', $result['status']);
        assertSame(false, is_file($path));
    }

    public function testReportsAccessibleAndLeavesDataUnchanged(): void
    {
        $path = $this->database();
        try {
            $before = hash_file('sha256', $path);

            $result = (new DatabaseLockProbe())->probe($path, 0.1);

            assertSame('accessible', $result['status']);
            assertSame($before, hash_file('sha256', $path));
        } finally {
            @unlink($path);
        }
    }

    public function testReportsLockedWhenAnotherConnectionHoldsWriteLock(): void
    {
        $path = $this->database();
        try {
            $holder = new PDO('sqlite:' . $path);
            $holder->exec('BEGIN IMMEDIATE');
            $start = hrtime(true);

            $result = (new DatabaseLockProbe())->probe($path, 0.2);
            $elapsed = (hrtime(true) - $start) / 1e9;
            $holder->exec('ROLLBACK');

            assertSame('locked', $result['status']);
            assertSame(true, $elapsed < 2.0);
            assertSame('accessible', (new DatabaseLockProbe())->probe($path, 0.1)['status']);
        } finally {
            @unlink($path);
        }
    }

    public function testReportsLockedWhenExclusiveLockBlocksReads(): void
    {
        $path = $this->database();
        try {
            $holder = new PDO('sqlite:' . $path);
            $holder->exec('BEGIN EXCLUSIVE');

            $result = (new DatabaseLockProbe())->probe($path, 0.2);
            $holder->exec('ROLLBACK');

            assertSame('locked', $result['status']);
        } finally {
            @unlink($path);
        }
    }

    public function testReportsUnavailableForNonDatabaseFile(): void
    {
        $path = sys_get_temp_dir() . '/forum-lock-probe-garbage-' . bin2hex(random_bytes(6)) . '.sqlite3';
        file_put_contents($path, str_repeat('not a database ', 100));
        try {
            assertSame('unavailable', (new DatabaseLockProbe())->probe($path, 0.1)['status']);
        } finally {
            @unlink($path);
        }
    }

    private function database(): string
    {
        $path = sys_get_temp_dir() . '/forum-lock-probe-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $pdo = new PDO('sqlite:' . $path);
        $pdo->exec('CREATE TABLE t (id INTEGER)');
        $pdo = null;

        return $path;
    }
}
