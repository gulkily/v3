<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Feedback\BacklogPrompt;
use ForumRewrite\Feedback\FeedbackBacklog;
use ForumRewrite\Feedback\FeedbackItem;
use ForumRewrite\Feedback\FeedbackItemCheck;

final class FeedbackBacklogTest
{
    public function testImpactHeaderIsCheckedLikeEstimate(): void
    {
        $directory = sys_get_temp_dir() . '/v3-backlog-' . bin2hex(random_bytes(4));
        mkdir($directory);
        $base = "ID: PF-20261103-F01\nTitle: Summary field\nKind: feature\nWorkflow: untriaged\nCreated: 2026-11-03\nImpact: 0.4\n\nRequest:\nAdd it.\n\nSources:\n- Session: test\n\nNotes:\n2026-11-05: looked at it.\n2026-11-09: planned.\n";
        file_put_contents($directory . '/PF-20261103-F01.txt', $base);
        file_put_contents($directory . '/PF-20261103-F02.txt', str_replace(['F01', 'Impact: 0.4'], ['F02', 'Impact: high'], $base));
        try {
            $check = FeedbackItemCheck::run($directory);
            assertSame(1, count($check->items));
            assertContainsLine('PF-20261103-F02.txt: Impact must be a nonnegative decimal or none', $check->problems);
            assertSame(0.4, $check->items[0]->number('Impact'));
            assertSame(null, $check->items[0]->number('Estimate'));
            assertSame('2026-11-09', $check->items[0]->touched());
        } finally {
            array_map('unlink', glob($directory . '/*'));
            rmdir($directory);
        }
    }

    public function testTouchedFallsBackToCreated(): void
    {
        assertSame('2026-11-01', $this->item('F01', '2026-11-01')->touched());
    }

    public function testEnteredAndTouchedSortInBothDirections(): void
    {
        $items = [
            $this->item('F01', '2026-11-01', notes: "2026-11-20: reopened.\n"),
            $this->item('F02', '2026-11-10'),
            $this->item('F03', '2026-11-05'),
        ];

        assertSame(['F02', 'F03', 'F01'], $this->ids(FeedbackBacklog::sort($items, 'entered', 'newest')));
        assertSame(['F01', 'F03', 'F02'], $this->ids(FeedbackBacklog::sort($items, 'entered', 'oldest')));
        assertSame(['F01', 'F02', 'F03'], $this->ids(FeedbackBacklog::sort($items, 'touched', 'newest')));
        assertSame(['F03', 'F02', 'F01'], $this->ids(FeedbackBacklog::sort($items, 'touched', 'oldest')));
    }

    public function testEffortAndImpactPutItemsWithAValueFirst(): void
    {
        $items = [
            $this->item('F01', '2026-11-01'),
            $this->item('F02', '2026-11-02', estimate: '0.5', impact: '0.2'),
            $this->item('F03', '2026-11-03', estimate: '0.1', impact: '0.9'),
            $this->item('F04', '2026-11-04', estimate: 'none'),
        ];

        assertSame(['F03', 'F02', 'F04', 'F01'], $this->ids(FeedbackBacklog::sort($items, 'effort', 'newest')));
        assertSame(['F03', 'F02', 'F01', 'F04'], $this->ids(FeedbackBacklog::sort($items, 'effort', 'oldest')));
        assertSame(['F03', 'F02', 'F04', 'F01'], $this->ids(FeedbackBacklog::sort($items, 'impact', 'newest')));
    }

    public function testSalientRanksImpactOverEffortWithMissingValuesInTheMiddle(): void
    {
        $items = [
            $this->item('F01', '2026-11-01'),                                   // 0.5 / 0.5 = 1
            $this->item('F02', '2026-11-02', estimate: '0.1'),                  // 0.5 / 0.1 = 5
            $this->item('F03', '2026-11-03', estimate: '0.9'),                  // 0.5 / 0.9
            $this->item('F04', '2026-11-04', estimate: '0.2', impact: '0.8'),   // 4
            $this->item('F05', '2026-11-05'),                                   // 1
            $this->item('F06', '2026-11-06', estimate: '0', impact: '0.1'),     // effort floor
        ];

        assertSame(['F06', 'F02', 'F04', 'F05', 'F01', 'F03'], $this->ids(FeedbackBacklog::sort($items, 'salient', 'newest')));
        assertSame(['F06', 'F02', 'F04', 'F01', 'F05', 'F03'], $this->ids(FeedbackBacklog::sort($items, 'salient', 'oldest')));
    }

    public function testFiltersByWorkflowGroupAndKind(): void
    {
        $items = [
            $this->item('F01', '2026-11-01', workflow: 'ready'),
            $this->item('B01', '2026-11-01', workflow: 'untriaged', kind: 'bug'),
            $this->item('F02', '2026-11-01', workflow: 'blocked'),
            $this->item('F03', '2026-11-01', workflow: 'done'),
            $this->item('F04', '2026-11-01', workflow: 'in-progress'),
        ];

        assertSame(['F01', 'B01'], $this->ids(FeedbackBacklog::select($items, 'can-start', 'all')));
        assertSame(['B01'], $this->ids(FeedbackBacklog::select($items, 'can-start', 'bug')));
        assertSame(['F02'], $this->ids(FeedbackBacklog::select($items, 'waiting', 'all')));
        assertSame(['B01'], $this->ids(FeedbackBacklog::select($items, 'untriaged', 'all')));
        assertSame(['F01', 'B01', 'F02', 'F04'], $this->ids(FeedbackBacklog::select($items, 'open', 'all')));
    }

    public function testUnknownWordsFallBackAndAreNamed(): void
    {
        $words = FeedbackBacklog::words(['sort' => 'effort', 'order' => 'sideways', 'show' => ['x'], 'kind' => 'bug', 'page' => '2']);

        assertSame('effort', $words['sort']);
        assertSame('newest', $words['order']);
        assertSame('can-start', $words['show']);
        assertSame('bug', $words['kind']);
        assertSame(['order=sideways'], $words['unknown']);
    }

    public function testEverySortRunsOverTheRealItems(): void
    {
        $check = FeedbackItemCheck::run(dirname(__DIR__) . '/docs/feedback/items');
        assertSame([], $check->problems);
        $open = FeedbackBacklog::select($check->items, 'open', 'all');
        assertSame(true, count($open) > 0);
        foreach (FeedbackBacklog::SORTS as $sort) {
            foreach (FeedbackBacklog::ORDERS as $order) {
                $sorted = FeedbackBacklog::sort($open, $sort, $order);
                assertSame(count($open), count($sorted));
                assertSame(count($open), count(array_unique($this->ids($sorted))));
            }
        }
        $withValue = true;
        foreach (FeedbackBacklog::sort($open, 'effort', 'newest') as $item) {
            $has = $item->number('Estimate') !== null;
            assertSame(true, $withValue || !$has);
            $withValue = $has;
        }
    }

    public function testPromptIsShortAndNamesOnlyFilesThatExist(): void
    {
        $check = FeedbackItemCheck::run(dirname(__DIR__) . '/docs/feedback/items');
        $open = array_slice(FeedbackBacklog::select($check->items, 'can-start', 'all'), 0, 2);
        $prompt = BacklogPrompt::build($open, 'one-shot', 'https://example.org/v3');

        assertSame(true, substr_count($prompt, "\n") < 15);
        assertSame(true, str_contains($prompt, 'these 2 v3 backlog items together as one Railspike one-shot cycle'));
        assertSame(true, str_contains($prompt, 'Repository: https://example.org/v3'));
        preg_match_all('/^- (\S+)$/m', $prompt, $files);
        assertSame(3, count($files[1]));
        foreach ($files[1] as $file) {
            assertSame(true, is_file(dirname(__DIR__) . '/' . $file));
        }

        assertSame(true, str_starts_with($prompt, 'Please work on '));

        // Access comes before the files to read, in every mode; the grouping check only with several items.
        foreach (BacklogPrompt::MODES as $mode) {
            $two = BacklogPrompt::build($open, $mode, null);
            assertSame(true, strpos($two, BacklogPrompt::ACCESS) < strpos($two, 'Read first:'), $mode);
            assertSame(1, substr_count($two, BacklogPrompt::TOGETHER), $mode);
            assertSame(true, strpos($two, 'Begin now') < strpos($two, BacklogPrompt::TOGETHER), $mode);
            $single = BacklogPrompt::build([$open[0]], $mode, null);
            assertSame(1, substr_count($single, BacklogPrompt::ACCESS), $mode);
            assertSame(false, str_contains($single, BacklogPrompt::TOGETHER), $mode);
            assertSame(true, substr_count($single, "\n") < 15, $mode);
        }
        assertSame(true, str_contains(BacklogPrompt::ACCESS, "don't give me patch files"));
        assertSame(true, str_contains(BacklogPrompt::ACCESS, 'tell me what to connect'));

        $auto = BacklogPrompt::build([$open[0]], 'auto', null);
        assertSame($auto, BacklogPrompt::build([$open[0]], 'fast', null), 'An unknown mode means the agent decides.');
        assertSame(true, str_starts_with($auto, 'Please work on this v3 backlog item as one Railspike cycle, one-shot or staged as AGENTS.md says.'));
        assertSame(true, str_contains($auto, 'If you go staged, wait for my approval of each step.'));
        assertSame('auto', BacklogPrompt::mode(null));

        $staged = BacklogPrompt::build([$open[0]], 'staged', null);
        assertSame(true, str_contains($staged, 'this v3 backlog item as one staged Railspike cycle'));
        assertSame(false, str_contains($staged, 'Repository:'));
        assertSame(true, str_contains($staged, 'wait for my approval of each step'));
    }

    public function testPickSeparatesOpenUnknownAndClosedItems(): void
    {
        $items = [$this->item('PF-20261101-F01', '2026-11-01'), $this->item('PF-20261101-F02', '2026-11-01', workflow: 'done')];
        $ids = BacklogPrompt::ids(['PF-20261101-F02,PF-20261101-F01', 'PF-20261101-F09 ../etc PF-20261101-F01']);

        assertSame(['PF-20261101-F02', 'PF-20261101-F01', 'PF-20261101-F09'], $ids);
        $picked = BacklogPrompt::pick($items, $ids);
        assertSame(['PF-20261101-F01'], $this->ids($picked['items']));
        assertSame(['PF-20261101-F09'], $picked['unknown']);
        assertSame(['PF-20261101-F02'], $picked['closed']);
    }

    public function testListAndStartCommands(): void
    {
        $v3 = 'cd ' . escapeshellarg(dirname(__DIR__)) . ' && ./v3 feedback ';
        exec($v3 . 'list --sort=effort --kind=bug 2>&1', $list, $exitCode);
        assertSame(0, $exitCode);
        assertSame('Sort: effort', $list[0]);
        assertContainsLine('Kind: bug', $list);
        assertContainsLine('File: docs/feedback/items/PF-', $list);

        exec($v3 . 'list --sort=sideways 2>&1', $fallback, $exitCode);
        assertSame(2, $exitCode);
        assertContainsLine('Unknown word, default used: sort=sideways', $fallback);
        assertContainsLine('Sort: salient', $fallback);

        $directory = sys_get_temp_dir() . '/v3-backlog-' . bin2hex(random_bytes(4));
        mkdir($directory);
        $open = "ID: PF-20261103-F01\nTitle: Summary field\nKind: feature\nWorkflow: ready\nCreated: 2026-11-03\n\nRequest:\nAdd it.\n\nSources:\n- Session: test\n";
        file_put_contents($directory . '/PF-20261103-F01.txt', $open);
        file_put_contents($directory . '/PF-20261103-F02.txt', str_replace(['F01', 'Workflow: ready'], ['F02', 'Workflow: done'], $open));
        $dir = ' --dir=' . escapeshellarg($directory) . ' 2>&1';
        try {
            exec($v3 . 'start PF-20261103-F01 PF-20261103-F02 PF-00000000-F99' . $dir, $start, $exitCode);
            assertSame(0, $exitCode);
            assertContainsLine('Left out, no such item: PF-00000000-F99', $start);
            assertContainsLine('Left out, already closed: PF-20261103-F02', $start);
            assertContainsLine('- docs/feedback/items/PF-20261103-F01.txt', $start);
            assertContainsLine('- docs/agents/backlog_to_pr.md', $start);
            assertContainsLine('Please work on this v3 backlog item as one Railspike cycle, one-shot or staged as AGENTS.md says.', $start);

            exec($v3 . 'start PF-20261103-F02' . $dir, $nothing, $exitCode);
            assertSame(1, $exitCode);
            exec($v3 . 'start PF-20261103-F01 --mode=fast' . $dir, $mode, $exitCode);
            assertSame(2, $exitCode);
        } finally {
            array_map('unlink', glob($directory . '/*'));
            rmdir($directory);
        }
    }

    public function testClaimAndReleaseCommandsReadTheSiteAndPostThroughQuill(): void
    {
        $root = sys_get_temp_dir() . '/v3-claim-cli-' . bin2hex(random_bytes(4));
        mkdir($root);
        // A stand-in site that answers /backlog/claims.txt, and a stand-in Quill that records what it was asked to post.
        $free = "Claims-Thread: thread-claims\nStale-After-Days: 3\n\nID: PF-20261104-B18\nClaim-State: free\n\nID: PF-20261104-B12\nClaim-State: none\nGuest-Claims: 1\n";
        $held = "Claims-Thread: thread-claims\n\nID: PF-20261104-B18\nClaim-State: held\nClaimed-By: mara\nClaimed-Since: 2026-11-23T08:00:00Z\nClaim-Post: post-claim-1\n\nID: PF-20261104-B12\nClaim-State: free\n";
        file_put_contents($root . '/router.php', '<?php $mode = trim((string) @file_get_contents(__DIR__ . "/mode")); header("Content-Type: text/plain");'
            . ' if (parse_url($_SERVER["REQUEST_URI"], PHP_URL_PATH) !== "/backlog/claims.txt" || $mode === "none") { http_response_code(404); echo "This site keeps no backlog claims.\n"; return; }'
            . ' file_put_contents(__DIR__ . "/asked", $_SERVER["REQUEST_URI"]); echo file_get_contents(__DIR__ . "/" . $mode . ".txt");');
        file_put_contents($root . '/free.txt', $free);
        file_put_contents($root . '/held.txt', $held);
        file_put_contents($root . '/quill.php', '<?php $args = array_slice($argv, 1); $file = $args[array_search("--body-file", $args) + 1];'
            . ' file_put_contents(__DIR__ . "/posted", implode(" ", $args) . "\n" . file_get_contents($file) . "---\n", FILE_APPEND); echo "Post-ID: post-new\n";');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $port = (int) substr(strrchr((string) stream_socket_get_name($socket, false), ':'), 1);
        fclose($socket);
        $server = proc_open(['php', '-S', '127.0.0.1:' . $port, $root . '/router.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        $site = 'http://127.0.0.1:' . $port;
        // The harness may route http through a proxy; a loopback site must not go through it.
        $v3 = 'NO_PROXY=127.0.0.1 no_proxy=127.0.0.1 http_proxy= HTTP_PROXY= V3_QUILL=' . escapeshellarg('php ' . $root . '/quill.php') . ' ' . escapeshellarg(dirname(__DIR__) . '/v3') . ' feedback ';
        $run = static function (string $mode, string $arguments) use ($root, $v3): array {
            file_put_contents($root . '/mode', $mode);
            @unlink($root . '/posted');
            exec($v3 . $arguments . ' 2>&1', $output, $code);
            return [$code, implode("\n", $output), is_file($root . '/posted') ? (string) file_get_contents($root . '/posted') : ''];
        };
        try {
            for ($i = 0; $i < 50 && @file_get_contents($site . '/') === false && !isset($http_response_header); $i++) {
                usleep(100000);
            }

            // Free items (a guest claim does not hold one): the state is printed, then one claim line for both is posted.
            [$code, $output, $posted] = $run('free', "claim --site={$site}/ PF-20261104-B18 PF-20261104-B12 --note='Branch fix/fonts'");
            assertSame(0, $code, $output);
            assertSame('/backlog/claims.txt?items=PF-20261104-B18,PF-20261104-B12', file_get_contents($root . '/asked'));
            assertSame(true, str_contains($output, "ID: PF-20261104-B12\nClaim-State: none\nGuest-Claims: 1"));
            assertSame(1, preg_match('/^--site ' . preg_quote($site, '/') . ' reply --thread thread-claims --parent thread-claims --body-file \S+\nclaim: PF-20261104-B18, PF-20261104-B12\n\nBranch fix\/fonts\n---\n$/', $posted), $posted);

            // A counted claim by someone else: say who, post nothing, exit 1. --anyway posts regardless.
            [$code, $output, $posted] = $run('held', "claim --site={$site} PF-20261104-B18 PF-20261104-B12");
            assertSame(1, $code, $output);
            assertSame(true, str_contains($output, 'Held: PF-20261104-B18 by mara since 2026-11-23T08:00:00Z'));
            assertSame(true, str_contains($output, 'Nothing was posted.'));
            assertSame('', $posted);
            [$code, , $posted] = $run('held', "claim --site={$site} PF-20261104-B18 --anyway");
            assertSame(0, $code);
            assertSame(true, str_contains($posted, "\nclaim: PF-20261104-B18, PF-20261104-B12\n"));

            // Release answers the claim post and names the item; an item with no counted claim is skipped with a reason.
            [$code, $output, $posted] = $run('held', "release --site={$site} PF-20261104-B18 PF-20261104-B12");
            assertSame(0, $code, $output);
            assertSame(true, str_contains($output, 'Nothing to release for PF-20261104-B12'));
            assertSame(1, preg_match('/ reply --thread thread-claims --parent post-claim-1 --body-file \S+\nrelease: PF-20261104-B18\n---\n$/', $posted), $posted);
            // --post names the claim to answer (the stand-in site lists both items whatever it is asked).
            [$code, , $posted] = $run('held', "release --site={$site} PF-20261104-B12 --post=post-mine");
            assertSame(0, $code);
            assertSame(1, preg_match('/ --parent post-mine --body-file \S+\nrelease: PF-20261104-B18, PF-20261104-B12\n---\n$/', $posted), $posted);

            // A site with no claims thread, and a missing site or item: nothing posted, a reason, a failing exit.
            [$code, $output, $posted] = $run('none', "claim --site={$site} PF-20261104-B18");
            assertSame(1, $code);
            assertSame(true, str_contains($output, 'keeps no backlog claims'));
            assertSame('', $posted);
            [$code] = $run('free', 'claim PF-20261104-B18');
            assertSame(2, $code);
            [$code] = $run('free', "claim --site={$site}");
            assertSame(2, $code);
        } finally {
            proc_terminate($server);
            proc_close($server);
            array_map('unlink', glob($root . '/*'));
            rmdir($root);
        }
    }

    private function item(string $suffix, string $created, string $workflow = 'untriaged', string $kind = 'feature', ?string $estimate = null, ?string $impact = null, string $notes = ''): FeedbackItem
    {
        $headers = ['ID' => $suffix, 'Title' => 'Item ' . $suffix, 'Kind' => $kind, 'Workflow' => $workflow, 'Created' => $created];
        if ($estimate !== null) {
            $headers['Estimate'] = $estimate;
        }
        if ($impact !== null) {
            $headers['Impact'] = $impact;
        }
        return new FeedbackItem('/tmp/' . $suffix . '.txt', $headers, [], $notes);
    }

    /**
     * @param list<FeedbackItem> $items
     * @return list<string>
     */
    private function ids(array $items): array
    {
        return array_map(static fn (FeedbackItem $item): string => $item->id(), $items);
    }
}
