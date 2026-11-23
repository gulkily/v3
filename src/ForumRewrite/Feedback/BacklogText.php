<?php

declare(strict_types=1);

namespace ForumRewrite\Feedback;

/**
 * The backlog as Key: value text, for ./v3 feedback list and /backlog.txt.
 */
final class BacklogText
{
    /**
     * @param list<FeedbackItem> $items already selected and sorted
     * @param array{sort: string, order: string, show: string, kind: string} $words
     */
    public static function render(array $items, array $words, string $version): string
    {
        $blocks = [implode("\n", [
            'Sort: ' . $words['sort'],
            'Order: ' . $words['order'],
            'Show: ' . $words['show'],
            'Kind: ' . $words['kind'],
            'Count: ' . count($items),
            'Version: ' . substr($version, 0, 12),
            'Start-Address: /backlog/start.txt?items=<ID>,<ID>&mode=one-shot',
            'Start-Command: ./v3 feedback start <ID>...',
        ])];
        foreach ($items as $item) {
            $lines = [
                'ID: ' . $item->id(),
                'Title: ' . $item->header('Title'),
                'Kind: ' . $item->header('Kind'),
                'Workflow: ' . $item->header('Workflow'),
                'Created: ' . $item->header('Created'),
                'Touched: ' . $item->touched(),
            ];
            foreach (['Estimate', 'Impact'] as $header) {
                if ($item->number($header) !== null) {
                    $lines[] = $header . ': ' . $item->header($header);
                }
            }
            $lines[] = 'File: docs/feedback/items/' . $item->id() . '.txt';
            $blocks[] = implode("\n", $lines);
        }
        return implode("\n\n", $blocks) . "\n";
    }
}
