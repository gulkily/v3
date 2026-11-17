<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Import\ImportOutputFilter;

final class ImportOutputFilterTest
{
    private float $now = 0.0;

    public function testProgressLinesPrintOnlyWhenAskedFor(): void
    {
        assertSame([], (new ImportOutputFilter())->lines('Progress: install 100/7418'));
        assertSame(['Progress: install 100/7418'], (new ImportOutputFilter(true))->lines('Progress: install 100/7418'));
        assertSame(['Committing imported content...'], (new ImportOutputFilter())->lines('Committing imported content...'));
    }

    public function testPlanningProgressBecomesAReadableCounterOnTerminals(): void
    {
        $this->now = 0.0;
        $filter = $this->filter();
        assertSame([], $filter->lines('Progress: plan'));
        assertSame(['Checking records (0/8490).'], $filter->lines('Progress: plan 0/8490'));
        assertSame([], $filter->lines('Progress: plan 250/8490'));
        $this->now = 1.5;
        assertSame(['Checking records (500/8490).'], $filter->lines('Progress: plan 500/8490'));
        assertSame([], $filter->lines('Progress: plan 8250/8490'));
        assertSame(['Checking records (8250/8490).', 'Installing 7418 record file(s)...'], $filter->lines('Installing 7418 record file(s)...'));
        assertSame(['Progress: plan 0/8490'], (new ImportOutputFilter(true))->lines('Progress: plan 0/8490'));
    }

    public function testCountersPrintOncePerSecondAndTheirLastValueBeforeTheNextLine(): void
    {
        $this->now = 0.0;
        $filter = $this->filter();
        $printed = [];
        foreach ([100, 200, 300, 400] as $done) {
            $printed = [...$printed, ...$filter->lines('Installed ' . $done . ' record files...')];
            $this->now += 0.3;
        }
        $this->now += 0.2;
        $printed = [...$printed, ...$filter->lines('Installed 500 record files...')];
        $printed = [...$printed, ...$filter->lines('Installed 600 record files...')];
        // A Progress line in between is hidden and does not flush the counter.
        $printed = [...$printed, ...$filter->lines('Progress: install 600/7418')];
        $printed = [...$printed, ...$filter->lines('Committing imported content...')];
        assertSame([
            'Installed 100 record files...',
            'Installed 500 record files...',
            'Installed 600 record files...',
            'Committing imported content...',
        ], $printed);
    }

    public function testOrdinaryLinesThatDifferOnlyInDigitsAllPrint(): void
    {
        $filter = $this->filter(verbose: true);
        foreach ([
            'Review cause: records/posts/reply-20261112044628-c5af25ad.txt — invalid',
            'Review cause: records/posts/reply-20261112044639-c5af25ad.txt — invalid',
            'import: 7418',
            'import: 7418',
        ] as $line) {
            assertSame([$line], $filter->lines($line));
        }
    }

    public function testReadModelDetailShowsOnlyWhenVerboseOrAfterFiveQuietSeconds(): void
    {
        $this->now = 0.0;
        $filter = $this->filter();
        assertSame(['Refreshing read model and applicable offline data...'], $filter->lines('Refreshing read model and applicable offline data...'));
        $this->now = 4.9;
        assertSame([], $filter->lines('Read model: index posts...'));
        $this->now = 6.0;
        assertSame(['Read model: parsing post records (2000/3129).'], $filter->lines('Read model: parsing post records (2000/3129).'));
        $this->now = 7.0;
        assertSame([], $filter->lines('Read model: parsing post records (3129/3129).'));
        assertSame(['Read-model promotion complete.'], $filter->lines('Read-model promotion complete.'));

        $verbose = $this->filter(verbose: true);
        assertSame(['Read model: index posts...'], $verbose->lines('Read model: index posts...'));
        assertSame(['Read model: index profiles...'], $verbose->lines('Read model: index profiles...'));
    }

    public function testReviewCausesGroupByStateAndReasonWithoutRecordIds(): void
    {
        $lines = ImportOutputFilter::groupedReviewCauses([
            ['state' => 'unsupported', 'reason' => 'Unsupported record family or path'],
            ['state' => 'invalid', 'reason' => 'Missing dependency: post:thread-20261103074538-e5864c2c'],
            ['state' => 'invalid', 'reason' => 'Missing dependency: post:reply-20260526032118-654b2b08'],
            ['state' => 'unsupported', 'reason' => 'Unsupported record family or path'],
            ['state' => 'invalid', 'reason' => 'Missing dependency: post:thread-20261117032814-7617a78d'],
            ['state' => 'conflict', 'reason' => 'Existing path or canonical identity has different bytes'],
            ['state' => 'excluded', 'reason' => 'Approval or invitation authority post'],
        ]);
        assertSame([
            'Invalid: Missing dependency (3 files)',
            'Unsupported: Unsupported record family or path (2 files)',
            'Conflict: Existing path or canonical identity has different bytes (1 file)',
        ], $lines);
    }

    private function filter(bool $verbose = false): ImportOutputFilter
    {
        return new ImportOutputFilter(false, fn (): float => $this->now, $verbose);
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting identical values: ' . var_export($expected, true) . ' !== ' . var_export($actual, true));
        }
    }
}
