<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

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
