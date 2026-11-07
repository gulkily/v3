<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * A line `theme: <name>` in a thread's opening post or any reply sets that thread's
 * page theme; `theme: none` clears it. The line stays in the body as written; this
 * class only reads it. Scanning follows the event and title directives: quoted lines
 * are skipped and the first matching line in a post wins. Who may set a theme, and
 * which names count, is decided by ThreadTheme.
 */
final class ThemeDirective
{
    public const NONE = 'none';

    public static function extract(string $body): ?string
    {
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (preg_match('/^\s*>/', $line) === 1) {
                continue;
            }
            if (preg_match('/^\s*theme:\s*([a-z0-9][a-z0-9_-]{0,39})\s*$/i', $line, $match) === 1) {
                return strtolower($match[1]);
            }
        }

        return null;
    }
}
