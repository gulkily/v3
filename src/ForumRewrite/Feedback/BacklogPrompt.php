<?php

declare(strict_types=1);

namespace ForumRewrite\Feedback;

/**
 * The short prompt that starts an agent on one or several backlog items.
 * It names repository files to read and nothing else; the procedure it points
 * at is docs/agents/backlog_to_pr.md.
 */
final class BacklogPrompt
{
    public const MODES = ['one-shot', 'staged'];
    public const PROCEDURE = 'docs/agents/backlog_to_pr.md';

    public static function mode(mixed $value): string
    {
        return is_string($value) && in_array($value, self::MODES, true) ? $value : self::MODES[0];
    }

    /**
     * Splits the requested IDs into open items, in request order, and IDs that
     * are unknown or closed.
     *
     * @param list<FeedbackItem> $items every loaded item
     * @param list<string> $ids
     * @return array{items: list<FeedbackItem>, unknown: list<string>, closed: list<string>}
     */
    public static function pick(array $items, array $ids): array
    {
        $byId = [];
        foreach ($items as $item) {
            $byId[$item->id()] = $item;
        }
        $picked = ['items' => [], 'unknown' => [], 'closed' => []];
        foreach (array_values(array_unique($ids)) as $id) {
            if (!isset($byId[$id])) {
                $picked['unknown'][] = $id;
            } elseif ($byId[$id]->isClosed()) {
                $picked['closed'][] = $id;
            } else {
                $picked['items'][] = $byId[$id];
            }
        }
        return $picked;
    }

    /**
     * IDs from "A,B" text or a list of such texts, keeping only ID-shaped words.
     *
     * @return list<string>
     */
    public static function ids(mixed $value): array
    {
        $ids = [];
        foreach (is_array($value) ? $value : [$value] as $part) {
            if (!is_string($part)) {
                continue;
            }
            foreach (preg_split('/[\s,]+/', $part, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $id) {
                if (preg_match('/^PF-\d{8}-[FBO]\d{2,}$/D', $id) === 1) {
                    $ids[] = $id;
                }
            }
        }
        return array_values(array_unique($ids));
    }

    /**
     * @param list<FeedbackItem> $items open items to start; at least one
     */
    public static function build(array $items, string $mode, ?string $repositoryUrl): string
    {
        $count = count($items);
        $what = $count === 1 ? 'this v3 backlog item' : 'these ' . $count . ' v3 backlog items together';
        $cycle = self::mode($mode) === 'staged' ? 'one staged Railspike cycle' : 'one Railspike one-shot cycle';
        $lines = ['Work on ' . $what . ' as ' . $cycle . '.', ''];
        if ($repositoryUrl !== null) {
            array_push($lines, 'Repository: ' . $repositoryUrl, '');
        }
        $lines[] = 'Read first:';
        $lines[] = '- ' . self::PROCEDURE;
        foreach ($items as $item) {
            $lines[] = '- docs/feedback/items/' . $item->id() . '.txt';
        }
        $lines[] = '';
        $lines[] = self::mode($mode) === 'staged'
            ? 'Begin now with Step 1 and wait for my approval of each step. After Step 3, go all the way to an open pull request, or as close as you can get.'
            : 'Begin now. Go all the way to an open pull request, or as close as you can get.';
        return implode("\n", $lines) . "\n";
    }
}
