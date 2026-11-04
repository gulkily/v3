<?php

declare(strict_types=1);

namespace ForumRewrite\Work;

/**
 * A line of the form `status: active` (optionally followed by a note, such as
 * `status: active - session abc on feat/x`) sets a work item's status. The line stays
 * in the post body as written; this class only reads it. Scanning follows the
 * `title:` directive rules: quoted lines are skipped and the first valid match wins.
 */
final class WorkStatusDirective
{
    public const STATUSES = ['open', 'active', 'done', 'dropped'];

    /** @return array{status: string, note: string}|null */
    public static function extract(string $body): ?array
    {
        foreach (preg_split('/\r\n|\r|\n/', $body) ?: [] as $line) {
            if (preg_match('/^\s*>/', $line) === 1) {
                continue;
            }
            if (preg_match('/^\s*status:\s*([A-Za-z]+)\b\s*(.*?)\s*$/i', $line, $match) !== 1) {
                continue;
            }
            $status = strtolower($match[1]);
            if (!in_array($status, self::STATUSES, true)) {
                continue;
            }
            $note = trim(preg_replace('/^[\s\-–—:·]+/u', '', $match[2]) ?? '');

            return ['status' => $status, 'note' => mb_substr($note, 0, 200)];
        }

        return null;
    }
}
