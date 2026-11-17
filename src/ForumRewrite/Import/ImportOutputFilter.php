<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use Closure;

/**
 * Decides which import lines reach the terminal.
 *
 * - `Progress:` lines are for the import page's task log and print only when asked for;
 *   otherwise planning's count becomes "Checking records (n/m)."
 * - A counter line ("Installed 300 record files...", "Downloaded 1048576 bytes...") prints at
 *   most once a second after a line of the same wording. The last one held back prints before
 *   the next different line, so each step still shows where it ended.
 * - Read-model rebuild detail prints only with --verbose, or when nothing else has printed for
 *   five seconds, so a long rebuild still shows where it is.
 */
final class ImportOutputFilter
{
    private const QUIET_SECONDS = 5.0;

    private ?string $lastShape = null;
    private float $lastCounterPrinted = 0.0;
    private ?string $heldCounter = null;
    private ?float $lastPrinted = null;
    private readonly Closure $clock;

    public function __construct(private readonly bool $progressLines = false, ?Closure $clock = null, private readonly bool $verbose = false)
    {
        $this->clock = $clock ?? static fn (): float => microtime(true);
    }

    /** @return list<string> Lines to print now, in order; empty when the message is held back or hidden. */
    public function lines(string $message): array
    {
        if (str_starts_with($message, 'Progress:')) {
            if ($this->progressLines) {
                return [$message];
            }
            // Planning has no other progress line, so terminals get a readable one.
            if (preg_match('/^Progress: plan ([0-9]+)\/([0-9]+)$/D', $message, $plan) !== 1) {
                return [];
            }
            $message = 'Checking records (' . $plan[1] . '/' . $plan[2] . ').';
        }
        $now = ($this->clock)();
        if (!$this->verbose && str_starts_with($message, 'Read model: ')) {
            if ($this->lastPrinted !== null && $now - $this->lastPrinted < self::QUIET_SECONDS) {
                return [];
            }
            return $this->emit([$message], $now);
        }
        $counter = preg_match('/^[A-Z][a-z]+ [0-9]+ [a-z ]+\.\.\.$/D', $message) === 1
            || preg_match('/\(([0-9]+)\/([0-9]+)\)\.?$/D', $message) === 1;
        $shape = $counter ? preg_replace('/[0-9]+/', '#', $message) : null;
        if ($shape !== null && $shape === $this->lastShape && $now - $this->lastCounterPrinted < 1.0) {
            $this->heldCounter = $message;
            return [];
        }
        $lines = $this->heldCounter !== null && $shape !== $this->lastShape ? [$this->heldCounter] : [];
        $this->heldCounter = null;
        $this->lastShape = $shape;
        $this->lastCounterPrinted = $now;
        $lines[] = $message;
        return $this->emit($lines, $now);
    }

    /** @return list<string> */
    private function emit(array $lines, float $now): array
    {
        $this->lastPrinted = $now;
        return $lines;
    }

    /**
     * One line per state and reason, largest first. Excluded files are already summarized by
     * the "Excluded:" lines. The missing record's ID is per-file detail; --verbose and
     * report.json keep it.
     *
     * @param list<array{state:string,reason:string}> $causes
     * @return list<string>
     */
    public static function groupedReviewCauses(array $causes): array
    {
        $groups = [];
        foreach ($causes as $cause) {
            if ($cause['state'] === 'excluded') {
                continue;
            }
            $key = ucfirst($cause['state']) . ': ' . preg_replace('/^(Missing dependency): .*$/D', '$1', rtrim((string) $cause['reason'], '.'));
            $groups[$key] = ($groups[$key] ?? 0) + 1;
        }
        uksort($groups, static fn (string $a, string $b): int => [$groups[$b], $a] <=> [$groups[$a], $b]);
        $lines = [];
        foreach ($groups as $key => $count) {
            $lines[] = $key . ' (' . $count . ' ' . ($count === 1 ? 'file' : 'files') . ')';
        }
        return $lines;
    }
}
