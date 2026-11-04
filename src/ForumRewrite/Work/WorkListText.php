<?php

declare(strict_types=1);

namespace ForumRewrite\Work;

/**
 * Plain-text views of work items. `/work.txt` answers "is anyone on this?" with one
 * tab-separated line per item, in thread-ID order.
 */
final class WorkListText
{
    public const TEXT_HEADER = "# status\tthread\ttitle\tby\tnote\tpath";

    /** @param list<array<string, mixed>> $items */
    public static function text(array $items): string
    {
        $lines = [self::TEXT_HEADER];
        foreach ($items as $item) {
            $lines[] = implode("\t", [
                self::statusLabel($item),
                $item['thread_id'],
                self::cell($item['title']),
                self::cell($item['set_by']),
                self::cell($item['note']),
                '/threads/' . $item['thread_id'],
            ]);
        }
        if ($items === []) {
            $lines[] = '# no matching work items';
        }

        return implode("\n", $lines) . "\n";
    }

    private static function statusLabel(array $item): string
    {
        return $item['status'] . ($item['stale'] ? ' (stale)' : '');
    }

    private static function cell(string $value): string
    {
        return trim(preg_replace('/[\t\r\n]+/', ' ', $value) ?? '');
    }
}
