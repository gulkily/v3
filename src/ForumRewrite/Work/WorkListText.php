<?php

declare(strict_types=1);

namespace ForumRewrite\Work;

/**
 * Plain-text views of work items. `/work.txt` answers "is anyone on this?" with one
 * tab-separated line per item; `/work.md` is the generated Markdown export that
 * `./v3 work export` commits to the repository. Both keep thread-ID order.
 */
final class WorkListText
{
    public const TEXT_HEADER = "# status\tthread\ttitle\tby\tnote\tpath\tsource";

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
                self::cell((string) ($item['source'] ?? '')),
            ]);
        }
        if ($items === []) {
            $lines[] = '# no matching work items';
        }

        return implode("\n", $lines) . "\n";
    }

    /** @param list<array<string, mixed>> $items */
    public static function markdown(array $items, string $origin): string
    {
        $origin = rtrim($origin, '/');
        $lines = [
            '# Work items',
            '',
            '_Generated from ' . $origin . '/work.md. Do not edit; change status on each item\'s thread._',
            '',
        ];
        foreach ($items as $item) {
            $line = '- [' . self::statusLabel($item) . '] [' . self::markdownText($item['title']) . '](' . $origin . '/threads/' . $item['thread_id'] . ')';
            if ($item['set_by'] !== '') {
                $line .= ' · ' . self::markdownText($item['set_by']);
            }
            if ($item['note'] !== '') {
                $line .= ' · ' . self::markdownText($item['note']);
            }
            $lines[] = $line;
        }
        if ($items === []) {
            $lines[] = '_No work items yet._';
        }

        return implode("\n", $lines) . "\n";
    }

    /** @param list<array{thread_id: string, title: string}> $requests */
    public static function requests(array $requests): string
    {
        $lines = ["# request\tthread\ttitle\tpath"];
        foreach ($requests as $request) {
            $lines[] = implode("\t", ['request', $request['thread_id'], self::cell($request['title']), '/threads/' . $request['thread_id']]);
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

    private static function markdownText(string $value): string
    {
        return self::cell(str_replace(['\\', '[', ']', '<', '>'], ['\\\\', '\\[', '\\]', '&lt;', '&gt;'], $value));
    }
}
