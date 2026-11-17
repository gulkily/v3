<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

/**
 * Turns an import task's `Progress: <step> [done/total]` lines into the step list, liveness
 * and time-left estimate shown on Tools → Import.
 *
 * Time left is only given where there is a basis: the measured rate of the current step
 * (download bytes, installed files), or the step durations of the previous finished import.
 * Without either it is null, and the page says "estimating".
 */
final class ImportProgressView
{
    public const STEPS = [
        'download' => 'Downloading',
        'extract' => 'Checking the archive',
        'plan' => 'Planning',
        'install' => 'Installing records',
        'commit' => 'Committing',
        'publish' => 'Refreshing views',
        'images' => 'Copying images',
    ];

    /** A rate needs this many seconds of the step before it is trusted. */
    private const MIN_RATE_SECONDS = 2;

    /**
     * @param list<array{message: string, created_at: string}> $events oldest first
     * @param array<string, float> $previous step => seconds from the previous finished import
     * @return array{
     *   steps: list<array{key: string, label: string, status: string, done: ?int, total: ?int}>,
     *   current: ?string, finished: bool, started_at: ?int, last_update_at: ?int,
     *   step_seconds_left: ?int, seconds_left: ?int
     * }
     */
    public static function build(array $events, int $now, array $previous = []): array
    {
        $started = [];
        $counts = [];
        $finished = false;
        $firstAt = null;
        $lastAt = null;
        foreach ($events as $event) {
            $at = strtotime((string) $event['created_at']) ?: null;
            if ($at !== null) {
                $firstAt ??= $at;
                $lastAt = $at;
            }
            if (preg_match('/^Progress: ([a-z]+)(?: (\d+)\/(\d+|\?))?$/', (string) $event['message'], $match) !== 1) {
                continue;
            }
            $step = $match[1];
            if ($step === 'done') {
                $finished = true;
                continue;
            }
            if (!isset(self::STEPS[$step])) {
                continue;
            }
            $started[$step] ??= $at ?? $now;
            if (isset($match[2]) && $match[2] !== '') {
                $counts[$step] = [(int) $match[2], $match[3] === '?' ? null : (int) $match[3]];
            }
        }
        $current = $finished || $started === [] ? null : array_key_last($started);
        $steps = [];
        $seen = false;
        foreach (self::STEPS as $key => $label) {
            $isStarted = isset($started[$key]);
            // A resumed import skips download, extract and plan; leave those out once later steps run.
            if (!$isStarted && $finished) {
                continue;
            }
            if (!$isStarted && $current !== null && array_search($key, array_keys(self::STEPS), true) < array_search($current, array_keys(self::STEPS), true)) {
                continue;
            }
            $status = match (true) {
                $finished && $isStarted => 'done',
                $key === $current => 'current',
                $isStarted => 'done',
                default => 'pending',
            };
            $seen = $seen || $isStarted;
            $steps[] = ['key' => $key, 'label' => $label, 'status' => $status, 'done' => $counts[$key][0] ?? null, 'total' => $counts[$key][1] ?? null];
        }
        if (!$seen && !$finished) {
            $steps = [];
        }

        $stepLeft = null;
        if ($current !== null) {
            $elapsed = $now - $started[$current];
            [$done, $total] = $counts[$current] ?? [null, null];
            if ($done !== null && $total !== null && $done > 0 && $elapsed >= self::MIN_RATE_SECONDS) {
                $stepLeft = (int) ceil($elapsed / $done * max(0, $total - $done));
            } elseif (isset($previous[$current])) {
                $stepLeft = (int) max(0, ceil($previous[$current] - $elapsed));
            }
        }
        $totalLeft = null;
        if ($current !== null && $stepLeft !== null && $previous !== []) {
            $totalLeft = $stepLeft;
            $after = false;
            foreach (array_keys(self::STEPS) as $key) {
                if ($after) {
                    $totalLeft += (int) ceil($previous[$key] ?? 0);
                }
                $after = $after || $key === $current;
            }
        }

        return [
            'steps' => $steps,
            'current' => $current,
            'finished' => $finished,
            'started_at' => $firstAt,
            'last_update_at' => $lastAt,
            'step_seconds_left' => $stepLeft,
            'seconds_left' => $totalLeft,
        ];
    }

    /**
     * Step durations of a finished run, from the times each step started.
     *
     * @param array<string, float> $stepStarts step => start time; 'done' marks the end
     * @return array<string, float>
     */
    public static function durations(array $stepStarts): array
    {
        $keys = array_keys($stepStarts);
        $durations = [];
        foreach ($keys as $index => $key) {
            if ($key === 'done' || !isset(self::STEPS[$key]) || !isset($keys[$index + 1])) {
                continue;
            }
            $durations[$key] = round(max(0.0, $stepStarts[$keys[$index + 1]] - $stepStarts[$key]), 1);
        }

        return $durations;
    }

    /** @param array<string, float> $durations */
    public static function encodeDurations(array $durations): string
    {
        return implode(' ', array_map(static fn (string $key, float $seconds): string => $key . '=' . $seconds, array_keys($durations), $durations));
    }

    /** @return array<string, float> */
    public static function decodeDurations(string $text): array
    {
        $durations = [];
        foreach (preg_split('/\s+/', trim($text)) ?: [] as $pair) {
            if (preg_match('/^([a-z]+)=(\d+(?:\.\d+)?)$/', $pair, $match) === 1 && isset(self::STEPS[$match[1]])) {
                $durations[$match[1]] = (float) $match[2];
            }
        }

        return $durations;
    }
}
