<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * A line `event: confirmed` or `event: declined` in any post of an event thread, root
 * included, settles the event. The line stays in the body as written; this class only
 * reads it. Scanning follows the title directive: quoted lines are skipped and the first
 * matching line in a post wins. Who may settle an event is decided by EventStatus.
 */
final class EventStatusDirective
{
    public const CONFIRMED = 'confirmed';
    public const DECLINED = 'declined';

    public static function extract(string $body): ?string
    {
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (preg_match('/^\s*>/', $line) === 1) {
                continue;
            }
            if (preg_match('/^\s*event:\s*(confirmed|declined)\s*$/i', $line, $match) === 1) {
                return strtolower($match[1]);
            }
        }

        return null;
    }
}
