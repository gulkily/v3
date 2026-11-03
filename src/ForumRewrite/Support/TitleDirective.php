<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * A reply line of the form `title: New name` asks to retitle the thread. The line
 * stays in the reply body as written; this class only reads it. Scanning follows the
 * hashtag rules: quoted lines are skipped and the first match wins.
 */
final class TitleDirective
{
    public const REASON = 'Title directive in reply';

    public static function extract(string $body): ?string
    {
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (preg_match('/^\s*>/', $line) === 1) {
                continue;
            }
            if (preg_match('/^\s*title:\s*(.*?)\s*$/i', $line, $match) !== 1) {
                continue;
            }
            $value = $match[1];
            if ($value === '') {
                continue;
            }

            return $value;
        }

        return null;
    }
}
