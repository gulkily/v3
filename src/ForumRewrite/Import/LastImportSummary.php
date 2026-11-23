<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

/**
 * The newest finished import into this repository, whichever way it was started. Every import
 * that reaches the merge saves `.git/instance-import/<run>/report.json` (ContentImportRunner),
 * from Tools → Import, the local start page or `./v3 import-instance` alike, so the saved
 * reports are the one record all three share. This reads them and never writes.
 *
 * Runs are ordered by when their report was written, not by run name: names carry a start time
 * to the second and a random suffix, so two quick runs do not sort by name.
 */
final class LastImportSummary
{
    /** Enough of a report to reach its counts; the per-file entries that follow are not read. */
    private const HEAD_BYTES = 65536;

    public function __construct(private readonly string $repositoryRoot)
    {
    }

    /**
     * @param ?string $preferRunId when two reports were written in the same second, the run to take as newer
     *                             (the one the newest page request's own output names)
     * @return ?array{
     *   id: string, source: string, host: string, at: int, status: string, counts: array<string, int>,
     *   imported: int, held_back: int, conflicts: int, repeat: bool
     * }
     */
    public function read(?string $preferRunId = null): ?array
    {
        $runs = [];
        foreach (glob($this->repositoryRoot . '/.git/instance-import/*/report.json') ?: [] as $path) {
            if (is_link($path) || is_link(dirname($path))) {
                continue;
            }
            $head = self::head($path);
            // A preview plans an import without installing anything.
            if ($head === null || ($head['preview'] ?? false) === true || !is_string($head['source'] ?? null)) {
                continue;
            }
            $runs[] = ['id' => basename(dirname($path)), 'at' => (int) filemtime($path), 'head' => $head];
        }
        if ($runs === []) {
            return null;
        }
        usort($runs, static fn (array $a, array $b): int => [$b['at'], $b['id'] === $preferRunId ? 1 : 0, $b['id']] <=> [$a['at'], $a['id'] === $preferRunId ? 1 : 0, $a['id']]);
        $last = $runs[0];
        $source = (string) $last['head']['source'];
        $counts = [];
        foreach (is_array($last['head']['counts'] ?? null) ? $last['head']['counts'] : [] as $category => $count) {
            $counts[(string) $category] = (int) $count;
        }
        $repeat = false;
        foreach (array_slice($runs, 1) as $earlier) {
            if (self::sameSource((string) $earlier['head']['source'], $source)) {
                $repeat = true;
                break;
            }
        }
        $host = parse_url($source, PHP_URL_HOST);
        $port = parse_url($source, PHP_URL_PORT);

        return [
            'id' => $last['id'],
            'source' => $source,
            'host' => is_string($host) && $host !== '' ? $host . ($port !== null && $port !== false ? ':' . $port : '') : $source,
            'at' => $last['at'],
            'status' => (string) ($last['head']['status'] ?? 'complete'),
            'counts' => $counts,
            'imported' => $counts['import'] ?? 0,
            // Excluded records are left out by rule (authority, settings, private data), not held for review.
            'held_back' => ($counts['conflict'] ?? 0) + ($counts['invalid'] ?? 0) + ($counts['unsupported'] ?? 0),
            'conflicts' => $counts['conflict'] ?? 0,
            'repeat' => $repeat,
        ];
    }

    private static function sameSource(string $a, string $b): bool
    {
        return rtrim(strtolower($a), '/') === rtrim(strtolower($b), '/');
    }

    /**
     * The report's fields before `entries` (source, preview, status, counts). Reports list every
     * file under `entries` and run to megabytes; the start page reads this on each visit.
     *
     * @return array<string, mixed>|null
     */
    private static function head(string $path): ?array
    {
        $text = @file_get_contents($path, false, null, 0, self::HEAD_BYTES);
        if (!is_string($text) || $text === '') {
            return null;
        }
        $cut = strpos($text, '"entries":');
        if ($cut !== false) {
            $text = rtrim(rtrim(substr($text, 0, $cut)), ',') . '}';
        }
        $decoded = json_decode($text, true);
        if (!is_array($decoded) && $cut !== false) {
            // An unexpected layout: fall back to the whole file.
            $decoded = json_decode((string) @file_get_contents($path), true);
        }

        return is_array($decoded) ? $decoded : null;
    }
}
