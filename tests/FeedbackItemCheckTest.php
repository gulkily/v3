<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Feedback\FeedbackItemCheck;

final class FeedbackItemCheckTest
{
    private const VALID = "ID: PF-20261103-F01\nTitle: Summary field\nKind: feature\nWorkflow: untriaged\nCreated: 2026-11-03\n\nRequest:\nAdd a summary field.\n\nSources:\n- https://zenmemes.com/threads/thread-20261103051750-7f138dfb\n- https://zenmemes.com/posts/reply-20261103051823-6a0ec599\n";

    public function testValidItemPassesWithItsSources(): void
    {
        $check = $this->check(['PF-20261103-F01.txt' => self::VALID]);

        assertSame([], $check->problems);
        assertSame(1, count($check->items));
        assertSame(['thread-20261103051750-7f138dfb', 'reply-20261103051823-6a0ec599'], $check->items[0]->sourceIds());
    }

    public function testMalformedHeaderAndUnknownHeaderAreReported(): void
    {
        $check = $this->check([
            'PF-20261103-F01.txt' => "ID PF-20261103-F01\n\nRequest:\nx\n",
            'PF-20261103-F02.txt' => str_replace(['F01', "Created: 2026-11-03\n"], ['F02', "Created: 2026-11-03\nOwner: me\n"], self::VALID),
        ]);

        assertContainsLine('PF-20261103-F01.txt: Invalid header line', $check->problems);
        assertContainsLine('PF-20261103-F02.txt: unknown header Owner', $check->problems);
    }

    public function testIdMustMatchFilenameAndKind(): void
    {
        $check = $this->check([
            'wrong.txt' => self::VALID,
            'PF-20261103-F02.txt' => str_replace(['F01', 'Kind: feature'], ['F02', 'Kind: bug'], self::VALID),
        ]);

        assertContainsLine('wrong.txt: filename must be PF-20261103-F01.txt', $check->problems);
        assertContainsLine('PF-20261103-F02.txt: ID letter F does not match Kind bug', $check->problems);
    }

    public function testInvalidValuesAndMissingSectionsAreReported(): void
    {
        $bad = "ID: PF-20261103-B01\nTitle: Broken\nKind: bug\nWorkflow: someday\nCreated: 2026-13-40\nEstimate: big\n\nRequest:\nIt breaks.\n\nSources:\n- see the thread\n";
        $check = $this->check(['PF-20261103-B01.txt' => $bad, 'PF-20261103-B02.txt' => "ID: PF-20261103-B02\nTitle: x\nKind: bug\nWorkflow: ready\nCreated: 2026-11-03\n\nNotes:\nnothing\n"]);

        assertContainsLine('PF-20261103-B01.txt: Workflow must be one of', $check->problems);
        assertContainsLine('PF-20261103-B01.txt: Created must be YYYY-MM-DD', $check->problems);
        assertContainsLine('PF-20261103-B01.txt: Estimate must be', $check->problems);
        assertContainsLine('PF-20261103-B01.txt: source must be', $check->problems);
        assertContainsLine('PF-20261103-B02.txt: missing or empty section Request:', $check->problems);
        assertContainsLine('PF-20261103-B02.txt: missing or empty section Sources:', $check->problems);
    }

    public function testMissingDuplicateTargetFails(): void
    {
        $duplicate = str_replace('Workflow: untriaged', "Workflow: duplicate\nDuplicate-Of: PF-20261103-F09", self::VALID);
        $check = $this->check([
            'PF-20261103-F01.txt' => self::VALID,
            'PF-20261103-F02.txt' => str_replace('F01', 'F02', $duplicate),
        ]);

        assertContainsLine('PF-20261103-F02.txt: Duplicate-Of PF-20261103-F09 does not exist', $check->problems);
        assertSame([], $check->warnings);
    }

    public function testSharedSourceIsListedForReviewWithoutFailing(): void
    {
        $check = $this->check([
            'PF-20261103-F01.txt' => self::VALID,
            'PF-20261103-F02.txt' => str_replace('F01', 'F02', self::VALID),
        ]);

        assertSame([], $check->problems);
        assertContainsLine('PF-20261103-F01, PF-20261103-F02: share source thread-20261103051750-7f138dfb', $check->warnings);
    }

    public function testCommandExitsNonzeroOnProblems(): void
    {
        $directory = $this->directory(['PF-20261103-F01.txt' => self::VALID, 'bad.txt' => "nope\n"]);
        try {
            exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && ./v3 feedback check --dir=' . escapeshellarg($directory) . ' 2>&1', $output, $exitCode);
            assertSame(1, $exitCode);
            assertContainsLine('Problem: bad.txt:', $output);
            assertContainsLine('1 item(s), 1 problem(s), 0 to review.', $output);
        } finally {
            $this->remove($directory);
        }
    }

    public function testIndexGroupsOpenItemsByKindAndWorkflowAndListsClosed(): void
    {
        $directory = $this->directory([
            'PF-20261103-F01.txt' => self::VALID,
            'PF-20261103-F02.txt' => str_replace(['F01', 'Workflow: untriaged', 'Summary field'], ['F02', "Workflow: in-progress\nEstimate: 0.15", 'Pipe | title'], self::VALID),
            'PF-20261103-B01.txt' => str_replace(['F01', 'Kind: feature', 'reply-20261103051823', 'thread-20261103051750'], ['B01', 'Kind: bug', 'reply-1', 'thread-1'], self::VALID),
            'PF-20261103-F03.txt' => str_replace(['F01', 'Workflow: untriaged'], ['F03', "Workflow: duplicate\nDuplicate-Of: PF-20261103-F01"], self::VALID),
        ]);
        try {
            exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && ./v3 feedback index --dir=' . escapeshellarg($directory . '/items') . ' 2>&1', $output, $exitCode);
            assertSame(1, $exitCode);
            assertContainsLine('Problem: ', $output);

            mkdir($directory . '/items');
            foreach (glob($directory . '/*.txt') as $file) {
                rename($file, $directory . '/items/' . basename($file));
            }
            $output = [];
            exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && ./v3 feedback index --dir=' . escapeshellarg($directory . '/items') . ' 2>&1', $output, $exitCode);
            assertSame(0, $exitCode);
            assertContainsLine('Wrote ', $output);
            $index = file_get_contents($directory . '/README.md');
            assertSame(true, str_contains($index, "3 open, 1 closed."));
            $features = strpos($index, '## Features');
            $bugs = strpos($index, '## Bugs');
            $inProgress = strpos($index, '[PF-20261103-F02]');
            $untriaged = strpos($index, '[PF-20261103-F01]');
            assertSame(true, $features < $inProgress && $inProgress < $untriaged && $untriaged < $bugs);
            assertSame(true, str_contains($index, 'Pipe \\| title'));
            assertSame(true, str_contains($index, '| 0.15 | 2 |'));
            assertSame(true, str_contains($index, 'duplicate → [PF-20261103-F01](items/PF-20261103-F01.txt)'));
            assertSame(false, str_contains($index, 'HISTORY.md'));

            $output = [];
            exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && ./v3 feedback index --dir=' . escapeshellarg($directory . '/items') . ' 2>&1', $output, $exitCode);
            assertContainsLine('Unchanged: ', $output);
            assertSame($index, file_get_contents($directory . '/README.md'));

            file_put_contents($directory . '/HISTORY.md', "# History\n");
            exec('cd ' . escapeshellarg(dirname(__DIR__)) . ' && ./v3 feedback index --dir=' . escapeshellarg($directory . '/items') . ' 2>&1');
            assertSame(true, str_contains(file_get_contents($directory . '/README.md'), '[history](HISTORY.md)'));
        } finally {
            $this->remove($directory);
        }
    }

    /**
     * @param array<string, string> $files
     */
    private function check(array $files): FeedbackItemCheck
    {
        $directory = $this->directory($files);
        try {
            return FeedbackItemCheck::run($directory);
        } finally {
            $this->remove($directory);
        }
    }

    /**
     * @param array<string, string> $files
     */
    private function directory(array $files): string
    {
        $directory = sys_get_temp_dir() . '/feedback-items-' . bin2hex(random_bytes(6));
        mkdir($directory);
        foreach ($files as $name => $contents) {
            @mkdir(dirname($directory . '/' . $name), 0777, true);
            file_put_contents($directory . '/' . $name, $contents);
        }
        return $directory;
    }

    private function remove(string $directory): void
    {
        exec('rm -rf ' . escapeshellarg($directory));
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting that values are identical. Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

if (!function_exists('assertContainsLine')) {
    /**
     * @param list<string> $lines
     */
    function assertContainsLine(string $prefix, array $lines): void
    {
        foreach ($lines as $line) {
            if (str_starts_with($line, $prefix)) {
                return;
            }
        }
        throw new RuntimeException('No line starts with ' . var_export($prefix, true) . ' in ' . var_export($lines, true));
    }
}
