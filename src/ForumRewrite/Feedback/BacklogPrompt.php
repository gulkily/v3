<?php

declare(strict_types=1);

namespace ForumRewrite\Feedback;

/**
 * The short prompt that starts an agent on one or several backlog items.
 * It names repository files to read, asks for a clone and a pull request, and
 * leaves the rest to the procedure, docs/agents/backlog_to_pr.md.
 */
final class BacklogPrompt
{
    /** The first mode is the default: the agent chooses one-shot or staged. */
    public const MODES = ['auto', 'one-shot', 'staged'];
    public const PROCEDURE = 'docs/agents/backlog_to_pr.md';
    /** Agents in a chat often start with patch files; this asks for the clone and the pull request up front. */
    public const ACCESS = "Work in a clone of the repository and open the pull request yourself. Please don't give me patch files or pasted code. If you can't clone or push, stop before writing code and tell me what to connect.";
    /** Added when several items are picked. */
    public const TOGETHER = "If the items don't belong in one pull request, say so before you build.";

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
     * @param string|null $claimsAddress where a site with a claims thread answers who holds these items;
     *     null in a checkout and on a site with no claims thread, which adds no line
     */
    public static function build(array $items, string $mode, ?string $repositoryUrl, ?string $claimsAddress = null): string
    {
        $count = count($items);
        $mode = self::mode($mode);
        $what = $count === 1 ? 'this v3 backlog item' : 'these ' . $count . ' v3 backlog items together';
        $cycle = match ($mode) {
            'staged' => 'one staged Railspike cycle',
            'one-shot' => 'one Railspike one-shot cycle',
            default => 'one Railspike cycle, one-shot or staged as AGENTS.md says',
        };
        $lines = ['Please work on ' . $what . ' as ' . $cycle . '.', ''];
        $where = [
            ...($repositoryUrl !== null ? ['Repository: ' . $repositoryUrl] : []),
            ...($claimsAddress !== null ? ['Claims: ' . $claimsAddress] : []),
        ];
        if ($where !== []) {
            array_push($lines, ...$where, ...['']);
        }
        // Above "Read first": reading the named files already needs the clone.
        array_push($lines, self::ACCESS, '');
        $lines[] = 'Read first:';
        $lines[] = '- ' . self::PROCEDURE;
        foreach ($items as $item) {
            $lines[] = '- docs/feedback/items/' . $item->id() . '.txt';
        }
        $lines[] = '';
        $together = $count === 1 ? '' : ' ' . self::TOGETHER;
        $lines[] = match ($mode) {
            'staged' => 'Begin now with Step 1 and wait for my approval of each step.' . $together . ' After Step 3, go all the way to an open pull request, or as close as you can get.',
            'one-shot' => 'Begin now.' . $together . ' Go all the way to an open pull request, or as close as you can get.',
            default => 'Begin now.' . $together . ' If you go staged, wait for my approval of each step. Go all the way to an open pull request, or as close as you can get.',
        };
        return implode("\n", $lines) . "\n";
    }
}
