<?php

declare(strict_types=1);

namespace ForumRewrite\ReadModel;

use ForumRewrite\Support\ExecutionLock;
use RuntimeException;

final class ReadModelRecovery
{
    /**
     * @return array{
     *   database_exists:bool,
     *   lock_held:bool,
     *   sidecars:list<array{path:string,suffix:string,size:int,modified_at:string,journal_header:string}>,
     *   holders:list<array{pid:int,command:string,paths:list<string>}>
     * }
     */
    public static function inspect(string $databasePath): array
    {
        $sidecars = [];
        foreach (['-journal', '-wal', '-shm'] as $suffix) {
            $path = $databasePath . $suffix;
            if (!is_file($path)) {
                continue;
            }

            $sidecars[] = [
                'path' => $path,
                'suffix' => $suffix,
                'size' => (int) (filesize($path) ?: 0),
                'modified_at' => gmdate('c', (int) (filemtime($path) ?: 0)),
                'journal_header' => $suffix === '-journal' ? self::journalHeaderStatus($path) : 'not applicable',
            ];
        }

        $lockPath = dirname($databasePath) . '/forum-rewrite.lock';
        return [
            'database_exists' => is_file($databasePath),
            'lock_held' => (new ExecutionLock($lockPath))->isLocked(),
            'sidecars' => $sidecars,
            'holders' => self::fileHolders(array_merge([$databasePath], array_column($sidecars, 'path'))),
        ];
    }

    /**
     * Archives the live database and every present sidecar without opening
     * SQLite. The caller can then rebuild the derived read model from records.
     *
     * @return string absolute recovery directory
     */
    public static function archiveForRebuild(string $databasePath): string
    {
        $diagnosis = self::inspect($databasePath);
        if ($diagnosis['lock_held']) {
            throw new RuntimeException('Read-model recovery refused: the application rebuild lock is currently held. Stop the active operation and retry.');
        }
        if ($diagnosis['holders'] !== []) {
            $pids = implode(', ', array_map(static fn (array $holder): string => (string) $holder['pid'], $diagnosis['holders']));
            throw new RuntimeException("Read-model recovery refused: process(es) still have the database or a sidecar open: {$pids}. Stop them and retry.");
        }
        if ($diagnosis['sidecars'] === []) {
            throw new RuntimeException('Read-model recovery is not needed: no SQLite sidecars were found.');
        }
        if (!$diagnosis['database_exists']) {
            throw new RuntimeException('Read-model recovery refused: the live database file is missing.');
        }

        $recoveryRoot = dirname($databasePath) . '/read-model-recovery-'
            . gmdate('Ymd\\THis\\Z') . '-' . bin2hex(random_bytes(4));
        $snapshotRoot = $recoveryRoot . '/snapshot';
        $retiredRoot = $recoveryRoot . '/retired';
        if (!mkdir($snapshotRoot, 0700, true) && !is_dir($snapshotRoot)) {
            throw new RuntimeException('Unable to create read-model recovery backup directory: ' . $snapshotRoot);
        }
        if (!mkdir($retiredRoot, 0700, true) && !is_dir($retiredRoot)) {
            throw new RuntimeException('Unable to create read-model recovery retirement directory: ' . $retiredRoot);
        }

        $paths = array_merge([$databasePath], array_column($diagnosis['sidecars'], 'path'));
        foreach ($paths as $path) {
            if (!copy($path, $snapshotRoot . '/' . basename($path))) {
                throw new RuntimeException('Unable to create read-model recovery snapshot: ' . $path);
            }
        }

        $movedPaths = [];
        try {
            foreach ($paths as $path) {
                $retiredPath = $retiredRoot . '/' . basename($path);
                if (!rename($path, $retiredPath)) {
                    throw new RuntimeException('Unable to archive read-model file for recovery: ' . $path);
                }
                $movedPaths[$path] = $retiredPath;
            }
        } catch (\Throwable $throwable) {
            foreach (array_reverse($movedPaths, true) as $originalPath => $retiredPath) {
                @rename($retiredPath, $originalPath);
            }
            throw $throwable;
        }

        return $recoveryRoot;
    }

    /**
     * @param array{
     *   database_exists:bool,
     *   lock_held:bool,
     *   sidecars:list<array{path:string,suffix:string,size:int,modified_at:string,journal_header:string}>,
     *   holders:list<array{pid:int,command:string,paths:list<string>}>
     * } $diagnosis
     */
    public static function renderDiagnosis(string $databasePath, array $diagnosis): string
    {
        $lines = [
            'Read-model SQLite diagnosis',
            'Database: ' . $databasePath,
            'Database file: ' . ($diagnosis['database_exists'] ? 'present' : 'missing'),
            'Application rebuild lock: ' . ($diagnosis['lock_held'] ? 'held' : 'not held'),
        ];

        if ($diagnosis['sidecars'] === []) {
            $lines[] = 'SQLite sidecars: none found';
        } else {
            $lines[] = 'SQLite sidecars:';
            foreach ($diagnosis['sidecars'] as $sidecar) {
                $header = $sidecar['suffix'] === '-journal' ? '; rollback-journal header: ' . $sidecar['journal_header'] : '';
                $lines[] = sprintf(
                    '  %s (%d bytes; modified %s%s)',
                    $sidecar['path'],
                    $sidecar['size'],
                    $sidecar['modified_at'],
                    $header,
                );
            }
        }

        if ($diagnosis['holders'] === []) {
            $lines[] = 'Open file holders for the current user: none found';
        } else {
            $lines[] = 'Open file holders for the current user:';
            foreach ($diagnosis['holders'] as $holder) {
                $lines[] = sprintf('  PID %d (%s): %s', $holder['pid'], $holder['command'], implode(', ', $holder['paths']));
            }
        }

        if ($diagnosis['sidecars'] !== [] && !$diagnosis['lock_held'] && $diagnosis['holders'] === []) {
            $lines[] = 'Next action: ./v3 rebuild recover --confirm';
            $lines[] = 'Recovery preserves the live database and sidecars together under state/cache/read-model-recovery-*/ before rebuilding.';
        } elseif ($diagnosis['sidecars'] !== []) {
            $lines[] = 'Next action: stop the listed holder(s), then run ./v3 rebuild diagnose again.';
        }

        return implode("\n", $lines) . "\n";
    }

    private static function journalHeaderStatus(string $path): string
    {
        $header = file_get_contents($path, false, null, 0, 8);
        if ($header === false || strlen($header) !== 8) {
            return 'unreadable or too short';
        }

        return hash_equals($header, hex2bin('d9d505f920a163d7'))
            ? 'SQLite signature present'
            : 'signature absent';
    }

    /**
     * @param list<string> $paths
     * @return list<array{pid:int,command:string,paths:list<string>}>
     */
    private static function fileHolders(array $paths): array
    {
        $watchedPaths = array_fill_keys($paths, true);
        $holders = [];
        $currentUserId = function_exists('posix_geteuid') ? posix_geteuid() : null;
        foreach (glob('/proc/[0-9]*/fd') ?: [] as $fdDirectory) {
            $pid = (int) basename(dirname($fdDirectory));
            if ($currentUserId !== null && !self::isOwnedByUser($pid, $currentUserId)) {
                continue;
            }

            $heldPaths = [];
            foreach (@scandir($fdDirectory) ?: [] as $descriptor) {
                if ($descriptor === '.' || $descriptor === '..') {
                    continue;
                }

                $target = @readlink($fdDirectory . '/' . $descriptor);
                if ($target !== false && isset($watchedPaths[$target])) {
                    $heldPaths[] = $target;
                }
            }

            if ($heldPaths === []) {
                continue;
            }

            $command = @file_get_contents('/proc/' . $pid . '/cmdline');
            $holders[] = [
                'pid' => $pid,
                'command' => $command === false || $command === '' ? 'unknown' : str_replace("\0", ' ', trim($command)),
                'paths' => array_values(array_unique($heldPaths)),
            ];
        }

        return $holders;
    }

    private static function isOwnedByUser(int $pid, int $userId): bool
    {
        $status = @file_get_contents('/proc/' . $pid . '/status');
        return $status !== false
            && preg_match('/^Uid:\\s+(\\d+)/m', $status, $matches) === 1
            && (int) $matches[1] === $userId;
    }
}
