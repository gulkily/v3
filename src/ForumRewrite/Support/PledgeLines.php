<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * The text of a post on a campaign's thread page without the pledge line the reader used:
 * the totals block or the pledge card shows what the line said, so the line itself is not
 * repeated. Only the thread page does this; the source, the `.txt` view, the Reply page and
 * Forte keep every line. Quoted lines are never touched, as the readers skip them too.
 */
final class PledgeLines
{
    public const GOAL = 'goal';
    public const PLEDGE = 'pledge';
    public const WITHDRAW = 'withdraw';
    public const FILLED = 'filled';
    public const REVIEW = 'review';

    /** The body with the first line the reader of $kind accepts removed, without the extra blank lines that leaves. */
    public static function hide(string $body, string $kind): string
    {
        $lines = preg_split('/(\r\n|\r|\n)/', $body) ?: [];
        foreach ($lines as $index => $line) {
            if (preg_match('/^\s*>/', $line) === 1 || !self::reads($line, $kind)) {
                continue;
            }
            unset($lines[$index]);
            // A line between two blank lines leaves one paragraph break, not two.
            $before = $lines[$index - 1] ?? '';
            if (isset($lines[$index + 1]) && trim($lines[$index + 1]) === '' && trim($before) === '') {
                unset($lines[$index + 1]);
            }
            break;
        }

        return (string) preg_replace('/^(?:[ \t]*(?:\r\n|\r|\n))+/', '', implode("\n", $lines));
    }

    /** Whether the body is that line and nothing else. */
    public static function isOnlyLine(string $body, string $kind): bool
    {
        return trim($body) !== '' && trim(self::hide($body, $kind)) === '';
    }

    private static function reads(string $line, string $kind): bool
    {
        return match ($kind) {
            self::GOAL => GoalDirective::extract($line) !== null,
            self::PLEDGE => PledgeDirective::extract($line) !== null,
            self::WITHDRAW => WithdrawDirective::extract($line) !== null,
            self::FILLED => FilledDirective::extract($line) !== null,
            self::REVIEW => ReviewDirective::extract($line) !== null,
            default => false,
        };
    }
}
