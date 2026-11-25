<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

final class EventTimeDirective
{
    /** @return array{event_time:string, event_end_time:?string}|null */
    public static function extract(string $body): ?array
    {
        $time = '(?:[01][0-9]|2[0-3]):[0-5][0-9]';
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (preg_match('/^\s*event-time:\s*(' . $time . ')(?:\s*-\s*(' . $time . '))?\s*$/i', $line, $match) === 1) {
                return ['event_time' => $match[1], 'event_end_time' => $match[2] ?? null];
            }
        }

        return null;
    }
}
