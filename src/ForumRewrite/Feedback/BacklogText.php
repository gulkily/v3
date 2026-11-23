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
     * @param array<string, array<string, mixed>>|null $claims BacklogClaims::fromPosts(), or null on a site with no claims thread, which prints exactly what it printed before claims existed
     */
    public static function render(array $items, array $words, string $version, ?array $claims = null): string
    {
        $blocks = [implode("\n", [
            'Sort: ' . $words['sort'],
            'Order: ' . $words['order'],
            'Show: ' . $words['show'],
            'Kind: ' . $words['kind'],
            'Count: ' . count($items),
            'Version: ' . substr($version, 0, 12),
            'Start-Address: /backlog/start.txt?items=<ID>,<ID>',
            'Start-Command: ./v3 feedback start <ID>...',
            ...($claims === null ? [] : [
                'Claimed: ' . ($words['claimed'] ?? 'show'),
                'Claims-Address: /backlog/claims.txt?items=<ID>,<ID>',
            ]),
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
            array_push($lines, ...self::claimLines($claims[$item->id()] ?? null));
            $blocks[] = implode("\n", $lines);
        }
        return implode("\n\n", $blocks) . "\n";
    }

    /**
     * One item's claims as Key: value lines. Claim-State is `held` (a counted claim
     * no older than the stale period: stop), `stale` or `none` (only guest or
     * waiting claims: look for the branch or pull request before deciding).
     *
     * @param array<string, mixed>|null $entry one item's entry from BacklogClaims::fromPosts()
     * @return list<string>
     */
    public static function claimLines(?array $entry): array
    {
        if ($entry === null) {
            return [];
        }
        $lines = ['Claim-State: ' . (BacklogClaims::isHeld($entry) ? 'held' : ($entry['holder'] !== null ? 'stale' : 'none'))];
        if ($entry['holder'] !== null) {
            $lines[] = 'Claimed-By: ' . $entry['holder']['name'];
            $lines[] = 'Claimed-Since: ' . $entry['holder']['since'];
            $lines[] = 'Claim-Post: ' . $entry['holder']['post_id'];
        }
        if ($entry['also'] !== []) {
            $lines[] = 'Also-Claimed-By: ' . implode(', ', array_column($entry['also'], 'name'));
        }
        if ($entry['waiting'] !== []) {
            $lines[] = 'Claims-Waiting-For-Approval: ' . implode(', ', array_column($entry['waiting'], 'name'));
        }
        if ($entry['guests'] !== []) {
            $lines[] = 'Guest-Claims: ' . count($entry['guests']);
        }

        return $lines;
    }
}
