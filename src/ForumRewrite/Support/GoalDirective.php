<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * A line `goal: 1000 coffees` in a thread's opening post makes the thread a pledge
 * campaign: a number, then the unit as free text. The line stays in the body as
 * written; this class only reads it. Scanning follows the theme and title directives:
 * quoted lines are skipped and the first line that reads as a goal wins.
 */
final class GoalDirective
{
    public const MAX_UNIT_LENGTH = 60;

    /** @return array{amount: int, written: string, unit: string}|null amount in hundredths */
    public static function extract(string $body): ?array
    {
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (preg_match('/^\s*>/', $line) === 1) {
                continue;
            }
            if (preg_match('/^\s*goal:\s*(\S+)(?:\s+(.*?))?\s*$/i', $line, $match) !== 1) {
                continue;
            }
            $amount = PledgeAmount::parse($match[1]);
            $unit = $match[2] ?? '';
            if ($amount === null || strlen($unit) > self::MAX_UNIT_LENGTH) {
                continue;
            }

            return ['amount' => $amount, 'written' => $match[1], 'unit' => $unit];
        }

        return null;
    }
}
