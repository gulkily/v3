<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Feedback\BacklogClaims;
use ForumRewrite\Feedback\FeedbackBacklog;
use ForumRewrite\Http\BacklogPageController;
use ForumRewrite\SiteProfileRegistry;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\Support\FeatureFlags\FeatureFlagRegistry;
use ForumRewrite\Write\LocalWriteService;

/**
 * Tools → Backlog reads the checkout's own docs/feedback/items, so these tests
 * run against the real items and assert only what holds for any set of them.
 */
final class BacklogPageTest
{
    public function testFlagOffHidesThePageAndItsToolsEntry(): void
    {
        $this->withSite(false, function (Application $application): void {
            assertStringContains('Not Found', $this->render($application, '/backlog'));
            assertStringNotContains('href="/backlog"', $this->render($application, '/tools/'));
        });
    }

    public function testSignedOutVisitorSeesTheDefaultList(): void
    {
        $this->withSite(true, function (Application $application): void {
            assertStringContains('href="/backlog"', $this->render($application, '/tools/'), 'Tools links to the backlog.');
            $html = $this->render($application, '/backlog');
            $items = BacklogPageController::load(dirname(__DIR__));
            $shown = FeedbackBacklog::sort(FeedbackBacklog::select($items, 'can-start', 'all'), 'salient', 'newest');
            assertTrue(count($shown) > 0);
            assertStringContains(count($shown) . ' of ' . count(FeedbackBacklog::select($items, 'open', 'all')) . ' open', $html);
            assertSame(count($shown), substr_count($html, 'class="backlog-row"'));
            assertTrue(strpos($html, $shown[0]->id()) < strpos($html, $shown[1]->id()), 'Rows follow the shared order.');
            assertStringContains('/assets/backlog.', $html, 'The page carries its own stylesheet.');
            assertStringContains('aria-current="true">Salient<span class="visually-hidden">, newest first</span>', $html);
            assertStringContains('aria-current="true">Can start</a>', $html);
            assertStringNotContains('data-workflow="blocked"', $html);
            assertStringNotContains('data-workflow="done"', $html);
        });
    }

    public function testSortAndFilterLinksUseReadableWords(): void
    {
        $this->withSite(true, function (Application $application): void {
            $html = $this->render($application, '/backlog');
            assertStringContains('href="/backlog?order=oldest"', $html, 'The active sort flips its order.');
            assertStringContains('href="/backlog?sort=effort"', $html);
            assertStringContains('href="/backlog?show=waiting"', $html);
            assertStringContains('href="/backlog?kind=bug"', $html);
            assertStringContains('href="/backlog.txt"', $html);

            $bugs = $this->render($application, '/backlog?sort=effort&order=oldest&show=waiting&kind=bug');
            assertStringContains('href="/backlog?sort=effort&amp;show=waiting&amp;kind=bug"', $bugs, 'Effort flips back to newest; other words stay.');
            assertStringContains('href="/backlog?sort=impact&amp;show=waiting&amp;kind=bug"', $bugs, 'Another sort starts newest first.');
            assertStringContains('href="/backlog?sort=effort&amp;order=oldest&amp;kind=bug"', $bugs, 'Can start drops the show word.');
            assertStringContains('aria-current="true">Waiting on you</a>', $bugs);
            assertStringNotContains('data-workflow="untriaged"', $bugs);
            preg_match_all('/href="(\/backlog[^"]*)"/', $bugs, $links);
            foreach ($links[1] as $link) {
                assertSame(1, preg_match('/^\/backlog(\.txt)?(\?[a-z=&;-]+)?$/', $link), 'Readable words only: ' . $link);
            }
        });
    }

    public function testUnknownWordsFallBackWithANotice(): void
    {
        $this->withSite(true, function (Application $application): void {
            $html = $this->render($application, '/backlog?sort=sideways&show=<b>');
            assertStringContains('Not understood, so the default is shown: sort=sideways, show=&lt;b&gt;', $html);
            assertStringContains('aria-current="true">Salient', $html);
            assertStringNotContains('<b>', $html);

            $planned = $this->render($application, '/backlog?show=planned&kind=other');
            assertStringContains('aria-current="true">Planned</a>', $planned, 'A workflow word without a standing link shows while active.');
        });
    }

    public function testStartPageShowsThePromptForThePickedItems(): void
    {
        $this->withSite(true, function (Application $application): void {
            $items = BacklogPageController::load(dirname(__DIR__));
            $open = FeedbackBacklog::select($items, 'can-start', 'all');
            $closed = array_values(array_filter($items, static fn ($item): bool => $item->isClosed()));
            [$a, $b] = [$open[0]->id(), $open[1]->id()];

            $list = $this->render($application, '/backlog');
            assertStringContains('<form class="backlog-form" method="get" action="/backlog/start"', $list);
            assertStringContains('type="checkbox" name="items[]" value="' . $a . '"', $list);
            assertStringContains('<button type="submit">Start</button>', $list);

            $one = $this->render($application, '/backlog/start?items=' . $a);
            assertStringContains('Please work on this v3 backlog item as one Railspike cycle, one-shot or staged as AGENTS.md says.', $one);
            assertStringContains('- docs/feedback/items/' . $a . '.txt', $one);
            assertStringContains(htmlspecialchars(\ForumRewrite\Feedback\BacklogPrompt::ACCESS), $one, 'The prompt asks for a clone and a pull request, not patch files.');
            assertStringNotContains('belong in one pull request', $one, 'One item: nothing to group.');
            assertStringContains('aria-current="true">Agent decides</a>', $one, 'The agent decides by default.');
            assertSame(true, strpos($one, '>Agent decides</a>') < strpos($one, '>One-shot</a>') && strpos($one, '>One-shot</a>') < strpos($one, '>Staged cycle</a>'), 'Agent decides, One-shot, Staged cycle, in that order.');
            assertStringContains('href="/backlog/start?items=' . $a . '&amp;mode=one-shot"', $one);
            assertStringContains('href="/backlog/start?items=' . $a . '&amp;mode=staged"', $one);

            $oneShot = $this->render($application, '/backlog/start?items=' . $a . '&mode=one-shot');
            assertStringContains('Please work on this v3 backlog item as one Railspike one-shot cycle.', $oneShot, 'An explicit one-shot address still works.');
            assertStringContains('aria-current="true">One-shot</a>', $oneShot);
            assertStringContains('href="/backlog/start?items=' . $a . '"', $oneShot, 'Agent decides links to the address without a mode.');
            assertStringContains('href="/backlog/start.txt?items=' . $a . '"', $one);
            assertStringContains('class="copy-prompt"', $one, 'The shared prompt partial with Copy.');

            $two = $this->render($application, '/backlog/start?items=' . $a . ',' . $b . '&mode=staged');
            assertStringContains('Please work on these 2 v3 backlog items together as one staged Railspike cycle.', $two);
            assertStringContains(htmlspecialchars(\ForumRewrite\Feedback\BacklogPrompt::TOGETHER), $two, 'Several items: say so before building if they do not belong together.');
            assertStringContains('aria-current="true">Staged cycle</a>', $two);
            exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && ./v3 feedback start ' . $a . ' ' . $b . ' --mode=staged 2>/dev/null', $command);
            assertStringContains(htmlspecialchars(implode("\n", $command)), $two, 'The page and the command give the same prompt.');

            $mixed = $this->render($application, '/backlog/start?items=' . $a . ',' . $closed[0]->id() . ',PF-00000000-F99');
            assertStringContains('Left out, already closed: ' . $closed[0]->id(), $mixed);
            assertStringContains('Left out, no such item: PF-00000000-F99', $mixed);
            assertStringContains('Please work on this v3 backlog item', $mixed);
            assertStringContains('href="/backlog/start.txt?items=' . $a . '"', $mixed, 'The text link names only what starts.');

            $none = $this->render($application, '/backlog/start?items=' . $closed[0]->id());
            assertStringContains('Nothing to start.', $none);
            assertStringNotContains('class="copy-prompt', $none);
        });
    }

    public function testStartRequestsAreRedirectedToTheTidyAddress(): void
    {
        $tidy = '/backlog/start?items=PF-20261101-F01,PF-20261101-B02';
        assertSame(null, BacklogPageController::startRedirect(['items' => 'PF-20261101-F01,PF-20261101-B02'], $tidy));
        assertSame($tidy, BacklogPageController::startRedirect(['items' => ['PF-20261101-F01', 'PF-20261101-B02']], '/backlog/start?items%5B%5D=PF-20261101-F01&items%5B%5D=PF-20261101-B02'));
        assertSame($tidy . '&mode=staged', BacklogPageController::startRedirect(['items' => 'PF-20261101-F01,PF-20261101-B02,PF-20261101-F01', 'mode' => 'staged'], '/x'));
        assertSame($tidy . '&mode=one-shot', BacklogPageController::startRedirect(['items' => ['PF-20261101-F01', 'PF-20261101-B02'], 'mode' => 'one-shot'], '/x'), 'One-shot is kept: it is no longer the default.');
        assertSame($tidy, BacklogPageController::startRedirect(['items' => 'PF-20261101-F01,PF-20261101-B02', 'mode' => 'auto'], '/x'), 'The default mode stays out of the address.');
        assertSame($tidy, BacklogPageController::startRedirect(['items' => 'PF-20261101-F01,PF-20261101-B02', 'mode' => 'fast'], '/x'), 'An unknown mode is dropped.');
        assertSame('/backlog', BacklogPageController::startRedirect([], '/backlog/start'), 'Nothing picked goes back to the list.');
        assertSame('/backlog', BacklogPageController::startRedirect(['items' => '<script>'], '/backlog/start?items=x'));

        $this->withSite(true, function (Application $application): void {
            $page = $this->render($application, '/backlog/start?items%5B%5D=PF-20261101-F01');
            assertStringContains('/backlog/start?items=PF-20261101-F01', $page, 'The redirect page names the tidy address.');
            assertStringNotContains('class="copy-prompt', $page);
            assertStringContains('Pick at least one item to start.', $this->render($application, '/backlog/start'));
        });
        $this->withSite(false, function (Application $application): void {
            assertStringContains('Not Found', $this->render($application, '/backlog/start?items=PF-20261101-F01'));
        });
    }

    public function testTextRoutesGiveTheSameBytesAsTheCommands(): void
    {
        $this->withSite(true, function (Application $application): void {
            $v3 = 'cd ' . escapeshellarg(dirname(__DIR__)) . ' && ./v3 feedback ';
            foreach ([['', ''], ['?sort=effort&kind=bug', '--sort=effort --kind=bug'], ['?sort=entered&order=oldest&show=waiting', '--sort=entered --order=oldest --show=waiting']] as [$query, $options]) {
                $command = (string) shell_exec($v3 . 'list ' . $options . ' 2>/dev/null');
                assertTrue(str_starts_with($command, 'Sort: '));
                assertSame($command, $this->raw($application, '/backlog.txt' . $query), 'Same list for: ' . $query);
            }
            assertStringContains("Sort: salient\n", $this->raw($application, '/backlog.txt?sort=sideways'), 'An unknown word falls back.');

            $items = BacklogPageController::load(dirname(__DIR__));
            $open = FeedbackBacklog::select($items, 'can-start', 'all');
            [$a, $b] = [$open[0]->id(), $open[1]->id()];
            $command = (string) shell_exec($v3 . 'start ' . $a . ' ' . $b . ' --mode=staged 2>/dev/null');
            assertSame($command, $this->raw($application, '/backlog/start.txt?items=' . $a . ',' . $b . '&mode=staged'));
            assertSame($command, $this->raw($application, '/backlog/start.txt?items[]=' . $a . '&items[]=' . $b . '&mode=staged'));
            foreach (['' => '', '&mode=auto' => '', '&mode=one-shot' => ' --mode=one-shot'] as $word => $option) {
                $command = (string) shell_exec($v3 . 'start ' . $a . $option . ' 2>/dev/null');
                assertTrue(str_starts_with($command, 'Please work on '));
                assertSame($command, $this->raw($application, '/backlog/start.txt?items=' . $a . $word), 'Same prompt for: ' . ($word === '' ? 'no mode' : $word));
            }
            assertStringContains('Name at least one item', $this->raw($application, '/backlog/start.txt'));
            assertStringContains('Name at least one item', $this->raw($application, '/backlog/start.txt?items=' . $a . '&mode=fast'));
            assertStringContains('Nothing to start: no open item among PF-00000000-F99.', $this->raw($application, '/backlog/start.txt?items=PF-00000000-F99'));
            assertStringContains('- [Backlog](/backlog.txt)', $this->raw($application, '/llms.txt'));
        });
        $this->withSite(false, function (Application $application): void {
            assertStringContains('Not Found', $this->raw($application, '/backlog.txt'));
            assertStringContains('Not Found', $this->raw($application, '/backlog/start.txt?items=PF-20261101-F01'));
            assertStringNotContains('/backlog.txt', $this->raw($application, '/llms.txt'));
        });
    }

    public function testCheckoutWithoutItemsGivesAnEmptyList(): void
    {
        $root = sys_get_temp_dir() . '/v3-backlog-empty-' . bin2hex(random_bytes(4));
        mkdir($root);
        try {
            assertSame([], BacklogPageController::load($root));
        } finally {
            rmdir($root);
        }
    }

    /** @param callable(Application): void $test */
    public function testWithNoClaimsThreadNothingAboutClaimsIsShownOrPrinted(): void
    {
        $this->withSite(true, function (Application $application): void {
            $html = $this->render($application, '/backlog?claimed=hide');
            assertStringNotContains('Hide claimed', $html);
            assertStringNotContains('data-role="backlog-claim"', $html);
            assertStringNotContains('Not understood', $html, 'The word is known everywhere; it just hides nothing here.');
            $text = $this->raw($application, '/backlog.txt');
            assertSame(0, preg_match(self::CLAIM_KEYS, $text), 'No claim line of any kind.');
            assertSame($text, $this->raw($application, '/backlog.txt?claimed=hide'));
        });
    }

    public function testRowsShowWhoHoldsAnItemAndHideClaimedLeavesHeldRowsOut(): void
    {
        $this->withClaims(function (callable $application, LocalWriteService $service, string $thread, array $ids): void {
            [$held, $guest, $free] = $ids;
            // A claim posted through the write service is on the next request: nothing is rebuilt or deployed.
            $started = hrtime(true);
            $claim = $this->reply($service, $thread, "claim: {$held}\n\nBranch fix/example.", self::MEMBER);
            $html = $this->render($application(), '/backlog');
            assertTrue((hrtime(true) - $started) / 1e9 < 10, 'Post, then read, within one request each.');
            $this->reply($service, $thread, "claim: {$guest}", null);
            $html = $this->render($application(), '/backlog');

            $row = fn (string $page, string $id): string => (string) (preg_match('/<li class="backlog-row"[^>]*>\s*<input[^>]*value="' . preg_quote($id, '/') . '".*?<\/li>/s', $page, $m) === 1 ? $m[0] : '');
            assertSame(1, preg_match('/data-role="backlog-claim">Claimed by [^<·]+<\/span>/', $row($html, $held)), $row($html, $held));
            assertStringContains('data-role="backlog-claim">Claimed by a guest · not counted</span>', $row($html, $guest));
            assertStringNotContains('data-role="backlog-claim"', $row($html, $free));
            assertStringContains('<a class="nav-link" href="/backlog?claimed=hide">Hide claimed</a>', $html);

            $hidden = $this->render($application(), '/backlog?claimed=hide&kind=bug');
            assertSame('', $row($this->render($application(), '/backlog?claimed=hide'), $held), 'A counted claim hides the row.');
            assertTrue($row($this->render($application(), '/backlog?claimed=hide'), $guest) !== '', 'A guest claim does not.');
            assertStringContains('<a class="nav-link is-active" href="/backlog?kind=bug" aria-current="true">Hide claimed</a>', $hidden, 'The active word turns itself off and keeps the others.');
            assertStringContains('href="/backlog?sort=effort&amp;kind=bug&amp;claimed=hide"', $hidden, 'Sort links keep the word.');

            // Released by its holder, the row is free again.
            $this->reply($service, $thread, 'release', self::MEMBER, $claim);
            assertStringNotContains('data-role="backlog-claim"', $row($this->render($application(), '/backlog'), $held));
        });
    }

    public function testTextListCarriesClaimLines(): void
    {
        $this->withClaims(function (callable $application, LocalWriteService $service, string $thread, array $ids): void {
            [$held, $guest, $free] = $ids;
            $claim = $this->reply($service, $thread, "claim: {$held}, {$guest}", self::MEMBER);
            $this->reply($service, $thread, "claim: {$guest}", null);
            $text = $this->raw($application(), '/backlog.txt');

            assertStringContains("Start-Command: ./v3 feedback start <ID>...\nClaimed: show\nClaims-Address: /backlog/claims.txt?items=<ID>,<ID>\n\n", $text);
            $block = fn (string $all, string $id): string => (string) (preg_match('/^ID: ' . preg_quote($id, '/') . '\n.*?(?=\n\n|\z)/ms', $all, $m) === 1 ? $m[0] : '');
            assertSame(1, preg_match('/\nFile: [^\n]+\nClaim-State: held\nClaimed-By: [^\n]+\nClaimed-Since: [0-9T:Z-]+\nClaim-Post: ' . preg_quote($claim, '/') . '$/', $block($text, $held)), $block($text, $held));
            assertStringContains("\nGuest-Claims: 1", $block($text, $guest));
            assertSame(0, preg_match(self::CLAIM_KEYS, $block($text, $free)));

            $hidden = $this->raw($application(), '/backlog.txt?claimed=hide');
            assertStringContains("\nClaimed: hide\n", $hidden);
            assertSame('', $block($hidden, $held));
            assertTrue($block($hidden, $free) !== '');
        });
    }

    public function testStartPageShowsHoldersAndOffersClaimToARecognisedViewer(): void
    {
        $this->withClaims(function (callable $application, LocalWriteService $service, string $thread, array $ids): void {
            [$mine, $theirs, $free] = $ids;
            $second = (string) $service->linkIdentity(['public_key' => (string) file_get_contents(__DIR__ . '/fixtures/openpgp_signature/reaction-public-key.asc')])['identity_id'];
            (new PDO('sqlite:' . $this->database))->prepare('UPDATE profiles SET is_approved = 1 WHERE identity_id = ?')->execute([$second]);
            $this->reply($service, $thread, "claim: {$mine}", self::MEMBER);
            $this->reply($service, $thread, "claim: {$theirs}", $second);
            $path = "/backlog/start?items={$mine},{$theirs},{$free}";
            $oldCookies = $_COOKIE;
            try {
                // Nobody recognised: holders and "Not claimed" for everyone, no form.
                $_COOKIE = [];
                $anyone = $this->render($application(), $path);
                assertSame(1, preg_match('/data-claim-id="' . preg_quote($mine, '/') . '">Claimed by [^<·]+ since [A-Z][a-z]{2} \d{1,2}<\/span>/', $anyone), 'The holder and the date.');
                assertStringContains('data-claim-id="' . $free . '">Not claimed</span>', $anyone);
                assertStringNotContains('data-role="backlog-claim-form"', $anyone);
                assertStringContains('/assets/backlog_claim.', $anyone);
                assertStringContains('data-copy-prompt-source', $anyone, 'The prompt is there whatever the claims say.');

                // The member holds one item already; the form claims the other two, the held one included (Start warns, never refuses).
                $_COOKIE = ['identity_hint' => self::MEMBER];
                $member = $this->render($application(), $path);
                assertStringContains('<form class="backlog-claim-form requires-script" method="post" action="/compose/reply" data-role="backlog-claim-form" data-claim-ids="' . $theirs . ',' . $free . '">', $member);
                assertStringContains('<input type="hidden" name="thread_id" value="' . $thread . '">', $member);
                assertStringContains('<input type="hidden" name="parent_id" value="' . $thread . '">', $member);
                assertStringContains('<input type="hidden" name="body" value="claim: ' . $theirs . ', ' . $free . '">', $member);

                // Once they have a claim on every picked item there is nothing left to claim: a reload offers no form.
                $this->reply($service, $thread, "claim: {$theirs}, {$free}", self::MEMBER);
                $after = $this->render($application(), $path);
                assertStringNotContains('data-role="backlog-claim-form"', $after);
                assertSame(1, preg_match('/data-claim-id="' . preg_quote($theirs, '/') . '">Claimed by [^<·]+ since [^<·]+ · Also claimed by [^<·]+<\/span>/', $after), 'The earlier claim holds; the later one is listed.');
            } finally {
                $_COOKIE = $oldCookies;
            }
        });
    }

    public function testStartPageWithNoClaimsThreadSaysNothingAboutClaims(): void
    {
        $this->withSite(true, function (Application $application): void {
            $oldCookies = $_COOKIE;
            try {
                $_COOKIE = ['identity_hint' => self::MEMBER];
                $id = FeedbackBacklog::select(BacklogPageController::load(dirname(__DIR__)), 'can-start', 'all')[0]->id();
                $html = $this->render($application, '/backlog/start?items=' . $id);
                foreach (['Not claimed', 'data-role="backlog-claim', 'backlog_claim.', 'backlog-start-items'] as $absent) {
                    assertStringNotContains($absent, $html);
                }
                assertStringNotContains('backlog-claim-intent', $this->render($application, '/backlog'));
            } finally {
                $_COOKIE = $oldCookies;
            }
        });
    }

    public function testANamedThreadThatDoesNotExistShowsNoClaimsAndNoError(): void
    {
        $this->withClaims(function (callable $application): void {
            $html = $this->render($application(), '/backlog');
            assertStringContains('Hide claimed', $html);
            assertStringNotContains('data-role="backlog-claim"', $html);
            assertStringContains("\nClaimed: show\n", $this->raw($application(), '/backlog.txt'));
        }, 'thread-that-was-never-posted');
    }

    private string $database = '';

    /** Any Key: value line the text routes print about claims. */
    private const CLAIM_KEYS = '/^(Claim|Claimed|Claims|Also-Claimed|Guest-Claims)[A-Za-z-]*: /m';

    /** The fixture's committed root-approved operator. */
    private const MEMBER = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    /**
     * A site whose profile names a claims thread. The test gets a maker of fresh
     * applications (each request reads the posts anew), the write service, the
     * thread's ID and three real items that can start.
     *
     * @param callable(callable(): Application, LocalWriteService, string, list<string>): void $test
     */
    private function withClaims(callable $test, ?string $missingThread = null): void
    {
        $this->withSite(true, function (Application $first, string $repositoryRoot) use ($test, $missingThread): void {
            $database = dirname($repositoryRoot) . '/read-model.sqlite3';
            $this->database = $database;
            $this->render($first, '/');
            $service = new LocalWriteService($repositoryRoot, $database, dirname($repositoryRoot) . '/public', new CanonicalRecordRepository($repositoryRoot), featureFlags: FeatureFlagEvaluator::forRepository($repositoryRoot));
            $thread = $missingThread ?? (string) $service->createThread(['board_tags' => 'general', 'subject' => 'Backlog claims', 'body' => 'Claims on backlog items.', 'author_identity_id' => self::MEMBER])['thread_id'];
            $profiles = SiteProfileRegistry::all();
            foreach ($profiles as $siteId => $profile) {
                $profiles[$siteId]['backlogClaimsThread'] = $thread;
            }
            SiteProfileRegistry::setProfilesForTesting($profiles);
            try {
                $startable = FeedbackBacklog::select(BacklogPageController::load(dirname(__DIR__)), 'can-start', 'all');
                $ids = array_map(static fn ($item): string => $item->id(), array_slice($startable, 0, 3));
                $test(static fn (): Application => new Application(dirname(__DIR__), $repositoryRoot, $database), $service, $thread, $ids);
            } finally {
                SiteProfileRegistry::setProfilesForTesting(null);
            }
        });
    }

    private function reply(LocalWriteService $service, string $thread, string $body, ?string $author, ?string $parent = null): string
    {
        $input = ['thread_id' => $thread, 'parent_id' => $parent ?? $thread, 'body' => $body];
        if ($author !== null) {
            $input['author_identity_id'] = $author;
        }

        return (string) $service->createReply($input)['post_id'];
    }

    private function withSite(bool $enabled, callable $test): void
    {
        $oldFlag = getenv(FeatureFlagRegistry::BACKLOG_ENABLED);
        putenv(FeatureFlagRegistry::BACKLOG_ENABLED . '=' . ($enabled ? 'true' : 'false'));
        $repositoryRoot = sys_get_temp_dir() . '/forum-rewrite-backlog-' . bin2hex(random_bytes(6)) . '/repo';
        try {
            mkdir($repositoryRoot, 0777, true);
            $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $repositoryRoot);
            foreach (['git init', 'git config user.name "Forum Rewrite"', 'git config user.email "forum-rewrite@example.invalid"', 'git add .', 'git commit -m "Test repository"'] as $command) {
                $this->runCommand($repositoryRoot, $command);
            }
            mkdir(dirname($repositoryRoot) . '/public', 0777, true);
            $test(new Application(dirname(__DIR__), $repositoryRoot, dirname($repositoryRoot) . '/read-model.sqlite3'), $repositoryRoot);
        } finally {
            putenv($oldFlag === false ? FeatureFlagRegistry::BACKLOG_ENABLED : FeatureFlagRegistry::BACKLOG_ENABLED . '=' . $oldFlag);
            exec('rm -rf ' . escapeshellarg(dirname($repositoryRoot)));
        }
    }

    /** The page without its inline critical CSS, which names every class. */
    private function render(Application $application, string $path): string
    {
        ob_start();
        $application->handle('GET', $path);

        return (string) preg_replace('/<style data-role="critical-css">.*?<\/style>/s', '', (string) ob_get_clean());
    }

    private function raw(Application $application, string $path): string
    {
        ob_start();
        $application->handle('GET', $path);

        return (string) ob_get_clean();
    }

    private function runCommand(string $cwd, string $command): void
    {
        exec('cd ' . escapeshellarg($cwd) . ' && ' . $command . ' 2>&1', $output, $status);
        if ($status !== 0) {
            throw new RuntimeException('Command failed: ' . $command . "\n" . implode("\n", $output));
        }
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $targetPath = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                if (!is_dir($targetPath)) mkdir($targetPath, 0777, true);
                continue;
            }
            copy($item->getPathname(), $targetPath);
        }
    }
}
