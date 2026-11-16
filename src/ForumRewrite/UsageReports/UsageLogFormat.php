<?php

declare(strict_types=1);

namespace ForumRewrite\UsageReports;

/**
 * The text public/assets/usage_log.js produces: a `usage-log v1` header, then
 * one tab-separated line per entry:
 *   view    <dt> <page>
 *   click   <dt> <page> <region> <name> <destination page | - | external>
 *   submit  <dt> <page> <region> <name>
 * Uploads must match exactly, so the store only holds logs in this shape.
 */
final class UsageLogFormat
{
    public const HEADER = 'usage-log v1';
    private const PAGE = '/(?:[a-z*-]+(?:/[a-z*-]+)*)?';
    private const REGION = '[a-z-]{1,20}';
    private const NAME = '[^\t\r\n]{1,60}';

    public static function isValid(string $text): bool
    {
        if (!mb_check_encoding($text, 'UTF-8')) {
            return false;
        }
        $lines = explode("\n", rtrim($text, "\n"));
        if (array_shift($lines) !== self::HEADER) {
            return false;
        }
        foreach ($lines as $line) {
            if (!self::lineIsValid($line)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return list<array{type:string, dt:int, page:string, region:?string, name:?string, to:?string}>
     */
    public static function entries(string $text): array
    {
        $entries = [];
        foreach (array_slice(explode("\n", rtrim($text, "\n")), 1) as $line) {
            if (!self::lineIsValid($line)) {
                continue;
            }
            $fields = explode("\t", $line);
            $entries[] = [
                'type' => $fields[0],
                'dt' => (int) $fields[1],
                'page' => $fields[2],
                'region' => $fields[3] ?? null,
                'name' => $fields[4] ?? null,
                'to' => $fields[5] ?? null,
            ];
        }

        return $entries;
    }

    private static function lineIsValid(string $line): bool
    {
        $page = self::PAGE;
        $region = self::REGION;
        $name = self::NAME;

        return preg_match("#^view\t\\d{1,9}\t{$page}$#", $line) === 1
            || preg_match("#^click\t\\d{1,9}\t{$page}\t{$region}\t{$name}\t(?:{$page}|-|external)$#u", $line) === 1
            || preg_match("#^submit\t\\d{1,9}\t{$page}\t{$region}\t{$name}$#u", $line) === 1;
    }
}
