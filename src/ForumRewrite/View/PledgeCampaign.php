<?php

declare(strict_types=1);

namespace ForumRewrite\View;

use ForumRewrite\Support\FilledDirective;
use ForumRewrite\Support\GoalDirective;
use ForumRewrite\Support\PledgeAmount;
use ForumRewrite\Support\PledgeDirective;
use ForumRewrite\Support\PledgeLines;
use ForumRewrite\Support\ReviewDirective;
use ForumRewrite\Support\WithdrawDirective;

/**
 * A thread's pledge campaign, derived when read from the posts the thread page already
 * loads: the `goal:` line in the opening post, and the `pledge:` line in each reply.
 * Nothing is stored, so a rebuild cannot disagree with the posts.
 *
 * A pledge counts when its post is unretracted, comes from an approved key with counted
 * attribution, and reads as a number, optionally followed by the campaign's unit. Approval is
 * read when the thread is shown, so a pledge starts counting once its key is approved. A
 * signed pledge from a key not yet approved waits ("Waiting for approval"); a post with no
 * name can never count. Fills, withdrawals and reviews need an approved key too. Every
 * other pledge line stays visible with the reason it is not counted.
 *
 * A counted pledge is withdrawn by a reply to it that carries a `withdraw` line, has
 * counted attribution and has the pledge's author (the same key). The pledge stays
 * visible and leaves the sum and the count. Retracting that reply undoes it, because a
 * retracted post is not read. A withdraw line that does not take effect gets a reason.
 *
 * A counted pledge is filled by a reply to it that carries a `filled` line, has counted
 * attribution and a name, and comes from the pledger, the campaign author or a key the
 * operator check accepts now. `filled` fills what is left; `filled: 200` fills 200, capped
 * at what is left. Fills are read before withdrawals, so reply order does not change the
 * result: a pledge with nothing left cannot be withdrawn, and a withdrawn pledge counts in
 * "pledged" as what was filled on it. "Confirmed" is the part filled by the author or an
 * operator.
 */
final class PledgeCampaign
{
    public const COUNTED = 'counted';
    public const WAITING = 'waiting';
    public const UNNAMED = 'unnamed';
    public const UNREADABLE = 'unreadable';
    public const WITHDRAWN = 'withdrawn';

    public const STATUS_OPEN = 'open';
    public const STATUS_GOAL_REACHED = 'goal_reached';
    public const STATUS_COMPLETE = 'complete';

    /**
     * @param array<int, array<string, mixed>> $posts thread rows, root included
     * @param (callable(string): bool)|null $isOperator whether a key is an operator now; null means none is
     * @return array{
     *     author: string, goal: string, unit: string, pledged: string, count: int, fill_percent: float, goal_percent: ?float,
     *     delivered_percent: float, status: string,
     *     pledges: array<string, array{state: string, amount: ?string, note: string, withdrawn_by?: string, withdrawn_at?: string}>,
     *     withdrawals: array<string, array{effective: bool, note: string}>,
     *     filled: string, confirmed: string,
     *     fills: array<string, array{effective: bool, note: string}>,
     *     reviews: array<string, array{effective: bool, note: string}>,
     *     used_lines: array<string, string>
     * }|null null when the thread is not a campaign; `withdrawals`, `fills` and `reviews` are keyed by the reply's post ID;
     *     `used_lines` maps a post ID to the kind of pledge line its card or the totals block already shows.
     *     A pledge with a fill that counts also carries `filled` and `fill_list` (post_id, by, role, amount). A pledger's
     *     fill that was confirmed or disputed also carries `review`, `review_by` and `review_post_id`; a disputed one is
     *     listed but not counted.
     */
    public static function fromPosts(array $posts, string $rootPostId, ?callable $isOperator = null): ?array
    {
        $goal = null;
        $campaignAuthor = '';
        foreach ($posts as $post) {
            if ((string) ($post['post_id'] ?? '') === $rootPostId) {
                $goal = ($post['retracted_at'] ?? null) === null ? GoalDirective::extract((string) ($post['body'] ?? '')) : null;
                $campaignAuthor = (string) ($post['author_identity_id'] ?? '');
                break;
            }
        }
        if ($goal === null) {
            return null;
        }

        $unit = $goal['unit'];
        $sum = 0;
        $count = 0;
        $pledges = [];
        $counted = [];
        foreach ($posts as $post) {
            $postId = (string) ($post['post_id'] ?? '');
            if ($postId === $rootPostId || ($post['retracted_at'] ?? null) !== null || !str_contains((string) ($post['body'] ?? ''), ':')) {
                continue;
            }
            $written = PledgeDirective::extract((string) $post['body']);
            if ($written === null) {
                continue;
            }
            $amount = self::amount($written, $unit);
            if ($amount === null) {
                $pledges[$postId] = ['state' => self::UNREADABLE, 'amount' => null, 'note' => self::unreadableNote($written, $unit)];
                continue;
            }
            $state = match (true) {
                (string) ($post['author_identity_id'] ?? '') === '' => self::UNNAMED,
                !self::approved($post) => self::WAITING,
                default => self::COUNTED,
            };
            $pledges[$postId] = ['state' => $state, 'amount' => PledgeAmount::format($amount), 'note' => match ($state) {
                self::UNNAMED => 'Not counted: no name',
                self::WAITING => 'Waiting for approval',
                default => '',
            }];
            if ($state === self::COUNTED) {
                $counted[$postId] = ['amount' => $amount, 'author' => (string) ($post['author_identity_id'] ?? '')];
            }
        }

        if ($isOperator !== null) {
            $operatorCache = [];
            $check = $isOperator;
            $isOperator = static function (string $id) use (&$operatorCache, $check): bool {
                return $operatorCache[$id] ??= (bool) $check($id);
            };
        }
        [$reviews, $reviewed] = self::reviews($posts, $rootPostId, $counted, $campaignAuthor, $isOperator);
        [$fills, $filledOn, $filledSum, $confirmedSum] = self::fills($posts, $rootPostId, $unit, $pledges, $counted, $campaignAuthor, $isOperator, $reviewed);
        $withdrawals = self::withdrawals($posts, $rootPostId, $pledges, $counted, $filledOn);
        foreach ($counted as $postId => $pledge) {
            if ($pledges[$postId]['state'] === self::COUNTED) {
                $sum += $pledge['amount'];
                $count++;
            } else {
                // Withdrawn: only the unfilled part leaves the sum.
                $sum += $filledOn[$postId] ?? 0;
            }
        }

        $scale = max($goal['amount'], $sum);

        // The line each post's card or the totals block already shows, so the thread page can leave
        // it out of the text. A line that did not count stays, beside its reason.
        $usedLines = [$rootPostId => PledgeLines::GOAL];
        foreach ($pledges as $postId => $pledge) {
            if ($pledge['state'] !== self::UNREADABLE) {
                $usedLines[$postId] = PledgeLines::PLEDGE;
            }
        }
        foreach ([PledgeLines::WITHDRAW => $withdrawals, PledgeLines::FILLED => $fills, PledgeLines::REVIEW => $reviews] as $kind => $records) {
            foreach ($records as $postId => $record) {
                if ($record['effective']) {
                    $usedLines[$postId] = $kind;
                }
            }
        }

        return [
            'author' => $campaignAuthor,
            'goal' => PledgeAmount::format($goal['amount']),
            'unit' => $unit,
            'pledged' => PledgeAmount::format($sum),
            'count' => $count,
            'fill_percent' => round($sum / $scale * 100, 1),
            // The goal mark appears once pledges pass the goal; before that the bar's end is the goal.
            'goal_percent' => $sum > $goal['amount'] ? round($goal['amount'] / $scale * 100, 1) : null,
            // The delivered part of the bar, drawn over the pledged part.
            'delivered_percent' => round(min($filledSum, $scale) / $scale * 100, 1),
            // Derived each time the thread is read: complete once fills reach the goal,
            // goal_reached once pledges do, open before that.
            'status' => match (true) {
                $filledSum >= $goal['amount'] => self::STATUS_COMPLETE,
                $sum >= $goal['amount'] => self::STATUS_GOAL_REACHED,
                default => self::STATUS_OPEN,
            },
            'pledges' => $pledges,
            'withdrawals' => $withdrawals,
            'filled' => PledgeAmount::format($filledSum),
            'confirmed' => PledgeAmount::format($confirmedSum),
            'fills' => $fills,
            'reviews' => $reviews,
            'used_lines' => $usedLines,
        ];
    }

    /**
     * Reads each reply's `confirm` or `dispute` line. A review counts when it answers a pledger's
     * fill of a counted pledge and comes from the campaign author or an operator, other than the
     * fill's own author; the latest counting review on a fill decides. Read before fills, so a
     * disputed fill leaves room for later ones.
     *
     * @param array<int, array<string, mixed>> $posts
     * @param array<string, array{amount: int, author: string}> $counted
     * @param (callable(string): bool)|null $isOperator
     * @return array{0: array<string, array{effective: bool, note: string}>, 1: array<string, array{review: string, review_by: string, review_post_id: string}>}
     */
    private static function reviews(array $posts, string $rootPostId, array $counted, string $campaignAuthor, ?callable $isOperator): array
    {
        $pledgerFills = [];
        foreach ($posts as $post) {
            $pledgeId = (string) ($post['parent_id'] ?? '');
            $author = (string) ($post['author_identity_id'] ?? '');
            if (isset($counted[$pledgeId]) && $author !== '' && $author === $counted[$pledgeId]['author']
                && ($post['retracted_at'] ?? null) === null && (int) ($post['attribution_counted'] ?? 1) === 1
                && FilledDirective::extract((string) ($post['body'] ?? '')) !== null) {
                $pledgerFills[(string) $post['post_id']] = $author;
            }
        }

        $reviews = [];
        $reviewed = [];
        foreach ($posts as $post) {
            $postId = (string) ($post['post_id'] ?? '');
            $body = (string) ($post['body'] ?? '');
            if ($postId === $rootPostId || ($post['retracted_at'] ?? null) !== null
                || (stripos($body, 'confirm') === false && stripos($body, 'dispute') === false)) {
                continue;
            }
            $line = ReviewDirective::extract($body);
            if ($line === null) {
                continue;
            }
            $fillId = (string) ($post['parent_id'] ?? '');
            $author = (string) ($post['author_identity_id'] ?? '');
            $note = match (true) {
                !isset($pledgerFills[$fillId]) => "Not counted: not a reply to a pledger's fill",
                $line['after'] !== '' => 'Not counted: write “' . $line['word'] . '” alone on its line',
                $author === '' => 'Not counted: no name',
                !self::approved($post) => 'Not counted: waiting for approval',
                $author === $pledgerFills[$fillId] => 'Not counted: a fill cannot be reviewed by its own author',
                $author !== $campaignAuthor && ($isOperator === null || !$isOperator($author)) => 'Not counted: only the author or an operator can review',
                default => '',
            };
            $reviews[$postId] = ['effective' => $note === '', 'note' => $note];
            if ($note === '') {
                $reviewed[$fillId] = ['review' => $line['word'] === ReviewDirective::CONFIRM ? 'confirmed' : 'disputed',
                    'review_by' => (string) ($post['author_label'] ?? ''), 'review_post_id' => $postId];
            }
        }

        return [$reviews, $reviewed];
    }

    /**
     * Reads each reply's `filled` line in thread order. Fills on one pledge add up to its
     * amount; a fill past that counts up to what is left and says so.
     *
     * @param array<int, array<string, mixed>> $posts
     * @param array<string, array<string, mixed>> $pledges gains `filled` and `fill_list` in place
     * @param array<string, array{amount: int, author: string}> $counted
     * @param (callable(string): bool)|null $isOperator
     * @param array<string, array{review: string, review_by: string, review_post_id: string}> $reviewed from reviews()
     * @return array{0: array<string, array{effective: bool, note: string}>, 1: array<string, int>, 2: int, 3: int}
     */
    private static function fills(array $posts, string $rootPostId, string $unit, array &$pledges, array $counted, string $campaignAuthor, ?callable $isOperator, array $reviewed = []): array
    {
        $fills = [];
        $filledOn = [];
        $filledSum = 0;
        $confirmedSum = 0;
        $operators = [];
        foreach ($posts as $post) {
            $postId = (string) ($post['post_id'] ?? '');
            $body = (string) ($post['body'] ?? '');
            if ($postId === $rootPostId || ($post['retracted_at'] ?? null) !== null || stripos($body, 'filled') === false) {
                continue;
            }
            $after = FilledDirective::extract($body);
            if ($after === null) {
                continue;
            }
            $pledgeId = (string) ($post['parent_id'] ?? '');
            $author = (string) ($post['author_identity_id'] ?? '');
            $role = '';
            if (isset($counted[$pledgeId]) && $author !== '') {
                if ($author === $counted[$pledgeId]['author']) {
                    $role = 'pledger';
                } elseif ($author === $campaignAuthor) {
                    $role = 'author';
                } elseif ($isOperator !== null && ($operators[$author] ??= (bool) $isOperator($author))) {
                    $role = 'operator';
                }
            }
            $left = isset($counted[$pledgeId]) ? $counted[$pledgeId]['amount'] - ($filledOn[$pledgeId] ?? 0) : 0;
            $written = $after === '' ? $left : self::amount($after, $unit);
            $note = match (true) {
                !isset($counted[$pledgeId]) => 'Not counted: not a reply to a counted pledge',
                $author === '' => 'Not counted: no name',
                !self::approved($post) => 'Not counted: waiting for approval',
                $role === '' => 'Not counted: only the pledger, the author or an operator can fill',
                $written === null => self::unreadableNote($after, $unit),
                $left <= 0 => 'Not counted: already filled',
                default => '',
            };
            $review = $role === 'pledger' ? ($reviewed[$postId] ?? null) : null;
            if ($note === '' && ($review['review'] ?? '') === 'disputed') {
                // Listed with what it claimed, and takes no room on the pledge.
                $fills[$postId] = ['effective' => false, 'note' => 'Not counted: disputed by ' . ($review['review_by'] !== '' ? $review['review_by'] : 'unnamed')];
                $pledges[$pledgeId]['fill_list'][] = ['post_id' => $postId, 'by' => (string) ($post['author_label'] ?? ''),
                    'role' => $role, 'amount' => PledgeAmount::format($written)] + $review;
                continue;
            }
            if ($note !== '') {
                $fills[$postId] = ['effective' => false, 'note' => $note];
                continue;
            }
            $amount = min($written, $left);
            $fills[$postId] = ['effective' => true, 'note' => $amount < $written
                ? 'Counted ' . PledgeAmount::format($amount) . ' of ' . PledgeAmount::format($written) : ''];
            $filledOn[$pledgeId] = ($filledOn[$pledgeId] ?? 0) + $amount;
            $filledSum += $amount;
            if ($role !== 'pledger' || $review !== null) {
                $confirmedSum += $amount;
            }
            $pledges[$pledgeId]['filled'] = PledgeAmount::format($filledOn[$pledgeId]);
            $pledges[$pledgeId]['fill_list'][] = ['post_id' => $postId, 'by' => (string) ($post['author_label'] ?? ''),
                'role' => $role, 'amount' => PledgeAmount::format($amount)] + ($review ?? []);
        }

        return [$fills, $filledOn, $filledSum, $confirmedSum];
    }

    /**
     * Reads each reply's `withdraw` line in thread order and marks the pledge it answers.
     * The first withdrawal that takes effect marks the pledge; a later one changes nothing.
     *
     * @param array<int, array<string, mixed>> $posts
     * @param array<string, array<string, mixed>> $pledges marked in place
     * @param array<string, array{amount: int, author: string}> $counted pledges that count before withdrawals
     * @param array<string, int> $filledOn hundredths filled on each pledge
     * @return array<string, array{effective: bool, note: string}>
     */
    private static function withdrawals(array $posts, string $rootPostId, array &$pledges, array $counted, array $filledOn = []): array
    {
        $withdrawals = [];
        foreach ($posts as $post) {
            $postId = (string) ($post['post_id'] ?? '');
            $body = (string) ($post['body'] ?? '');
            if ($postId === $rootPostId || ($post['retracted_at'] ?? null) !== null || stripos($body, 'withdraw') === false) {
                continue;
            }
            $after = WithdrawDirective::extract($body);
            if ($after === null) {
                continue;
            }
            $pledgeId = (string) ($post['parent_id'] ?? '');
            $author = (string) ($post['author_identity_id'] ?? '');
            $note = match (true) {
                !isset($counted[$pledgeId]) => 'Not counted: not a reply to a pledge',
                $after !== '' => 'Not counted: write “withdraw” alone on its line',
                $author === '' => 'Not counted: no name',
                !self::approved($post) => 'Not counted: waiting for approval',
                $author !== $counted[$pledgeId]['author'] => 'Not counted: only the pledger can withdraw',
                ($filledOn[$pledgeId] ?? 0) >= $counted[$pledgeId]['amount'] => 'Not counted: already filled',
                default => '',
            };
            $withdrawals[$postId] = ['effective' => $note === '', 'note' => $note];
            if ($note === '' && $pledges[$pledgeId]['state'] === self::COUNTED) {
                $pledges[$pledgeId] = ['state' => self::WITHDRAWN, 'amount' => $pledges[$pledgeId]['amount'], 'note' => '',
                    'withdrawn_by' => $postId, 'withdrawn_at' => (string) ($post['created_at'] ?? '')]
                    + array_intersect_key($pledges[$pledgeId], ['filled' => true, 'fill_list' => true]);
            }
        }

        return $withdrawals;
    }

    /** A number, optionally followed by the campaign's unit; anything else is not counted. */
    private static function amount(string $written, string $unit): ?int
    {
        $parts = preg_split('/\s+/', $written, 2) ?: [];
        $amount = PledgeAmount::parse((string) ($parts[0] ?? ''));
        $trailing = trim((string) ($parts[1] ?? ''));
        if ($amount === null || ($trailing !== '' && self::unitKey($trailing) !== self::unitKey($unit))) {
            return null;
        }

        return $amount;
    }

    /** "Coffee" and "coffees" are the same unit. */
    private static function unitKey(string $unit): string
    {
        return rtrim(strtolower(trim($unit)), 's');
    }

    private static function unreadableNote(string $written, string $unit): string
    {
        $shown = mb_strlen($written) > 40 ? rtrim(mb_substr($written, 0, 39)) . '…' : $written;
        $first = (string) (preg_split('/\s+/', $written, 2)[0] ?? '');

        return PledgeAmount::parse($first) === null
            ? 'Not counted: “' . $shown . '” is not a number'
            : 'Not counted: “' . $shown . '” is not in ' . ($unit !== '' ? $unit : 'this campaign’s unit');
    }

    /** An approved key's post whose attribution counts: the only posts whose pledge lines count. */
    /**
     * Adds `pledge_campaign` to each board row whose opening post has a goal line: the same
     * result the thread page computes, from the same posts. Only those threads' posts are
     * loaded, so rows that are not campaigns cost nothing.
     *
     * @param array<int, array<string, mixed>> $rows board rows with `root_post_id` and `root_post_body`
     * @param callable(string): array<int, array<string, mixed>> $loadPosts the thread page's post loader
     * @param (callable(string): bool)|null $isOperator as for fromPosts()
     * @return array<int, array<string, mixed>>
     */
    public static function forRows(array $rows, callable $loadPosts, ?callable $isOperator = null): array
    {
        foreach ($rows as $index => $row) {
            if (($row['retracted_at'] ?? null) !== null || GoalDirective::extract((string) ($row['root_post_body'] ?? '')) === null) {
                continue;
            }
            $threadId = (string) $row['root_post_id'];
            $campaign = self::fromPosts($loadPosts($threadId), $threadId, $isOperator);
            if ($campaign !== null) {
                $rows[$index]['pledge_campaign'] = $campaign;
            }
        }

        return $rows;
    }

    private static function approved(array $post): bool
    {
        return (int) ($post['author_is_approved'] ?? 0) === 1 && (int) ($post['attribution_counted'] ?? 1) === 1;
    }

}
