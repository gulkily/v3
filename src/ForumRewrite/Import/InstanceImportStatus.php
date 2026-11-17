<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

/**
 * What the Tools → Import page shows about past imports: a pending interrupted run, and the
 * newest finished or canceled run with its saved report. Reads the recovery state that
 * ContentImportRunner keeps under `.git/instance-import/` and never writes to it.
 */
final class InstanceImportStatus
{
    public function __construct(private readonly string $repositoryRoot)
    {
    }

    /**
     * @return array{
     *   pending: ?array{id: string, phase: string, source: string, committed: bool},
     *   last: ?array{id: string, outcome: string, at: string, report: array<string, mixed>}
     * }
     */
    public function read(): array
    {
        $root = $this->repositoryRoot . '/.git/instance-import';
        $pending = null;
        $pendingRun = self::json($root . '/pending.json');
        if ($pendingRun !== null) {
            $pending = [
                'id' => (string) ($pendingRun['id'] ?? ''),
                'phase' => (string) ($pendingRun['phase'] ?? ''),
                'source' => (string) ($pendingRun['report']['source'] ?? ''),
                'committed' => ($pendingRun['phase'] ?? '') !== 'prepared',
            ];
        }
        $last = null;
        $directories = is_dir($root) ? (glob($root . '/*', GLOB_ONLYDIR) ?: []) : [];
        rsort($directories, SORT_STRING);
        foreach ($directories as $directory) {
            $id = basename($directory);
            if ($pending !== null && $id === $pending['id']) {
                continue;
            }
            $report = self::json($directory . '/report.json');
            if ($report !== null) {
                $last = ['id' => $id, 'outcome' => (string) ($report['status'] ?? 'complete'), 'at' => self::runTime($id), 'report' => $report];
                break;
            }
            $canceled = self::json($directory . '/canceled.json');
            if ($canceled !== null) {
                $last = ['id' => $id, 'outcome' => 'canceled', 'at' => (string) ($canceled['canceled_at'] ?? self::runTime($id)), 'report' => (array) ($canceled['report'] ?? [])];
                break;
            }
        }

        return ['pending' => $pending, 'last' => $last];
    }

    /** Run IDs start with their UTC start time, e.g. 20261116UTC203000-ab12cd34ef56 (the runner's gmdate('YmdTHis')). */
    private static function runTime(string $id): string
    {
        if (preg_match('/^(\d{4})(\d{2})(\d{2})[A-Z]+(\d{2})(\d{2})(\d{2})-/', $id, $m) !== 1) {
            return '';
        }

        return sprintf('%s-%s-%sT%s:%s:%sZ', $m[1], $m[2], $m[3], $m[4], $m[5], $m[6]);
    }

    /** @return array<string, mixed>|null */
    private static function json(string $path): ?array
    {
        if (!is_file($path) || is_link($path) || filesize($path) > 67108864) {
            return null;
        }
        try {
            $decoded = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return null;
        }

        return is_array($decoded) ? $decoded : null;
    }
}
