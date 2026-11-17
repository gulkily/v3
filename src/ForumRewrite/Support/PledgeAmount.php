<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * The number on a `goal:` or `pledge:` line: digits with an optional decimal point and
 * at most two decimal places, above zero. Commas count only as thousands grouping
 * (`1,000`, not `1,5`). Amounts are held as whole hundredths so sums stay exact.
 */
final class PledgeAmount
{
    /** @return int|null hundredths, or null when the text is not a countable number */
    public static function parse(string $text): ?int
    {
        if (preg_match('/^(\d{1,12}|\d{1,3}(?:,\d{3}){1,3})(?:\.(\d{1,2}))?$/', trim($text), $match) !== 1) {
            return null;
        }
        $hundredths = (int) str_replace(',', '', $match[1]) * 100 + (int) str_pad($match[2] ?? '', 2, '0');

        return $hundredths > 0 ? $hundredths : null;
    }

    /** Plain digits without trailing zeros: 31250 reads "312.5", 30000 reads "300". */
    public static function format(int $hundredths): string
    {
        $fraction = rtrim(str_pad((string) ($hundredths % 100), 2, '0', STR_PAD_LEFT), '0');

        return intdiv($hundredths, 100) . ($fraction === '' ? '' : '.' . $fraction);
    }
}
