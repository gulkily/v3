<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Host\StaticArtifactBuilder;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FilledDirective;
use ForumRewrite\Support\GoalDirective;
use ForumRewrite\Support\PledgeAmount;
use ForumRewrite\Support\PledgeDirective;
use ForumRewrite\Support\PledgeLines;
use ForumRewrite\Support\ReviewDirective;
use ForumRewrite\Support\WithdrawDirective;
use ForumRewrite\View\PledgeCampaign;
use ForumRewrite\Write\LocalWriteService;

final class PledgeCampaignTest
{
    /** The fixture's committed root-approved operator. */
    private const MEMBER = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testAmountsAreWholeOrTwoPlaceDecimalsAboveZero(): void
    {
        foreach ([
            '300' => 30000, '1.5' => 150, '12.50' => 1250, '0.25' => 25, '1,000' => 100000,
            '12,500,000.5' => 1250000050, ' 7 ' => 700, '007' => 700,
        ] as $text => $hundredths) {
            pledgeAssertSame($hundredths, PledgeAmount::parse((string) $text));
        }
        foreach (['', '0', '0.00', '-5', '+5', '1,5', '1,00', '1.234', '1.', '.5', '1e3', '1/2', 'a few', '300 coffees', '1 000', '1234567890123'] as $text) {
            pledgeAssertSame(null, PledgeAmount::parse($text));
        }
        pledgeAssertSame('300', PledgeAmount::format(30000));
        pledgeAssertSame('312.5', PledgeAmount::format(31250));
        pledgeAssertSame('12.05', PledgeAmount::format(1205));
        pledgeAssertSame('0.25', PledgeAmount::format(25));
    }

    public function testGoalLineIsANumberThenAUnit(): void
    {
        pledgeAssertSame(['amount' => 100000, 'written' => '1000', 'unit' => 'coffees'], GoalDirective::extract("We need coffee.\ngoal: 1000 coffees\nThanks"));
        pledgeAssertSame(['amount' => 100000, 'written' => '1,000', 'unit' => 'hours of help'], GoalDirective::extract('  GOAL:  1,000   hours of help  '));
        pledgeAssertSame(['amount' => 4000, 'written' => '40', 'unit' => ''], GoalDirective::extract('goal: 40'));
        // The first line that reads as a goal wins; quoted lines and prose are skipped.
        pledgeAssertSame('coffees', GoalDirective::extract("> goal: 5 teas\ngoal: to have fun\ngoal: 10 coffees\ngoal: 20 teas")['unit']);
        pledgeAssertSame(null, GoalDirective::extract('> goal: 1000 coffees'));
        pledgeAssertSame(null, GoalDirective::extract('our goal: 1000 coffees'));
        pledgeAssertSame(null, GoalDirective::extract('goal: lots of coffees'));
        pledgeAssertSame(null, GoalDirective::extract('goal:'));
        pledgeAssertSame(null, GoalDirective::extract('goal: 5 ' . str_repeat('x', GoalDirective::MAX_UNIT_LENGTH + 1)));
    }

    public function testPledgeLineIsReadAsWritten(): void
    {
        pledgeAssertSame('300', PledgeDirective::extract("pledge: 300\n\nI'll order these myself."));
        pledgeAssertSame('12.50 coffees', PledgeDirective::extract("Count me in.\n  Pledge:  12.50 coffees  "));
        pledgeAssertSame('a few', PledgeDirective::extract('pledge: a few'));
        pledgeAssertSame('20', PledgeDirective::extract("> pledge: 10\npledge: 20\npledge: 30"));
        pledgeAssertSame(null, PledgeDirective::extract('> pledge: 10'));
        pledgeAssertSame(null, PledgeDirective::extract('I pledge: 10'));
        pledgeAssertSame(null, PledgeDirective::extract('pledge:'));
        pledgeAssertSame('pledge: 300', PledgeDirective::line(' 300 '));
    }

    public function testCountsNamedReadablePledgesAndExplainsTheRest(): void
    {
        $post = static fn (string $id, string $body, array $more = []): array => $more + [
            'post_id' => $id, 'thread_id' => 'root', 'body' => $body, 'retracted_at' => null,
            'author_identity_id' => self::MEMBER, 'attribution_counted' => 1, 'author_is_approved' => 1,
        ];
        $posts = [
            $post('root', "Three days, a hundred people.\ngoal: 1000 coffees"),
            $post('a', "pledge: 300\n\nI'll order these myself."),
            $post('b', 'pledge: 12.50 Coffee'),
            $post('c', 'Does decaf count?'),
            $post('d', 'pledge: 1,000'),
            $post('unverified', 'pledge: 50', ['attribution_counted' => 0]),
            $post('unnamed', 'pledge: 60', ['author_identity_id' => null]),
            $post('newcomer', 'pledge: 40', ['author_is_approved' => 0]),
            $post('words', 'pledge: a few'),
            $post('unit', 'pledge: 300 dollars'),
            $post('retracted', 'pledge: 70', ['retracted_at' => '2026-11-16T00:00:00Z']),
            $post('quoted', "> pledge: 80\nNice one."),
        ];
        $campaign = PledgeCampaign::fromPosts($posts, 'root');

        pledgeAssertSame('1000', $campaign['goal']);
        pledgeAssertSame('coffees', $campaign['unit']);
        pledgeAssertSame('1312.5', $campaign['pledged']);
        pledgeAssertSame(3, $campaign['count']);
        pledgeAssertSame(100.0, $campaign['fill_percent']);
        pledgeAssertSame(76.2, $campaign['goal_percent']);
        pledgeAssertSame(['a', 'b', 'd', 'unverified', 'unnamed', 'newcomer', 'words', 'unit'], array_keys($campaign['pledges']));
        pledgeAssertSame(['state' => 'counted', 'amount' => '12.5', 'note' => ''], $campaign['pledges']['b']);
        // Only approved keys with counted attribution count; a signed newcomer waits.
        pledgeAssertSame(['state' => 'waiting', 'amount' => '50', 'note' => 'Waiting for approval'], $campaign['pledges']['unverified']);
        pledgeAssertSame(['state' => 'waiting', 'amount' => '40', 'note' => 'Waiting for approval'], $campaign['pledges']['newcomer']);
        pledgeAssertSame(['state' => 'unnamed', 'amount' => '60', 'note' => 'Not counted: no name'], $campaign['pledges']['unnamed']);
        pledgeAssertSame(['state' => 'unreadable', 'amount' => null, 'note' => 'Not counted: “a few” is not a number'], $campaign['pledges']['words']);
        pledgeAssertSame('Not counted: “300 dollars” is not in coffees', $campaign['pledges']['unit']['note']);

        // Approval is read each time: once the newcomer's key is approved, the pledge counts.
        $approvedLater = array_map(static fn (array $row): array => $row['post_id'] === 'newcomer' ? ['author_is_approved' => 1] + $row : $row, $posts);
        pledgeAssertSame('1352.5', PledgeCampaign::fromPosts($approvedLater, 'root')['pledged']);
        pledgeAssertSame('counted', PledgeCampaign::fromPosts($approvedLater, 'root')['pledges']['newcomer']['state']);

        // Under the goal the bar's end is the goal, so there is no goal mark.
        $under = PledgeCampaign::fromPosts(array_slice($posts, 0, 2), 'root');
        pledgeAssertSame('300', $under['pledged']);
        pledgeAssertSame(30.0, $under['fill_percent']);
        pledgeAssertSame(null, $under['goal_percent']);

        // No goal line, a goal line in a reply only, or a retracted opening post: not a campaign.
        pledgeAssertSame(null, PledgeCampaign::fromPosts([$post('root', 'Hello'), $post('a', 'goal: 5 teas')], 'root'));
        pledgeAssertSame(null, PledgeCampaign::fromPosts([$post('root', 'goal: 5 teas', ['retracted_at' => '2026-11-16T00:00:00Z'])], 'root'));
    }

    public function testThreadPageShowsTotalsAndAmountsOnlyWithTheFlagOn(): void
    {
        [$root, $database, $service] = $this->environment();
        $thread = $this->thread($service, 'Coffee for the hack weekend', "We need coffee.\ngoal: 1000 coffees");
        $this->reply($service, $thread, "pledge: 300\n\nI'll order these myself.");
        $unverified = $this->reply($service, $thread, 'pledge: 250');
        $this->reply($service, $thread, 'pledge: a few');
        $this->reply($service, $thread, 'pledge: 40', null);
        (new PDO('sqlite:' . $database))->prepare('UPDATE posts SET attribution_counted = 0 WHERE post_id = ?')->execute([$unverified]);
        $plain = $this->thread($service, 'No campaign', 'Just talking.');
        $this->reply($service, $plain, 'pledge: 300');
        $page = fn (string $threadId): string => $this->render(new Application(dirname(__DIR__), $root, $database), '/threads/' . rawurlencode($threadId));

        putenv('FORUM_PLEDGES_ENABLED');
        $off = $page($thread);
        pledgeAssertNotContains('pledge-totals', $off);
        pledgeAssertNotContains('pledge-amount', $off);
        pledgeAssertContains('goal: 1000 coffees', $off);

        putenv('FORUM_PLEDGES_ENABLED=1');
        try {
            $on = $page($thread);
            $other = $page($plain);
        } finally {
            putenv('FORUM_PLEDGES_ENABLED');
        }
        pledgeAssertContains('<strong>300</strong> of 1000 coffees pledged', $on);
        pledgeAssertContains('aria-label="300 of 1000 coffees pledged"', $on);
        pledgeAssertContains('style="width: 30%"', $on);
        pledgeAssertNotContains('pledge-bar__goal', $on);
        pledgeAssertContains('1 pledge</p>', $on);
        pledgeAssertContains('data-pledge-state="counted"><strong>300</strong> coffees <span class="pledge-status" data-role="pledge-card-status">Open</span></p>', $on);
        pledgeAssertContains('data-pledge-state="waiting"><strong>250</strong> coffees · <span class="meta">Waiting for approval</span>', $on);
        pledgeAssertContains('data-pledge-state="unnamed"><strong>40</strong> coffees · <span class="meta">Not counted: no name</span>', $on);
        pledgeAssertContains('data-pledge-state="unreadable"><span class="meta">Not counted: “a few” is not a number</span>', $on);
        // A site that still stores the removed "Count pledges with no name" flag changes nothing.
        putenv('FORUM_PLEDGES_ENABLED=1');
        putenv('FORUM_PLEDGES_COUNT_UNNAMED_ENABLED=1');
        try {
            $stored = $page($thread);
        } finally {
            putenv('FORUM_PLEDGES_ENABLED');
            putenv('FORUM_PLEDGES_COUNT_UNNAMED_ENABLED');
        }
        pledgeAssertContains('<strong>300</strong> of 1000 coffees pledged', $stored);
        pledgeAssertContains('Not counted: no name', $stored);
        // The thread page leaves out the lines its totals and cards already show; an unread line stays.
        pledgeAssertNotContains('goal: 1000 coffees', $on);
        pledgeAssertContains('We need coffee.', $on);
        pledgeAssertNotContains('pledge: 300', $on);
        pledgeAssertContains('I&#039;ll order these myself.', $on);
        pledgeAssertNotContains('pledge: 250', $on);
        pledgeAssertContains('pledge: a few', $on);
        // A pledge line outside a campaign is an ordinary reply.
        pledgeAssertNotContains('pledge-totals', $other);
        pledgeAssertNotContains('pledge-amount', $other);
    }

    public function testBakedThreadPageFollowsANewPledge(): void
    {
        [$root, $database, $service, $artifacts] = $this->environment();
        $thread = $this->thread($service, 'Baked campaign', 'goal: 10 hours');
        $path = $artifacts . '/threads/' . $thread . '.html';
        putenv('FORUM_PLEDGES_ENABLED=1');
        try {
            $builder = new StaticArtifactBuilder(dirname(__DIR__), $root, $database, $artifacts);
            pledgeAssertSame(true, $builder->buildSingleRoute('/threads/' . $thread));
            pledgeAssertContains('<strong>0</strong> of 10 hours pledged', (string) file_get_contents($path));

            $this->reply($service, $thread, 'pledge: 12.5');
            // The reply drops the baked page, so the next build carries the new sum and the goal mark.
            pledgeAssertSame(false, is_file($path));
            pledgeAssertSame(true, $builder->buildSingleRoute('/threads/' . $thread));
            $baked = (string) file_get_contents($path);
            pledgeAssertContains('<strong>12.5</strong> of 10 hours pledged', $baked);
            pledgeAssertContains('pledge-bar__goal" style="left: 80%"', $baked);
        } finally {
            putenv('FORUM_PLEDGES_ENABLED');
        }
    }

    public function testAmountFieldWritesTheLineOnceAndOnlyWithTheFlagOn(): void
    {
        pledgeAssertSame("pledge: 300\n\nI'll order these.", PledgeDirective::withAmount("  I'll order these.\n", ' 300 '));
        pledgeAssertSame('pledge: 12.5', PledgeDirective::withAmount('', '12.5'));
        pledgeAssertSame('pledge: a few', PledgeDirective::withAmount('', "a\nfew"));
        // A body that already has the line is left alone, and an empty amount adds nothing.
        pledgeAssertSame("pledge: 300\n\nNote", PledgeDirective::withAmount("pledge: 300\n\nNote", '300'));
        pledgeAssertSame('Just a reply', PledgeDirective::withAmount('Just a reply', '  '));

        [$root, $database, $service] = $this->environment();
        $thread = $this->thread($service, 'Form campaign', 'goal: 1000 coffees');
        $plain = $this->thread($service, 'No campaign', 'Just talking.');
        $application = fn (): Application => new Application(dirname(__DIR__), $root, $database);
        $post = function (array $fields) use ($application, $thread): void {
            $_POST = $fields + ['thread_id' => $thread, 'parent_id' => $thread, 'board_tags' => 'general'];
            try {
                $this->render($application(), 'POST /compose/reply');
            } finally {
                $_POST = [];
            }
        };
        $bodies = fn (): array => (new PDO('sqlite:' . $database))
            ->query("SELECT body FROM posts WHERE thread_id = '" . $thread . "' AND post_id != thread_id ORDER BY sequence_number")->fetchAll(PDO::FETCH_COLUMN);

        putenv('FORUM_PLEDGES_ENABLED');
        pledgeAssertNotContains('name="pledge_amount"', $this->render($application(), '/threads/' . rawurlencode($thread)));
        $post(['body' => 'Flag off', 'pledge_amount' => '5']);

        putenv('FORUM_PLEDGES_ENABLED=1');
        try {
            $page = $this->render($application(), '/threads/' . rawurlencode($thread));
            pledgeAssertContains('<label class="pledge-field">Pledge<input type="text" name="pledge_amount" inputmode="decimal"', $page);
            pledgeAssertContains('<span class="pledge-field__unit">coffees</span>', $page);
            pledgeAssertContains('/assets/pledge_field.', $page);
            // The field lives in the pledge form in the totals block, which has its own draft and button;
            // the reply box at the bottom no longer carries it.
            pledgeAssertSame(1, substr_count($page, 'name="pledge_amount"'));
            pledgeAssertContains('data-compose-kind="reply" data-pledge-form data-compose-draft-scope="pledge">', $page);
            $pledgeForm = substr($page, (int) strpos($page, 'data-pledge-form'), 6000);
            pledgeAssertContains('name="pledge_amount"', $pledgeForm);
            pledgeAssertContains('placeholder="Note (optional)"', $pledgeForm);
            pledgeAssertContains('Post pledge', $pledgeForm);
            pledgeAssertNotContains('data-compose-disclosure="options"', substr($pledgeForm, 0, (int) strpos($pledgeForm, '</form>')));
            $bottom = substr($page, (int) strpos($page, 'data-inline-reply-compose'));
            pledgeAssertNotContains('name="pledge_amount"', $bottom);
            pledgeAssertNotContains('data-pledge-form', $bottom);
            $other = $this->render($application(), '/threads/' . rawurlencode($plain));
            pledgeAssertNotContains('name="pledge_amount"', $other);
            pledgeAssertNotContains('pledge_field.', $other);
            // The separate Reply page has no amount field in this cycle.
            pledgeAssertNotContains('name="pledge_amount"', $this->render($application(), '/compose/reply?thread_id=' . rawurlencode($thread) . '&parent_id=' . rawurlencode($thread)));

            $post(['body' => "I'll order these.", 'pledge_amount' => '300']);
            $post(['body' => '', 'pledge_amount' => '12.5']);
            $post(['body' => "pledge: 40\n\nSent by the script too.", 'pledge_amount' => '40']);
            $post(['body' => 'No amount', 'pledge_amount' => '']);
        } finally {
            putenv('FORUM_PLEDGES_ENABLED');
        }

        pledgeAssertSame(
            ["Flag off\n", "pledge: 300\n\nI'll order these.\n", "pledge: 12.5\n", "pledge: 40\n\nSent by the script too.\n", "No amount\n"],
            $bodies()
        );
    }

    public function testWithdrawLineIsBareOrCarriesText(): void
    {
        pledgeAssertSame('', WithdrawDirective::extract('withdraw'));
        pledgeAssertSame('', WithdrawDirective::extract("Sorry, the order fell through.\n  Withdraw:  "));
        pledgeAssertSame('100', WithdrawDirective::extract('withdraw: 100'));
        pledgeAssertSame(null, WithdrawDirective::extract('> withdraw'));
        pledgeAssertSame(null, WithdrawDirective::extract('I withdraw'));
        pledgeAssertSame(null, WithdrawDirective::extract('withdraw my pledge please'));
        pledgeAssertSame(null, WithdrawDirective::extract('withdrawn'));
        pledgeAssertSame(true, WithdrawDirective::isBare(WithdrawDirective::BODY));
        pledgeAssertSame(true, WithdrawDirective::isBare("\n WITHDRAW: \n\n"));
        pledgeAssertSame(false, WithdrawDirective::isBare("withdraw\n\nSorry."));
        pledgeAssertSame(false, WithdrawDirective::isBare('withdraw: 100'));
        pledgeAssertSame(false, WithdrawDirective::isBare('Sorry.'));
    }

    public function testThePledgerWithdrawsAndEveryOtherWithdrawLineSaysWhyNot(): void
    {
        $other = 'openpgp:' . str_repeat('b', 40);
        $post = static fn (string $id, string $body, string $parent = 'root', array $more = []): array => $more + [
            'post_id' => $id, 'thread_id' => 'root', 'parent_id' => $parent, 'body' => $body, 'retracted_at' => null,
            'author_identity_id' => self::MEMBER, 'attribution_counted' => 1, 'author_is_approved' => 1, 'created_at' => '2026-11-16T05:00:00Z',
        ];
        $posts = [
            $post('root', 'goal: 1000 coffees'),
            $post('a', 'pledge: 300'),
            $post('b', 'pledge: 200'),
            $post('c', 'pledge: 100', 'root', ['author_identity_id' => $other]),
            $post('d', 'pledge: 50'),
            $post('e', 'pledge: 25'),
            $post('nameless', 'pledge: 60', 'root', ['author_identity_id' => null]),
            $post('talk', 'Does decaf count?'),
            $post('wa', "Withdraw:\n\nThe order fell through.", 'a'),
            $post('wa2', 'withdraw', 'a'),
            $post('wb-other', 'withdraw', 'b', ['author_identity_id' => $other]),
            $post('wc-other', 'withdraw', 'c'),
            $post('wd-number', 'withdraw: 20', 'd'),
            $post('wd-unverified', 'withdraw', 'd', ['attribution_counted' => 0]),
            $post('wd-nameless', 'withdraw', 'd', ['author_identity_id' => null]),
            $post('we-retracted', 'withdraw', 'e', ['retracted_at' => '2026-11-16T06:00:00Z']),
            $post('w-talk', 'withdraw', 'talk'),
            $post('w-root', 'withdraw'),
            $post('w-nameless', 'withdraw', 'nameless', ['author_identity_id' => null]),
            $post('quoted', "> withdraw\nPlease don't.", 'b'),
        ];
        $campaign = PledgeCampaign::fromPosts($posts, 'root');

        // a is withdrawn by its pledger; b, c, d and e still count.
        pledgeAssertSame('375', $campaign['pledged']);
        pledgeAssertSame(4, $campaign['count']);
        pledgeAssertSame(
            ['state' => 'withdrawn', 'amount' => '300', 'note' => '', 'withdrawn_by' => 'wa', 'withdrawn_at' => '2026-11-16T05:00:00Z'],
            $campaign['pledges']['a']
        );
        foreach (['b', 'c', 'd', 'e'] as $id) {
            pledgeAssertSame('counted', $campaign['pledges'][$id]['state']);
        }
        pledgeAssertSame([
            'wa' => ['effective' => true, 'note' => ''],
            // A second withdrawal changes nothing and is still a withdrawal.
            'wa2' => ['effective' => true, 'note' => ''],
            'wb-other' => ['effective' => false, 'note' => 'Not counted: only the pledger can withdraw'],
            'wc-other' => ['effective' => false, 'note' => 'Not counted: only the pledger can withdraw'],
            'wd-number' => ['effective' => false, 'note' => 'Not counted: write “withdraw” alone on its line'],
            'wd-unverified' => ['effective' => false, 'note' => 'Not counted: waiting for approval'],
            'wd-nameless' => ['effective' => false, 'note' => 'Not counted: no name'],
            'w-talk' => ['effective' => false, 'note' => 'Not counted: not a reply to a pledge'],
            'w-root' => ['effective' => false, 'note' => 'Not counted: not a reply to a pledge'],
            // A pledge with no name is not counted, so there is nothing to withdraw.
            'w-nameless' => ['effective' => false, 'note' => 'Not counted: not a reply to a pledge'],
        ], $campaign['withdrawals']);

        // Retracting the withdraw reply undoes it: a retracted post is not read.
        $posts[8]['retracted_at'] = '2026-11-16T07:00:00Z';
        $posts[9]['retracted_at'] = '2026-11-16T07:00:00Z';
        $undone = PledgeCampaign::fromPosts($posts, 'root');
        pledgeAssertSame('675', $undone['pledged']);
        pledgeAssertSame(['state' => 'counted', 'amount' => '300', 'note' => ''], $undone['pledges']['a']);

        // A withdraw line from a key not yet approved does not count.
        $waiting = PledgeCampaign::fromPosts([...$posts, $post('w-new', 'withdraw', 'a', ['author_is_approved' => 0])], 'root');
        pledgeAssertSame(['effective' => false, 'note' => 'Not counted: waiting for approval'], $waiting['withdrawals']['w-new']);

        // A withdrawal of a retracted pledge has nothing to mark.
        $posts[1]['retracted_at'] = '2026-11-16T08:00:00Z';
        $posts[8]['retracted_at'] = null;
        $gone = PledgeCampaign::fromPosts($posts, 'root');
        pledgeAssertSame(false, isset($gone['pledges']['a']));
        pledgeAssertSame('Not counted: not a reply to a pledge', $gone['withdrawals']['wa']['note']);
    }

    public function testFilledLineIsBareOrCarriesText(): void
    {
        pledgeAssertSame('', FilledDirective::extract('filled'));
        pledgeAssertSame('', FilledDirective::extract("Arrived Tuesday.\n  Filled:  "));
        pledgeAssertSame('200', FilledDirective::extract('filled: 200'));
        pledgeAssertSame('2 coffees', FilledDirective::extract('FILLED: 2 coffees'));
        pledgeAssertSame(null, FilledDirective::extract('> filled'));
        pledgeAssertSame(null, FilledDirective::extract('I filled it'));
        pledgeAssertSame(null, FilledDirective::extract('filled up the tank'));
        pledgeAssertSame(true, FilledDirective::isBare(FilledDirective::BODY));
        pledgeAssertSame(true, FilledDirective::isBare("\n Filled: \n"));
        pledgeAssertSame(false, FilledDirective::isBare("filled\n\nThanks!"));
        pledgeAssertSame(false, FilledDirective::isBare('filled: 200'));
    }

    public function testFillsAddUpToEachPledgeAndEveryOtherFillLineSaysWhyNot(): void
    {
        $author = 'openpgp:' . str_repeat('a', 40);
        $mara = 'openpgp:' . str_repeat('b', 40);
        $op = 'openpgp:' . str_repeat('c', 40);
        $rin = 'openpgp:' . str_repeat('d', 40);
        $post = static fn (string $id, string $body, string $parent, string $by, array $more = []): array => $more + [
            'post_id' => $id, 'thread_id' => 'root', 'parent_id' => $parent, 'body' => $body, 'retracted_at' => null,
            'author_identity_id' => $by, 'author_label' => ['' => '', $author => 'ilyag', $mara => 'mara', $op => 'opal', $rin => 'rin'][$by],
            'attribution_counted' => 1, 'author_is_approved' => 1, 'created_at' => '2026-11-16T05:00:00Z',
        ];
        $posts = [
            $post('root', 'goal: 1000 coffees', '', $author),
            $post('a', 'pledge: 300', 'root', $mara),
            $post('b', 'pledge: 400', 'root', $rin),
            $post('c', 'pledge: 50', 'root', $rin),
            $post('fa', 'filled', 'a', $mara),
            $post('fa2', 'filled: 10', 'a', $author),
            $post('fb', 'filled: 100', 'b', $author),
            $post('fb2', "filled: 2 coffees\nMore tomorrow.", 'b', $op),
            $post('fb3', 'filled: 350', 'b', $rin),
            $post('fc-other', 'filled', 'c', $mara),
            $post('fc-lots', 'filled: lots', 'c', $rin),
            $post('fc-unit', 'filled: 5 teas', 'c', $rin),
            $post('fc-unverified', 'filled', 'c', $rin, ['attribution_counted' => 0]),
            $post('fc-nameless', 'filled', 'c', ''),
            $post('fc-retracted', 'filled', 'c', $rin, ['retracted_at' => '2026-11-16T06:00:00Z']),
            $post('f-root', 'filled', 'root', $author),
            $post('f-fill', 'filled', 'fa', $author),
        ];
        $campaign = PledgeCampaign::fromPosts($posts, 'root', static fn (string $id): bool => $id === $op);

        pledgeAssertSame('750', $campaign['pledged']);
        // a: 300 by mara; b: 100 + 2 + 298 of 350 = 400; c: nothing.
        pledgeAssertSame('700', $campaign['filled']);
        pledgeAssertSame('102', $campaign['confirmed']);
        pledgeAssertSame('300', $campaign['pledges']['a']['filled']);
        pledgeAssertSame([['post_id' => 'fa', 'by' => 'mara', 'role' => 'pledger', 'amount' => '300']], $campaign['pledges']['a']['fill_list']);
        pledgeAssertSame([
            ['post_id' => 'fb', 'by' => 'ilyag', 'role' => 'author', 'amount' => '100'],
            ['post_id' => 'fb2', 'by' => 'opal', 'role' => 'operator', 'amount' => '2'],
            ['post_id' => 'fb3', 'by' => 'rin', 'role' => 'pledger', 'amount' => '298'],
        ], $campaign['pledges']['b']['fill_list']);
        pledgeAssertSame(false, isset($campaign['pledges']['c']['filled']));
        pledgeAssertSame([
            'fa' => ['effective' => true, 'note' => ''],
            'fa2' => ['effective' => false, 'note' => 'Not counted: already filled'],
            'fb' => ['effective' => true, 'note' => ''],
            'fb2' => ['effective' => true, 'note' => ''],
            'fb3' => ['effective' => true, 'note' => 'Counted 298 of 350'],
            'fc-other' => ['effective' => false, 'note' => 'Not counted: only the pledger, the author or an operator can fill'],
            'fc-lots' => ['effective' => false, 'note' => 'Not counted: “lots” is not a number'],
            'fc-unit' => ['effective' => false, 'note' => 'Not counted: “5 teas” is not in coffees'],
            'fc-unverified' => ['effective' => false, 'note' => 'Not counted: waiting for approval'],
            'fc-nameless' => ['effective' => false, 'note' => 'Not counted: no name'],
            'f-root' => ['effective' => false, 'note' => 'Not counted: not a reply to a counted pledge'],
            'f-fill' => ['effective' => false, 'note' => 'Not counted: not a reply to a counted pledge'],
        ], $campaign['fills']);

        // Without an operator check nobody is an operator; retracting a fill undoes it.
        $plain = PledgeCampaign::fromPosts($posts, 'root');
        pledgeAssertSame('Not counted: only the pledger, the author or an operator can fill', $plain['fills']['fb2']['note']);
        $posts[4]['retracted_at'] = '2026-11-16T07:00:00Z';
        $undone = PledgeCampaign::fromPosts($posts, 'root', static fn (string $id): bool => $id === $op);
        pledgeAssertSame(['effective' => true, 'note' => ''], $undone['fills']['fa2']);
        pledgeAssertSame('10', $undone['pledges']['a']['filled']);
        pledgeAssertSame('410', $undone['filled']);
        pledgeAssertSame('112', $undone['confirmed']);
    }

    public function testWithdrawingAPartFilledPledgeKeepsWhatArrivedInAnyOrder(): void
    {
        $mara = 'openpgp:' . str_repeat('b', 40);
        $post = static fn (string $id, string $body, string $parent): array => [
            'post_id' => $id, 'thread_id' => 'root', 'parent_id' => $parent, 'body' => $body, 'retracted_at' => null,
            'author_identity_id' => $mara, 'author_label' => 'mara', 'attribution_counted' => 1, 'author_is_approved' => 1, 'created_at' => '2026-11-16T05:00:00Z',
        ];
        $fill = $post('f', 'filled: 100', 'a');
        $withdraw = $post('w', 'withdraw', 'a');
        foreach ([[$fill, $withdraw], [$withdraw, $fill]] as $order) {
            $campaign = PledgeCampaign::fromPosts([$post('root', 'goal: 1000', ''), $post('a', 'pledge: 300', 'root'), ...$order], 'root');
            pledgeAssertSame('withdrawn', $campaign['pledges']['a']['state']);
            pledgeAssertSame('100', $campaign['pledges']['a']['filled']);
            pledgeAssertSame('100', $campaign['pledged']);
            pledgeAssertSame('100', $campaign['filled']);
            pledgeAssertSame(0, $campaign['count']);
            pledgeAssertSame(['effective' => true, 'note' => ''], $campaign['withdrawals']['w']);
        }

        // A fully filled pledge cannot be withdrawn, whichever reply came first.
        $full = $post('f', 'filled', 'a');
        foreach ([[$full, $withdraw], [$withdraw, $full]] as $order) {
            $campaign = PledgeCampaign::fromPosts([$post('root', 'goal: 1000', ''), $post('a', 'pledge: 300', 'root'), ...$order], 'root');
            pledgeAssertSame('counted', $campaign['pledges']['a']['state']);
            pledgeAssertSame('300', $campaign['pledged']);
            pledgeAssertSame(['effective' => false, 'note' => 'Not counted: already filled'], $campaign['withdrawals']['w']);
        }
    }

    public function testThreadPageMarksAWithdrawnPledgeAndABakedPageFollows(): void
    {
        [$root, $database, $service, $artifacts] = $this->environment();
        $thread = $this->thread($service, 'Withdraw campaign', 'goal: 1000 coffees');
        $kept = $this->reply($service, $thread, 'pledge: 200');
        $pledge = $this->reply($service, $thread, "pledge: 300\n\nI'll order these myself.");
        $path = $artifacts . '/threads/' . $thread . '.html';
        $page = fn (): string => $this->render(new Application(dirname(__DIR__), $root, $database), '/threads/' . rawurlencode($thread));

        putenv('FORUM_PLEDGES_ENABLED=1');
        try {
            $builder = new StaticArtifactBuilder(dirname(__DIR__), $root, $database, $artifacts);
            pledgeAssertSame(true, $builder->buildSingleRoute('/threads/' . $thread));
            pledgeAssertContains('<strong>500</strong> of 1000 coffees pledged', (string) file_get_contents($path));

            $withdrawal = $this->replyTo($service, $thread, $pledge, "withdraw\n\nThe order fell through.");
            $stranger = $this->replyTo($service, $thread, $kept, 'withdraw: 50');
            // The reply drops the baked page, so the next build carries the lower sum.
            pledgeAssertSame(false, is_file($path));
            pledgeAssertSame(true, $builder->buildSingleRoute('/threads/' . $thread));
            pledgeAssertContains('<strong>200</strong> of 1000 coffees pledged', (string) file_get_contents($path));

            $on = $page();
            pledgeAssertContains('<strong>200</strong> of 1000 coffees pledged', $on);
            pledgeAssertContains('1 pledge</p>', $on);
            pledgeAssertContains(
                'data-pledge-state="withdrawn"><strong>300</strong> coffees · <a href="/posts/' . $withdrawal . '">Withdrawn</a> <span class="meta">',
                $on
            );
            // The pledge keeps its text, and the withdraw line stays in its reply as written.
            pledgeAssertContains('order these myself.', $on);
            pledgeAssertContains('The order fell through.', $on);
            pledgeAssertContains('data-pledge-state="withdraw-uncounted"><span class="meta">Not counted: write “withdraw” alone on its line</span>', $on);
            pledgeAssertContains('data-pledge-state="counted"><strong>200</strong> coffees <span class="pledge-status" data-role="pledge-card-status">Open</span></p>', $on);
        } finally {
            putenv('FORUM_PLEDGES_ENABLED');
        }

        // With the flag off the lines are plain text.
        $off = $page();
        pledgeAssertNotContains('pledge-amount', $off);
        pledgeAssertContains('withdraw: 50', $off);
        pledgeAssertSame(true, $stranger !== '');
    }

    public function testThreadPageListsFillsAndABakedPageFollows(): void
    {
        [$root, $database, $service, $artifacts] = $this->environment();
        // The fixture's key is a root operator. A campaign with no name lets it fill a second
        // person's pledge as an operator; its own pledge shows it filling as the pledger.
        $thread = (string) $service->createThread(['board_tags' => 'general', 'subject' => 'Fill campaign', 'body' => 'goal: 1000 coffees'])['thread_id'];
        $own = $this->reply($service, $thread, 'pledge: 300');
        $second = $this->secondIdentity($service);
        $other = $this->reply($service, $thread, 'pledge: 400', $second);
        $path = $artifacts . '/threads/' . $thread . '.html';
        $this->approveKeys($database, $second);
        $page = fn (): string => $this->render(new Application(dirname(__DIR__), $root, $database), '/threads/' . rawurlencode($thread));

        putenv('FORUM_PLEDGES_ENABLED=1');
        try {
            $builder = new StaticArtifactBuilder(dirname(__DIR__), $root, $database, $artifacts);
            pledgeAssertSame(true, $builder->buildSingleRoute('/threads/' . $thread));
            pledgeAssertContains('2 pledges</p>', (string) file_get_contents($path));

            $delivered = $this->replyTo($service, $thread, $own, 'filled');
            $filled = $this->replyTo($service, $thread, $other, 'filled: 100');
            $over = $this->replyTo($service, $thread, $other, 'filled: 500');
            $nameless = $this->replyTo($service, $thread, $own, 'filled', null);
            // The reply drops the baked page, so the next build carries the new totals.
            pledgeAssertSame(false, is_file($path));
            pledgeAssertSame(true, $builder->buildSingleRoute('/threads/' . $thread));
            pledgeAssertContains('2 pledges · 700 delivered (400 confirmed)</p>', (string) file_get_contents($path));

            $on = $page();
            pledgeAssertContains('data-pledge-state="fill"><a href="/posts/' . $delivered . '">Delivered by ', $on);
            pledgeAssertContains('data-pledge-state="fill"><a href="/posts/' . $filled . '">Filled by ', $on);
            pledgeAssertContains('</a> · 100</p>', $on);
            pledgeAssertContains('data-pledge-state="fill"><a href="/posts/' . $over . '">Filled by ', $on);
            pledgeAssertContains('</a> · 300</p>', $on);
            pledgeAssertContains('data-pledge-state="fill-capped"><span class="meta">Counted 300 of 500</span>', $on);
            pledgeAssertContains('data-pledge-state="fill-uncounted"><span class="meta">Not counted: no name</span>', $on);
            pledgeAssertSame(true, $nameless !== '');
            // A fill that is only its line folds into its pledge, with or without an amount; one
            // counted in part, or not counted, stays for its reason.
            pledgeAssertNotContains('data-post-id="' . $delivered . '"', $on);
            pledgeAssertNotContains('data-post-id="' . $filled . '"', $on);
            pledgeAssertContains('data-post-id="' . $over . '"', $on);
            pledgeAssertContains('data-post-id="' . $nameless . '"', $on);
            // The post's own page keeps the line as written.
            pledgeAssertContains('pledge: 300', $this->render(new Application(dirname(__DIR__), $root, $database), '/posts/' . rawurlencode($own)));
        } finally {
            putenv('FORUM_PLEDGES_ENABLED');
        }

        // With the flag off the lines are plain text.
        pledgeAssertNotContains('pledge-amount', $page());
    }

    public function testABareWithdrawalFoldsIntoThePledgeAndUndoLeavesNoStub(): void
    {
        [$root, $database, $service] = $this->environment();
        $thread = $this->thread($service, 'Fold campaign', 'goal: 1000 coffees');
        $first = $this->reply($service, $thread, 'pledge: 300');
        $second = $this->reply($service, $thread, 'pledge: 200');
        $third = $this->reply($service, $thread, 'pledge: 100');
        $bare = $this->replyTo($service, $thread, $first, 'withdraw');
        $noted = $this->replyTo($service, $thread, $second, "withdraw\n\nThe order fell through.");
        $answered = $this->replyTo($service, $thread, $third, 'withdraw');
        $this->replyTo($service, $thread, $answered, 'Sorry to hear that.');
        $application = fn (): Application => new Application(dirname(__DIR__), $root, $database);
        $card = static fn (string $postId): string => 'data-post-id="' . $postId . '"';

        putenv('FORUM_PLEDGES_ENABLED=1');
        putenv('FORUM_THREAD_ACTIVITY_DRAWER_ENABLED=1');
        try {
            $page = $this->render($application(), '/threads/' . rawurlencode($thread));
            pledgeAssertContains('<strong>0</strong> of 1000 coffees pledged', $page);
            // The bare withdrawal has no card; the pledge links to its page, which still shows it.
            pledgeAssertNotContains($card($bare), $page);
            pledgeAssertContains('<a href="/posts/' . $bare . '">Withdrawn</a>', $page);
            pledgeAssertContains($card($noted), $page);
            pledgeAssertContains($card($answered), $page);
            pledgeAssertContains('withdraw', $this->render($application(), '/posts/' . rawurlencode($bare)));
        } finally {
            putenv('FORUM_THREAD_ACTIVITY_DRAWER_ENABLED');
        }
        try {
            // With the flag off nothing folds: the line is an ordinary reply.
            putenv('FORUM_PLEDGES_ENABLED');
            pledgeAssertContains($card($bare), $this->render($application(), '/threads/' . rawurlencode($thread)));

            // Undo: retracting the withdraw reply restores the pledge and leaves no "retracted" stub.
            putenv('FORUM_PLEDGES_ENABLED=1');
            (new PDO('sqlite:' . $database))->prepare("UPDATE posts SET retracted_at = '2026-11-16T09:00:00Z', body = '' WHERE post_id = ?")->execute([$bare]);
            $undone = $this->render($application(), '/threads/' . rawurlencode($thread));
            pledgeAssertContains('<strong>300</strong> of 1000 coffees pledged', $undone);
            pledgeAssertContains('data-pledge-state="counted"><strong>300</strong> coffees <span class="pledge-status" data-role="pledge-card-status">Open</span></p>', $undone);
            pledgeAssertNotContains($card($bare), $undone);
        } finally {
            putenv('FORUM_PLEDGES_ENABLED');
        }
    }

    public function testWithdrawButtonShowsOnlyOnThePledgersOwnCountedPledge(): void
    {
        [$root, $database, $service] = $this->environment();
        $thread = $this->thread($service, 'Button campaign', 'goal: 1000 coffees');
        $mine = $this->reply($service, $thread, 'pledge: 300');
        $withdrawn = $this->reply($service, $thread, 'pledge: 200');
        $this->replyTo($service, $thread, $withdrawn, 'withdraw');
        $second = $this->secondIdentity($service);
        $this->approveKeys($database, $second);
        $theirs = $this->reply($service, $thread, 'pledge: 40', $second);
        $this->reply($service, $thread, 'pledge: a few');
        $page = fn (): string => $this->render(new Application(dirname(__DIR__), $root, $database), '/threads/' . rawurlencode($thread));
        $form = static fn (string $postId): string => '<input type="hidden" name="parent_id" value="' . $postId . '">';
        $oldCookies = $_COOKIE;

        putenv('FORUM_PLEDGES_ENABLED=1');
        try {
            // No viewer: no button anywhere, so a page drawn for everyone carries none.
            $_COOKIE = [];
            pledgeAssertNotContains('data-role="pledge-withdraw"', $page());

            $_COOKIE = ['identity_hint' => self::MEMBER];
            $own = $page();
            pledgeAssertSame(1, substr_count($own, 'data-role="pledge-withdraw"'));
            pledgeAssertContains('<form class="pledge-withdraw" method="post" action="/compose/reply" data-role="pledge-withdraw">', $own);
            pledgeAssertContains($form($mine), $own);
            pledgeAssertContains('<input type="hidden" name="body" value="withdraw">', $own);
            pledgeAssertContains('>Withdraw pledge</button>', $own);
            pledgeAssertContains('/assets/pledge_withdraw.', $own);
            // Not on a withdrawn pledge, someone else's pledge, or a line that is not counted.
            // The member started the campaign, so the other person's pledge gets Mark filled instead.
            pledgeAssertNotContains($form($withdrawn), $own);
            pledgeAssertSame(1, substr_count($own, '>Mark filled</button>'));
            pledgeAssertContains($form($theirs), $own);

            // Another viewer sees no button on this member's pledge.
            $_COOKIE = ['identity_hint' => 'openpgp:' . str_repeat('b', 40)];
            pledgeAssertNotContains('data-role="pledge-withdraw"', $page());

            // Without JavaScript the form posts a reply with no name: it does not withdraw, and says why.
            $_COOKIE = ['identity_hint' => self::MEMBER];
            $_POST = ['thread_id' => $thread, 'parent_id' => $mine, 'board_tags' => 'general', 'body' => 'withdraw', 'return_to' => '/threads/' . $thread];
            try {
                $this->render(new Application(dirname(__DIR__), $root, $database), 'POST /compose/reply');
            } finally {
                $_POST = [];
            }
            $after = $page();
            pledgeAssertContains('<strong>340</strong> of 1000 coffees pledged', $after);
            pledgeAssertContains('data-pledge-state="withdraw-uncounted"><span class="meta">Not counted: no name</span>', $after);
            pledgeAssertContains($form($mine), $after);
        } finally {
            $_COOKIE = $oldCookies;
            putenv('FORUM_PLEDGES_ENABLED');
        }
    }

    public function testFillButtonsShowForThePledgerTheAuthorAndOperatorsOnly(): void
    {
        [$root, $database, $service] = $this->environment();
        $page = fn (string $thread): string => $this->render(new Application(dirname(__DIR__), $root, $database), '/threads/' . rawurlencode($thread));
        $second = $this->secondIdentity($service);
        $this->approveKeys($database, $second);
        $oldCookies = $_COOKIE;

        // The member's campaign: Mark delivered on the member's own pledge, Mark filled on someone else's.
        $mine = $this->thread($service, 'Own campaign', 'goal: 1000 coffees');
        $own = $this->reply($service, $mine, 'pledge: 300');
        $part = $this->reply($service, $mine, 'pledge: 200');
        $this->replyTo($service, $mine, $part, 'filled: 50');
        $full = $this->reply($service, $mine, 'pledge: 100');
        $this->replyTo($service, $mine, $full, 'filled');
        $nameless = $this->reply($service, $mine, 'pledge: 40', $second);
        // A campaign with no name: the member is not its author, but is an operator.
        $open = (string) $service->createThread(['board_tags' => 'general', 'subject' => 'Open campaign', 'body' => 'goal: 10 coffees'])['thread_id'];
        $theirs = $this->reply($service, $open, 'pledge: 5', $second);

        putenv('FORUM_PLEDGES_ENABLED=1');
        try {
            $_COOKIE = [];
            pledgeAssertNotContains('data-role="pledge-line"', $page($mine));

            $_COOKIE = ['identity_hint' => self::MEMBER];
            $html = $page($mine);
            // Own and part-filled pledges: Mark delivered; the fully filled one: nothing, not even Withdraw.
            pledgeAssertSame(2, substr_count($html, '>Mark delivered</button>'));
            pledgeAssertSame(1, substr_count($html, '>Mark filled</button>'));
            pledgeAssertSame(2, substr_count($html, 'data-role="pledge-withdraw"'));
            pledgeAssertContains('<input type="hidden" name="body" value="filled">', $html);
            foreach ([$own, $part, $nameless] as $id) {
                pledgeAssertContains('<input type="hidden" name="parent_id" value="' . $id . '">', $html);
            }
            pledgeAssertNotContains('<input type="hidden" name="parent_id" value="' . $full . '">', $html);
            pledgeAssertContains('>Mark filled</button>', $page($open));
            pledgeAssertContains('<input type="hidden" name="parent_id" value="' . $theirs . '">', $page($open));

            // A viewer who is none of the three sees no fill button.
            $_COOKIE = ['identity_hint' => 'openpgp:' . str_repeat('b', 40)];
            pledgeAssertNotContains('data-role="pledge-line"', $page($mine));
            pledgeAssertNotContains('data-role="pledge-line"', $page($open));
            pledgeAssertContains('data-failure="Marking failed. Try again."', $html);
        } finally {
            $_COOKIE = $oldCookies;
            putenv('FORUM_PLEDGES_ENABLED');
        }
    }

    /** @return array{string,string,LocalWriteService,string} */
    public function testReviewLineIsBareOrCarriesText(): void
    {
        pledgeAssertSame(['word' => 'confirm', 'after' => ''], ReviewDirective::extract(ReviewDirective::CONFIRM));
        pledgeAssertSame(['word' => 'dispute', 'after' => ''], ReviewDirective::extract("It never came.\n  Dispute:  "));
        pledgeAssertSame(['word' => 'confirm', 'after' => 'yes'], ReviewDirective::extract('CONFIRM: yes'));
        pledgeAssertSame(null, ReviewDirective::extract('> confirm'));
        pledgeAssertSame(null, ReviewDirective::extract('I confirm it'));
        pledgeAssertSame(null, ReviewDirective::extract('disputed'));
        pledgeAssertSame(true, ReviewDirective::isBare("\n confirm \n"));
        pledgeAssertSame(true, ReviewDirective::isBare('Dispute:'));
        pledgeAssertSame(false, ReviewDirective::isBare("dispute\n\nNothing arrived."));
        pledgeAssertSame(false, ReviewDirective::isBare('confirm: yes'));
    }

    public function testTheAuthorOrAnOperatorConfirmsOrDisputesAPledgersFill(): void
    {
        $author = 'openpgp:' . str_repeat('a', 40);
        $mara = 'openpgp:' . str_repeat('b', 40);
        $op = 'openpgp:' . str_repeat('c', 40);
        $rin = 'openpgp:' . str_repeat('d', 40);
        $post = static fn (string $id, string $body, string $parent, string $by, array $more = []): array => $more + [
            'post_id' => $id, 'thread_id' => 'root', 'parent_id' => $parent, 'body' => $body, 'retracted_at' => null,
            'author_identity_id' => $by, 'author_label' => ['' => '', $author => 'ilyag', $mara => 'mara', $op => 'opal', $rin => 'rin'][$by],
            'attribution_counted' => 1, 'author_is_approved' => 1, 'created_at' => '2026-11-17T05:00:00Z',
        ];
        $posts = [
            $post('root', 'goal: 1000 coffees', '', $author),
            $post('a', 'pledge: 300', 'root', $mara),
            $post('b', 'pledge: 400', 'root', $rin),
            $post('c', 'pledge: 50', 'root', $rin),
            $post('fa', 'filled', 'a', $mara),
            $post('fb', 'filled', 'b', $rin),
            $post('fc', 'filled: 20', 'c', $author),
            $post('ca', 'confirm', 'fa', $author),
            $post('db', "dispute\nNothing arrived.", 'fb', $op),
            $post('r-other', 'confirm', 'fa', $rin),
            $post('r-own', 'dispute', 'fa', $mara),
            $post('r-text', 'confirm: thanks', 'fa', $author),
            $post('r-author-fill', 'dispute', 'fc', $op),
            $post('r-pledge', 'confirm', 'a', $author),
            $post('r-unverified', 'dispute', 'fa', $author, ['attribution_counted' => 0]),
            $post('r-nameless', 'dispute', 'fa', ''),
            $post('r-retracted', 'dispute', 'fa', $author, ['retracted_at' => '2026-11-17T06:00:00Z']),
        ];
        $isOp = static fn (string $id): bool => $id === $op;
        $campaign = PledgeCampaign::fromPosts($posts, 'root', $isOp);

        // a: 300 confirmed; b: disputed, nothing counts; c: 20 by the author.
        pledgeAssertSame('320', $campaign['filled']);
        pledgeAssertSame('320', $campaign['confirmed']);
        pledgeAssertSame([['post_id' => 'fa', 'by' => 'mara', 'role' => 'pledger', 'amount' => '300',
            'review' => 'confirmed', 'review_by' => 'ilyag', 'review_post_id' => 'ca']], $campaign['pledges']['a']['fill_list']);
        pledgeAssertSame([['post_id' => 'fb', 'by' => 'rin', 'role' => 'pledger', 'amount' => '400',
            'review' => 'disputed', 'review_by' => 'opal', 'review_post_id' => 'db']], $campaign['pledges']['b']['fill_list']);
        pledgeAssertSame(false, isset($campaign['pledges']['b']['filled']));
        pledgeAssertSame(['effective' => false, 'note' => 'Not counted: disputed by opal'], $campaign['fills']['fb']);
        pledgeAssertSame([
            'ca' => ['effective' => true, 'note' => ''],
            'db' => ['effective' => true, 'note' => ''],
            'r-other' => ['effective' => false, 'note' => 'Not counted: only the author or an operator can review'],
            'r-own' => ['effective' => false, 'note' => 'Not counted: a fill cannot be reviewed by its own author'],
            'r-text' => ['effective' => false, 'note' => 'Not counted: write “confirm” alone on its line'],
            'r-author-fill' => ['effective' => false, 'note' => "Not counted: not a reply to a pledger's fill"],
            'r-pledge' => ['effective' => false, 'note' => "Not counted: not a reply to a pledger's fill"],
            'r-unverified' => ['effective' => false, 'note' => 'Not counted: waiting for approval'],
            'r-nameless' => ['effective' => false, 'note' => 'Not counted: no name'],
        ], $campaign['reviews']);

        // Without an operator check the operator's dispute does not count, so rin's fill does.
        $plain = PledgeCampaign::fromPosts($posts, 'root');
        pledgeAssertSame('Not counted: only the author or an operator can review', $plain['reviews']['db']['note']);
        pledgeAssertSame('720', $plain['filled']);
        pledgeAssertSame('320', $plain['confirmed']);

        // Retracting the dispute undoes it.
        $posts[8]['retracted_at'] = '2026-11-17T07:00:00Z';
        $undone = PledgeCampaign::fromPosts($posts, 'root', $isOp);
        pledgeAssertSame('720', $undone['filled']);
        pledgeAssertSame(false, isset($undone['pledges']['b']['fill_list'][0]['review']));
    }

    public function testTheLatestReviewDecidesAndADisputedFillLeavesRoom(): void
    {
        $author = 'openpgp:' . str_repeat('a', 40);
        $mara = 'openpgp:' . str_repeat('b', 40);
        $post = static fn (string $id, string $body, string $parent, string $by): array => [
            'post_id' => $id, 'thread_id' => 'root', 'parent_id' => $parent, 'body' => $body, 'retracted_at' => null,
            'author_identity_id' => $by, 'author_label' => $by === $author ? 'ilyag' : 'mara',
            'attribution_counted' => 1, 'author_is_approved' => 1, 'created_at' => '2026-11-17T05:00:00Z',
        ];
        $base = [$post('root', 'goal: 1000', '', $author), $post('a', 'pledge: 300', 'root', $mara), $post('f1', 'filled', 'a', $mara)];

        // Fill, dispute, fill again: the second fill has the whole pledge to fill.
        $again = PledgeCampaign::fromPosts([...$base, $post('d', 'dispute', 'f1', $author), $post('f2', 'filled: 250', 'a', $mara)], 'root');
        pledgeAssertSame('250', $again['filled']);
        pledgeAssertSame('0', $again['confirmed']);
        pledgeAssertSame(['effective' => true, 'note' => ''], $again['fills']['f2']);
        pledgeAssertSame('300', $again['pledges']['a']['fill_list'][0]['amount']);

        // Confirm then dispute, then confirm again: the latest decides.
        $flip = [...$base, $post('c1', 'confirm', 'f1', $author), $post('d1', 'dispute', 'f1', $author)];
        pledgeAssertSame('0', PledgeCampaign::fromPosts($flip, 'root')['filled']);
        $back = PledgeCampaign::fromPosts([...$flip, $post('c2', 'confirm', 'f1', $author)], 'root');
        pledgeAssertSame('300', $back['filled']);
        pledgeAssertSame('300', $back['confirmed']);

        // A capped fill is confirmed for the part that counted.
        $capped = PledgeCampaign::fromPosts([$post('root', 'goal: 1000', '', $author), $post('a', 'pledge: 300', 'root', $mara),
            $post('f1', 'filled: 500', 'a', $mara), $post('c', 'confirm', 'f1', $author)], 'root');
        pledgeAssertSame('300', $capped['confirmed']);
        pledgeAssertSame('Counted 300 of 500', $capped['fills']['f1']['note']);

        // After a dispute the pledge is open again, so it can be withdrawn.
        $withdrawn = PledgeCampaign::fromPosts([...$base, $post('d', 'dispute', 'f1', $author), $post('w', 'withdraw', 'a', $mara)], 'root');
        pledgeAssertSame('withdrawn', $withdrawn['pledges']['a']['state']);
        pledgeAssertSame('0', $withdrawn['pledged']);
        pledgeAssertSame(['effective' => true, 'note' => ''], $withdrawn['withdrawals']['w']);
    }

    public function testThreadPageShowsEachReviewAndWhyAReviewDidNotCount(): void
    {
        [$root, $database, $service] = $this->environment();
        // The pledger is a second, approved key whose posts are written as records and read back by
        // a rebuild, so the member (the campaign author) can review the pledger's fills.
        $pledger = $this->secondIdentity($service);
        $records = [
            'rv-root' => ['', self::MEMBER, "Subject: Review campaign\n", 'goal: 1000 coffees'],
            'rv-first' => ['rv-root', $pledger, '', 'pledge: 300'],
            'rv-second' => ['rv-root', $pledger, '', 'pledge: 400'],
            'rv-kept' => ['rv-first', $pledger, '', 'filled'],
            'rv-lost' => ['rv-second', $pledger, '', 'filled: 100'],
            'rv-confirm' => ['rv-kept', self::MEMBER, '', 'confirm'],
            'rv-dispute' => ['rv-lost', self::MEMBER, '', "dispute\nNothing came."],
            'rv-stray' => ['rv-first', self::MEMBER, '', 'confirm'],
        ];
        $this->writeThread($root, $database, $records);
        $this->approveKeys($database, $pledger);
        $page = fn (): string => $this->render(new Application(dirname(__DIR__), $root, $database), '/threads/rv-root');

        putenv('FORUM_PLEDGES_ENABLED=1');
        try {
            $html = $page();
            pledgeAssertContains('2 pledges · 300 delivered</p>', $html);
            pledgeAssertContains('data-pledge-state="fill"><a href="/posts/rv-kept">Delivered by ', $html);
            pledgeAssertContains(' · <a href="/posts/rv-confirm">confirmed by ', $html);
            pledgeAssertContains('data-pledge-state="fill-disputed"><s><a href="/posts/rv-lost">Delivered by ', $html);
            pledgeAssertContains('</a> · 100</s> · <a href="/posts/rv-dispute">disputed by ', $html);
            pledgeAssertContains('data-pledge-state="fill-uncounted"><span class="meta">Not counted: disputed by ', $html);
            pledgeAssertContains('data-pledge-state="review-uncounted"><span class="meta">Not counted: not a reply to a pledger&#039;s fill</span>', $html);
        } finally {
            putenv('FORUM_PLEDGES_ENABLED');
        }

        pledgeAssertNotContains('pledge-amount', $page());
    }

    public function testReviewButtonsShowForTheAuthorOnUnreviewedPledgerFillsAndABareConfirmFolds(): void
    {
        [$root, $database, $service] = $this->environment();
        $pledger = $this->secondIdentity($service);
        $this->writeThread($root, $database, [
            'rb-root' => ['', self::MEMBER, "Subject: Button campaign\n", 'goal: 1000 coffees'],
            'rb-a' => ['rb-root', $pledger, '', 'pledge: 300'],
            'rb-b' => ['rb-root', $pledger, '', 'pledge: 200'],
            'rb-c' => ['rb-root', self::MEMBER, '', 'pledge: 100'],
            'rb-open' => ['rb-a', $pledger, '', 'filled'],
            'rb-done' => ['rb-b', $pledger, '', 'filled'],
            'rb-confirm' => ['rb-done', self::MEMBER, '', 'confirm'],
            'rb-own' => ['rb-c', self::MEMBER, '', 'filled'],
        ]);
        $this->approveKeys($database, $pledger);
        $page = fn (): string => $this->render(new Application(dirname(__DIR__), $root, $database), '/threads/rb-root');
        $oldCookies = $_COOKIE;

        putenv('FORUM_PLEDGES_ENABLED=1');
        try {
            $_COOKIE = [];
            $html = $page();
            pledgeAssertNotContains('>Confirm</button>', $html);
            // The bare confirm folds, and so does the bare fill it answered; the open fill folds too.
            pledgeAssertNotContains('data-post-id="rb-confirm"', $html);
            pledgeAssertNotContains('data-post-id="rb-done"', $html);
            pledgeAssertNotContains('data-post-id="rb-open"', $html);

            // The member is the campaign author: buttons on the open pledger fill only, not on the
            // confirmed one or the member's own fill.
            $_COOKIE = ['identity_hint' => self::MEMBER];
            $html = $page();
            pledgeAssertSame(1, substr_count($html, '>Confirm</button>'));
            pledgeAssertSame(1, substr_count($html, '>Dispute</button>'));
            pledgeAssertSame(2, substr_count($html, '<input type="hidden" name="parent_id" value="rb-open">'));
            pledgeAssertContains('<input type="hidden" name="body" value="confirm">', $html);
            pledgeAssertContains('<input type="hidden" name="body" value="dispute">', $html);
            pledgeAssertContains('data-failure="Review failed. Try again."', $html);

            // The pledger sees no review buttons on their own fills.
            $_COOKIE = ['identity_hint' => $pledger];
            pledgeAssertNotContains('>Confirm</button>', $page());
        } finally {
            $_COOKIE = $oldCookies;
            putenv('FORUM_PLEDGES_ENABLED');
        }
    }

    public function testTotalsSitUnderTheTitleWithAStatusOnceTheGoalIsReached(): void
    {
        [$root, $database, $service] = $this->environment();
        $thread = $this->thread($service, 'Coffee for the hack weekend', "We need coffee.\ngoal: 100 coffees");
        $page = fn (): string => $this->render(new Application(dirname(__DIR__), $root, $database), '/threads/' . rawurlencode($thread));
        putenv('FORUM_PLEDGES_ENABLED=1');
        try {
            $open = $page();
            $pledge = $this->reply($service, $thread, "pledge: 120\n\nFrom the lab.");
            $reached = $page();
            $this->replyTo($service, $thread, $pledge, 'filled: 30');
            $part = $page();
            $this->replyTo($service, $thread, $pledge, 'filled');
            $complete = $page();
        } finally {
            putenv('FORUM_PLEDGES_ENABLED');
        }
        // The block comes before the pitch, straight after the title.
        $title = strpos($open, 'Coffee for the hack weekend</h1>');
        $totals = strpos($open, 'data-pledge-totals');
        $pitch = strpos($open, 'We need coffee.');
        pledgeAssertSame(true, $title !== false && $totals !== false && $pitch !== false && $title < $totals && $totals < $pitch);
        pledgeAssertContains('data-pledge-status="open"', $open);
        pledgeAssertNotContains('data-role="pledge-status"', $open);
        pledgeAssertNotContains('pledge-bar__delivered', $open);
        pledgeAssertContains('data-pledge-status="goal_reached"', $reached);
        pledgeAssertContains('<span class="pledge-status" data-role="pledge-status">Goal reached</span>', $reached);
        pledgeAssertContains('<span class="pledge-bar__delivered" style="width: 25%"></span>', $part);
        pledgeAssertContains('aria-label="120 of 100 coffees pledged, 30 delivered"', $part);
        // The author filled it, so delivered and confirmed are equal and "confirmed" is not repeated.
        pledgeAssertContains('1 pledge · 30 delivered</p>', $part);
        pledgeAssertContains('<span class="pledge-status" data-role="pledge-status">Complete</span>', $complete);
        pledgeAssertContains('1 pledge · 120 delivered</p>', $complete);
        // Each pledge card leads with its amount and one status, then the note.
        pledgeAssertContains('<strong>120</strong> coffees <span class="pledge-status" data-role="pledge-card-status">Open</span>', $reached);
        pledgeAssertSame(true, strpos($reached, 'data-role="pledge-card-status"') < strpos($reached, 'From the lab.'));
        pledgeAssertContains('data-role="pledge-card-status">Part delivered: 30 of 120</span>', $part);
        pledgeAssertContains('data-role="pledge-card-status">Delivered</span>', $complete);
    }

    public function testPledgeLinesLeaveOutOnlyTheLineTheReaderUsed(): void
    {
        pledgeAssertSame("I'll order these myself.", PledgeLines::hide("pledge: 300\n\nI'll order these myself.", PledgeLines::PLEDGE));
        pledgeAssertSame("We need coffee.\nPledge any number.", PledgeLines::hide("We need coffee.\ngoal: 1000 coffees\nPledge any number.", PledgeLines::GOAL));
        pledgeAssertSame("Three days.\n\nPledge any number.", PledgeLines::hide("Three days.\n\ngoal: 1000 coffees\n\nPledge any number.", PledgeLines::GOAL));
        // A quoted line is never the one removed, as the readers skip it too.
        pledgeAssertSame("> pledge: 50\nAgreed.", PledgeLines::hide("> pledge: 50\npledge: 20\nAgreed.", PledgeLines::PLEDGE));
        // Only the first match goes; a body with no such line is unchanged.
        pledgeAssertSame("filled: 5", PledgeLines::hide("filled\nfilled: 5", PledgeLines::FILLED));
        pledgeAssertSame("Just talking.", PledgeLines::hide("Just talking.", PledgeLines::PLEDGE));
        pledgeAssertSame(true, PledgeLines::isOnlyLine("filled: 5", PledgeLines::FILLED));
        pledgeAssertSame(true, PledgeLines::isOnlyLine("\nconfirm\n", PledgeLines::REVIEW));
        pledgeAssertSame(false, PledgeLines::isOnlyLine("filled\n\nArrived Tuesday.", PledgeLines::FILLED));
        pledgeAssertSame(false, PledgeLines::isOnlyLine("", PledgeLines::WITHDRAW));
    }

    public function testThreadPageKeepsTheLinesThatDidNotCount(): void
    {
        [$root, $database, $service] = $this->environment();
        $thread = $this->thread($service, 'Kept lines', 'goal: 100 coffees');
        $pledge = $this->reply($service, $thread, 'pledge: 30');
        $quiet = $this->reply($service, $thread, 'pledge: 20');
        // Text after a withdraw or review line stops it counting, so the line and its reason stay.
        $this->replyTo($service, $thread, $pledge, 'withdraw: 10');
        $this->replyTo($service, $thread, $pledge, 'confirm: yes');
        putenv('FORUM_PLEDGES_ENABLED=1');
        try {
            $on = $this->render(new Application(dirname(__DIR__), $root, $database), '/threads/' . rawurlencode($thread));
        } finally {
            putenv('FORUM_PLEDGES_ENABLED');
        }
        pledgeAssertContains('withdraw: 10', $on);
        pledgeAssertContains('confirm: yes', $on);
        // A pledge with no note shows its amount and no empty text box.
        pledgeAssertNotContains('pledge: 20', $on);
        $card = substr($on, (int) strpos($on, 'data-post-id="' . $quiet . '"'), 1500);
        pledgeAssertNotContains('<div class="body"></div>', $card);
    }

    public function testAPledgeFromAKeyNotYetApprovedWaitsAndCountsOnceApproved(): void
    {
        [$root, $database, $service] = $this->environment();
        $thread = $this->thread($service, 'Approval campaign', 'goal: 100 coffees');
        $this->reply($service, $thread, 'pledge: 30');
        $newcomer = $this->secondIdentity($service);
        $this->reply($service, $thread, "pledge: 20\n\nCounting me in.", $newcomer);
        $page = fn (): string => $this->render(new Application(dirname(__DIR__), $root, $database), '/threads/' . rawurlencode($thread));

        putenv('FORUM_PLEDGES_ENABLED=1');
        try {
            $waiting = $page();
            $this->approveKeys($database, $newcomer);
            $approved = $page();
        } finally {
            putenv('FORUM_PLEDGES_ENABLED');
        }
        pledgeAssertContains('<strong>30</strong> of 100 coffees pledged', $waiting);
        pledgeAssertContains('1 pledge</p>', $waiting);
        pledgeAssertContains('data-pledge-state="waiting"><strong>20</strong> coffees · <span class="meta">Waiting for approval</span>', $waiting);
        pledgeAssertContains('Counting me in.', $waiting);
        // Approval is read when the page is drawn: nothing is rewritten, and the pledge now counts.
        pledgeAssertContains('<strong>50</strong> of 100 coffees pledged', $approved);
        pledgeAssertContains('2 pledges</p>', $approved);
        pledgeAssertNotContains('Waiting for approval', $approved);
    }

    private function environment(): array
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-pledge-repo-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $root);
        foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git -c commit.gpgsign=false commit -m init'] as $command) {
            $this->run($root, $command);
        }
        // The read model sits in its own directory so its rebuild lock is not shared with other tests.
        $databaseDirectory = sys_get_temp_dir() . '/forum-rewrite-pledge-db-' . bin2hex(random_bytes(6));
        mkdir($databaseDirectory, 0777, true);
        $database = $databaseDirectory . '/read-model.sqlite3';
        $artifacts = sys_get_temp_dir() . '/forum-rewrite-pledge-public-' . bin2hex(random_bytes(6));
        mkdir($artifacts, 0777, true);
        $this->render(new Application(dirname(__DIR__), $root, $database, $artifacts), '/');
        $service = new LocalWriteService($root, $database, $artifacts, new CanonicalRecordRepository($root), featureFlags: FeatureFlagEvaluator::forRepository($root));

        return [$root, $database, $service, $artifacts];
    }

    /**
     * Writes one thread as post records, root first, and rebuilds the read model. Lets a test use a
     * second key, which the fixture does not have. Each record is [parent, author, extra headers, body].
     *
     * @param array<string, array{0: string, 1: string, 2: string, 3: string}> $records
     */
    private function writeThread(string $root, string $database, array $records): void
    {
        $rootId = (string) array_key_first($records);
        $minute = 0;
        foreach ($records as $id => [$parent, $author, $headers, $body]) {
            $minute++;
            $thread = $parent === '' ? '' : "Thread-ID: {$rootId}\nParent-ID: {$parent}\n";
            file_put_contents($root . '/records/posts/' . $id . '.txt', "Post-ID: {$id}\nCreated-At: 2026-11-17T06:" . sprintf('%02d', $minute)
                . ":00Z\nBoard-Tags: general\n{$thread}{$headers}Author-Identity-ID: {$author}\n\n{$body}\n");
        }
        (new \ForumRewrite\ReadModel\ReadModelBuilder($root, $database, new CanonicalRecordRepository($root), 'pledge_review_test'))->rebuild();
    }

    private function thread(LocalWriteService $service, string $subject, string $body): string
    {
        return (string) $service->createThread(['board_tags' => 'general', 'subject' => $subject, 'body' => $body, 'author_identity_id' => self::MEMBER])['thread_id'];
    }

    private function reply(LocalWriteService $service, string $threadId, string $body, ?string $author = self::MEMBER): string
    {
        $input = ['thread_id' => $threadId, 'parent_id' => $threadId, 'body' => $body];
        if ($author !== null) {
            $input['author_identity_id'] = $author;
        }

        return (string) $service->createReply($input)['post_id'];
    }

    private function replyTo(LocalWriteService $service, string $threadId, string $parentId, string $body, ?string $author = self::MEMBER): string
    {
        $input = ['thread_id' => $threadId, 'parent_id' => $parentId, 'body' => $body];
        if ($author !== null) {
            $input['author_identity_id'] = $author;
        }

        return (string) $service->createReply($input)['post_id'];
    }

    /** Links a second key from the signature fixtures, so a test has two real people. */
    private function secondIdentity(LocalWriteService $service): string
    {
        return (string) $service->linkIdentity(['public_key' => (string) file_get_contents(__DIR__ . '/fixtures/openpgp_signature/reaction-public-key.asc')])['identity_id'];
    }

    /** Marks keys approved in the read model's profiles, as an approval would. */
    private function approveKeys(string $database, string ...$identityIds): void
    {
        $statement = (new PDO('sqlite:' . $database))->prepare('UPDATE profiles SET is_approved = 1 WHERE identity_id = ?');
        foreach ($identityIds as $identityId) {
            $statement->execute([$identityId]);
        }
    }

    private function render(Application $application, string $path): string
    {
        [$method, $path] = str_starts_with($path, 'POST ') ? ['POST', substr($path, 5)] : ['GET', $path];
        ob_start();
        $application->handle($method, $path);

        return (string) ob_get_clean();
    }

    private function run(string $directory, string $command): void
    {
        exec('cd ' . escapeshellarg($directory) . ' && ' . $command . ' 2>&1', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
        foreach ($items as $item) {
            $target = $destination . '/' . $items->getSubPathName();
            $item->isDir() ? (is_dir($target) || mkdir($target, 0777, true)) : copy($item->getPathname(), $target);
        }
    }
}

function pledgeAssertSame(mixed $expected, mixed $actual): void
{
    if ($expected !== $actual) {
        throw new RuntimeException('Failed asserting identical values. Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function pledgeAssertContains(string $needle, string $haystack): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output contains: ' . $needle);
    }
}

function pledgeAssertNotContains(string $needle, string $haystack): void
{
    if (str_contains($haystack, $needle)) {
        throw new RuntimeException('Failed asserting that output does not contain: ' . $needle);
    }
}
