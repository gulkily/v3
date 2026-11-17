<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Import\ImportProgressView;

final class ImportProgressViewTest
{
    private const T = 1_800_000_000;

    public function testStepsFollowProgressLinesWithCounts(): void
    {
        $view = ImportProgressView::build([
            $this->event(0, 'Downloading repository archive...'),
            $this->event(0, 'Progress: download 0/?'),
            $this->event(3, 'Progress: download 4194304/4194304'),
            $this->event(3, 'Progress: extract'),
            $this->event(5, 'Progress: plan'),
            $this->event(9, 'Progress: install 0/7172'),
            $this->event(13, 'Progress: install 1200/7172'),
        ], self::T + 14);
        assertSame(['done', 'done', 'done', 'current', 'pending', 'pending', 'pending'], array_column($view['steps'], 'status'));
        assertSame([1200, 7172], [$view['steps'][3]['done'], $view['steps'][3]['total']]);
        assertSame('install', $view['current']);
        assertSame(self::T, $view['started_at']);
        assertSame(self::T + 13, $view['last_update_at']);
        // 1200 files in 5 s: 5972 more take about 25 s.
        assertSame(25, $view['step_seconds_left']);
        assertSame(null, $view['seconds_left'], 'no earlier import: no whole-import figure');
    }

    public function testNoEstimateWithoutABasis(): void
    {
        $view = ImportProgressView::build([$this->event(0, 'Progress: download 0/?'), $this->event(1, 'Progress: download 1048576/?')], self::T + 10);
        assertSame(null, $view['step_seconds_left'], 'unknown total');
        $view = ImportProgressView::build([$this->event(0, 'Progress: install 0/100'), $this->event(1, 'Progress: install 0/100')], self::T + 1);
        assertSame(null, $view['step_seconds_left'], 'nothing done yet');
        $view = ImportProgressView::build([$this->event(0, 'Progress: publish')], self::T + 30);
        assertSame(null, $view['step_seconds_left'], 'no count and no earlier run');
    }

    public function testPreviousRunTimingsGiveTheWholeImportEstimate(): void
    {
        $previous = ['download' => 4.0, 'extract' => 2.0, 'plan' => 6.0, 'install' => 30.0, 'commit' => 3.0, 'publish' => 80.0, 'images' => 10.0];
        $view = ImportProgressView::build([$this->event(0, 'Progress: publish')], self::T + 30, $previous);
        assertSame(50, $view['step_seconds_left']);
        assertSame(60, $view['seconds_left']);
        $view = ImportProgressView::build([$this->event(0, 'Progress: publish')], self::T + 200, $previous);
        assertSame(0, $view['step_seconds_left'], 'past the earlier duration: no negative time');
        assertSame(10, $view['seconds_left']);
    }

    public function testResumedAndFinishedRunsOmitSkippedSteps(): void
    {
        $resumed = ImportProgressView::build([$this->event(0, 'Progress: publish')], self::T + 1);
        assertSame(['publish', 'images'], array_column($resumed['steps'], 'key'));
        $finished = ImportProgressView::build([$this->event(0, 'Progress: publish'), $this->event(5, 'Progress: images'), $this->event(6, 'Progress: done')], self::T + 7);
        assertSame(['done', 'done'], array_column($finished['steps'], 'status'));
        assertSame(true, $finished['finished']);
        assertSame(null, $finished['current']);
        assertSame([], ImportProgressView::build([$this->event(0, 'Cancel line')], self::T)['steps']);
    }

    public function testDurationsRoundTrip(): void
    {
        $durations = ImportProgressView::durations(['download' => 10.0, 'extract' => 12.5, 'plan' => 13.0, 'publish' => 20.0, 'images' => 21.0, 'done' => 22.25]);
        assertSame(['download' => 2.5, 'extract' => 0.5, 'plan' => 7.0, 'publish' => 1.0, 'images' => 1.3], $durations);
        assertSame($durations, ImportProgressView::decodeDurations(ImportProgressView::encodeDurations($durations)));
        assertSame(['plan' => 3.0], ImportProgressView::decodeDurations('plan=3 bogus=4 install=x'));
    }

    /** @return array{message: string, created_at: string} */
    private function event(int $offset, string $message): array
    {
        return ['message' => $message, 'created_at' => gmdate('c', self::T + $offset)];
    }
}
