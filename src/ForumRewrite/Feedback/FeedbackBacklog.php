<?php

declare(strict_types=1);

namespace ForumRewrite\Feedback;

/**
 * Filters and orders open feedback items for the backlog page, /backlog.txt
 * and ./v3 feedback list, so all three give the same list for the same words.
 */
final class FeedbackBacklog
{
    public const SORTS = ['salient', 'entered', 'touched', 'effort', 'impact'];
    public const ORDERS = ['newest', 'oldest'];
    public const KINDS = ['all', 'bug', 'feature', 'other'];
    public const OPEN_WORKFLOWS = ['untriaged', 'ready', 'planned', 'in-progress', 'blocked'];
    /** Filter word => the workflows it shows. */
    public const SHOWS = [
        'can-start' => ['ready', 'untriaged'],
        'waiting' => ['blocked'],
        'open' => self::OPEN_WORKFLOWS,
        'untriaged' => ['untriaged'],
        'ready' => ['ready'],
        'planned' => ['planned'],
        'in-progress' => ['in-progress'],
        'blocked' => ['blocked'],
    ];
    public const DEFAULTS = ['sort' => 'salient', 'order' => 'newest', 'show' => 'can-start', 'kind' => 'all'];

    /** A missing Estimate or Impact counts as the middle of the scale in the salient order. */
    private const MIDDLE = 0.5;
    private const SMALLEST_EFFORT = 0.01;

    /**
     * Reads sort, order, show and kind from a query; an unknown word falls back to its default.
     *
     * @param array<string, mixed> $query
     * @return array{sort: string, order: string, show: string, kind: string, unknown: list<string>}
     */
    public static function words(array $query): array
    {
        $allowed = ['sort' => self::SORTS, 'order' => self::ORDERS, 'show' => array_keys(self::SHOWS), 'kind' => self::KINDS];
        $words = self::DEFAULTS;
        $unknown = [];
        foreach ($allowed as $name => $values) {
            $value = $query[$name] ?? null;
            if (!is_string($value) || $value === '') {
                continue;
            }
            if (in_array($value, $values, true)) {
                $words[$name] = $value;
            } else {
                $unknown[] = $name . '=' . $value;
            }
        }
        $words['unknown'] = $unknown;
        return $words;
    }

    /**
     * @param list<FeedbackItem> $items
     * @return list<FeedbackItem>
     */
    public static function select(array $items, string $show, string $kind): array
    {
        $workflows = self::SHOWS[$show] ?? self::SHOWS[self::DEFAULTS['show']];
        return array_values(array_filter($items, static fn (FeedbackItem $item): bool =>
            in_array($item->header('Workflow'), $workflows, true)
            && ($kind === 'all' || !in_array($kind, self::KINDS, true) || $item->header('Kind') === $kind)));
    }

    /**
     * entered and touched go by that date. effort lists the lowest effort first and
     * impact the highest impact first, with items that have no value after those that
     * do. salient lists the highest impact for the least effort first. In every sort,
     * $order decides ties by touched date (entered: created date), then ID.
     *
     * @param list<FeedbackItem> $items
     * @return list<FeedbackItem>
     */
    public static function sort(array $items, string $sort, string $order): array
    {
        $direction = $order === 'oldest' ? 1 : -1;
        $byDate = static function (FeedbackItem $a, FeedbackItem $b, bool $created) use ($direction): int {
            $left = $created ? $a->header('Created') : $a->touched();
            $right = $created ? $b->header('Created') : $b->touched();
            return ($direction * strcmp($left, $right)) ?: strcmp($a->id(), $b->id());
        };
        usort($items, static function (FeedbackItem $a, FeedbackItem $b) use ($sort, $byDate): int {
            if ($sort === 'entered') {
                return $byDate($a, $b, true);
            }
            if ($sort === 'effort' || $sort === 'impact') {
                $header = $sort === 'effort' ? 'Estimate' : 'Impact';
                $left = $a->number($header);
                $right = $b->number($header);
                if (($left === null) !== ($right === null)) {
                    return $left === null ? 1 : -1;
                }
                if ($left !== null && $left !== $right) {
                    return $sort === 'effort' ? $left <=> $right : $right <=> $left;
                }
            }
            if ($sort === 'salient') {
                $rank = self::salience($b) <=> self::salience($a);
                if ($rank !== 0) {
                    return $rank;
                }
            }
            return $byDate($a, $b, false);
        });
        return $items;
    }

    /**
     * Impact divided by effort; a missing value counts as the middle of the scale.
     */
    public static function salience(FeedbackItem $item): float
    {
        $impact = $item->number('Impact') ?? self::MIDDLE;
        $effort = max($item->number('Estimate') ?? self::MIDDLE, self::SMALLEST_EFFORT);
        return round($impact / $effort, 4);
    }
}
