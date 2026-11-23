<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Import\InstanceImportRequests;
use ForumRewrite\Import\LastImportSummary;

final class LastImportSummaryTest
{
    public function testNoImportPreviewsAndCanceledRunsGiveNoSummary(): void
    {
        $root = $this->repository();
        assertSame(null, (new LastImportSummary($root))->read());
        $this->report($root, '20261122GMT100000-aaaaaaaaaaaa', 1000, 'https://forum.example', ['import' => 5], true);
        mkdir($root . '/.git/instance-import/20261122GMT100100-bbbbbbbbbbbb', 0700, true);
        file_put_contents($root . '/.git/instance-import/20261122GMT100100-bbbbbbbbbbbb/canceled.json', '{"canceled_at":"2026-11-22T10:01:00Z"}');
        file_put_contents($root . '/.git/instance-import/pending.json', '{"id":"x"}');
        assertSame(null, (new LastImportSummary($root))->read());
    }

    public function testNewestReportWinsByWriteTimeHoweverTheImportWasStarted(): void
    {
        $root = $this->repository();
        // A page import, then a terminal import: both leave the same kind of report.
        $this->report($root, '20261122GMT100000-ffffffffffff', 1000, 'https://zenmemes.com', ['import' => 8813, 'duplicate' => 1, 'conflict' => 4, 'invalid' => 249, 'unsupported' => 103, 'excluded' => 767]);
        $first = (new LastImportSummary($root))->read();
        assertSame('zenmemes.com', $first['host']);
        assertSame(1000, $first['at']);
        assertSame(8813, $first['imported']);
        assertSame(356, $first['held_back']);
        assertSame(4, $first['conflicts']);
        assertSame('partial', $first['status']);
        assertSame(false, $first['repeat'], 'a first import is not a repeat');

        // The later run sorts before the first by name; its report is newer.
        $this->report($root, '20261122GMT100000-000000000000', 2000, 'https://zenmemes.com/', ['import' => 37, 'duplicate' => 8814]);
        $second = (new LastImportSummary($root))->read();
        assertSame('20261122GMT100000-000000000000', $second['id']);
        assertSame(37, $second['imported']);
        assertSame(0, $second['held_back']);
        assertSame(true, $second['repeat'], 'same source, trailing slash aside');

        // An older report written again later (the reverse order) is the newer one.
        touch($root . '/.git/instance-import/20261122GMT100000-ffffffffffff/report.json', 3000);
        assertSame('20261122GMT100000-ffffffffffff', (new LastImportSummary($root))->read()['id']);
    }

    public function testReportsWrittenInTheSameSecondFollowTheRunTheOutputNames(): void
    {
        $root = $this->repository();
        $this->report($root, '20261122GMT100000-ffffffffffff', 1000, 'https://zenmemes.com', ['import' => 0, 'duplicate' => 9]);
        $this->report($root, '20261122GMT100000-000000000000', 1000, 'https://zenmemes.com', ['import' => 2, 'duplicate' => 9]);
        $log = "Import result: complete\nimport: 2\nReview: {$root}/.git/instance-import/20261122GMT100000-000000000000/report.json\nProgress: done\nElapsed time: 17.42 seconds\n";
        $result = InstanceImportRequests::resultFromLog($log);
        assertSame('20261122GMT100000-000000000000', $result['run_id']);
        assertSame(17.42, $result['seconds']);
        assertSame(2, (new LastImportSummary($root))->read($result['run_id'])['imported']);
        assertSame(0, (new LastImportSummary($root))->read('20261122GMT100000-ffffffffffff')['imported']);
        assertSame(null, InstanceImportRequests::resultFromLog("Import failed: nope\n")['run_id']);
    }

    public function testChangedSourceIsNotARepeatAndLargeReportsAreReadFromTheirStart(): void
    {
        $root = $this->repository();
        $this->report($root, '20261122GMT100000-aaaaaaaaaaaa', 1000, 'https://zenmemes.com', ['import' => 10]);
        $this->report($root, '20261122GMT110000-bbbbbbbbbbbb', 2000, 'http://127.0.0.1:8123', ['import' => 3], false, 4000);
        $summary = (new LastImportSummary($root))->read();
        assertSame('127.0.0.1:8123', $summary['host']);
        assertSame(3, $summary['imported']);
        assertSame(false, $summary['repeat']);
        assertTrue(filesize($root . '/.git/instance-import/20261122GMT110000-bbbbbbbbbbbb/report.json') > 200000);
        // A report in a layout this reader does not expect is skipped, not fatal.
        mkdir($root . '/.git/instance-import/20261122GMT120000-cccccccccccc', 0700, true);
        file_put_contents($root . '/.git/instance-import/20261122GMT120000-cccccccccccc/report.json', 'not json');
        touch($root . '/.git/instance-import/20261122GMT120000-cccccccccccc/report.json', 5000);
        assertSame('127.0.0.1:8123', (new LastImportSummary($root))->read()['host']);
    }

    private function repository(): string
    {
        $root = sys_get_temp_dir() . '/last-import-' . bin2hex(random_bytes(6));
        mkdir($root . '/.git/instance-import', 0700, true);

        return $root;
    }

    /** Written as ContentImportRunner::save() writes it: pretty JSON, `entries` after the counts. */
    private function report(string $root, string $id, int $writtenAt, string $source, array $counts, bool $preview = false, int $entries = 1): void
    {
        $directory = $root . '/.git/instance-import/' . $id;
        mkdir($directory, 0700, true);
        $list = [];
        for ($i = 0; $i < $entries; $i++) {
            $list['records/posts/post-' . $i . '.txt'] = ['state' => 'import', 'reason' => 'New public record'];
        }
        $counts += ['import' => 0, 'duplicate' => 0, 'conflict' => 0, 'invalid' => 0, 'unsupported' => 0, 'excluded' => 0];
        file_put_contents($directory . '/report.json', json_encode([
            'source' => $source, 'destination' => $root, 'preview' => $preview, 'with_approvals' => false,
            'status' => $counts['conflict'] + $counts['invalid'] + $counts['unsupported'] > 0 ? 'partial' : 'complete',
            'counts' => $counts, 'excluded_archive_categories' => [], 'entries' => $list, 'review_causes' => [], 'exclusion_reasons' => [],
        ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        touch($directory . '/report.json', $writtenAt);
    }
}
