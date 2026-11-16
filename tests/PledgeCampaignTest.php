<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Host\StaticArtifactBuilder;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\GoalDirective;
use ForumRewrite\Support\PledgeAmount;
use ForumRewrite\Support\PledgeDirective;
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
            'author_identity_id' => self::MEMBER, 'attribution_counted' => 1,
        ];
        $posts = [
            $post('root', "Three days, a hundred people.\ngoal: 1000 coffees"),
            $post('a', "pledge: 300\n\nI'll order these myself."),
            $post('b', 'pledge: 12.50 Coffee'),
            $post('c', 'Does decaf count?'),
            $post('d', 'pledge: 1,000'),
            $post('unverified', 'pledge: 50', ['attribution_counted' => 0]),
            $post('unnamed', 'pledge: 60', ['author_identity_id' => null]),
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
        pledgeAssertSame(['a', 'b', 'd', 'unverified', 'unnamed', 'words', 'unit'], array_keys($campaign['pledges']));
        pledgeAssertSame(['state' => 'counted', 'amount' => '12.5', 'note' => ''], $campaign['pledges']['b']);
        pledgeAssertSame(['state' => 'unverified', 'amount' => '50', 'note' => 'Not counted: unverified'], $campaign['pledges']['unverified']);
        pledgeAssertSame(['state' => 'unnamed', 'amount' => '60', 'note' => 'Not counted: no name'], $campaign['pledges']['unnamed']);
        pledgeAssertSame(['state' => 'unreadable', 'amount' => null, 'note' => 'Not counted: “a few” is not a number'], $campaign['pledges']['words']);
        pledgeAssertSame('Not counted: “300 dollars” is not in coffees', $campaign['pledges']['unit']['note']);

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
        pledgeAssertContains('data-pledge-state="counted"><strong>300</strong> coffees</p>', $on);
        pledgeAssertContains('data-pledge-state="unverified"><strong>250</strong> coffees · <span class="meta">Not counted: unverified</span>', $on);
        pledgeAssertContains('data-pledge-state="unnamed"><strong>40</strong> coffees · <span class="meta">Not counted: no name</span>', $on);
        pledgeAssertContains('data-pledge-state="unreadable"><span class="meta">Not counted: “a few” is not a number</span>', $on);
        // The lines stay in the posts as written.
        pledgeAssertContains('goal: 1000 coffees', $on);
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

    /** @return array{string,string,LocalWriteService,string} */
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
