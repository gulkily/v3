<?php

declare(strict_types=1);

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;

require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

final class ReadModelBuilderTimingTest
{
    public function testProgressSeparatesParsingDerivationAndWritingWithBoundedCounts(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            for ($i = 0; $i < 250; $i++) {
                $fixture->write('thread-labels', $i);
            }
            $messages = [];
            $builder = new ReadModelBuilder($fixture->repository, $fixture->root . '/model.sqlite', new CanonicalRecordRepository($fixture->repository), 'progress_test', static function (string $message) use (&$messages): void { $messages[] = $message; });
            $builder->rebuild();
            $output = implode("\n", $messages);
            $parsing = strpos($output, 'parsing thread label records (252/252).');
            $resolution = strpos($output, 'resolve thread label source commits...');
            $deriving = strpos($output, 'deriving thread label records (0/252).');
            $writing = strpos($output, 'writing thread labels (0/');
            assertSame(true, $parsing !== false && $resolution > $parsing && $deriving > $resolution && $writing > $deriving);
            $counts = array_values(array_filter($messages, static fn (string $message): bool => preg_match('/parsing thread label records \(\d+\/252\)\./', $message) === 1));
            assertSame(4, count($counts)); // Start, 100, 200, final.
            foreach (['sort_posts', 'writing_thread_summaries', 'deriving_thread_label_records', 'writing_thread_labels', 'checking_approval_posts', 'replaying_invitation_posts', 'counting_profile_posts', 'linking_author_profiles', 'writing_post_activity', 'read_commit_history', 'processing_commit_history_lines'] as $key) {
                assertSame(true, isset($builder->timings()[$key]));
            }
            assertSame(true, str_contains($output, 'parsing post reaction records (0/0).'));
            assertSame(true, str_contains($output, 'parsing post reaction records complete'));
            assertSame(true, str_contains($output, 'approval pass 1.'));
        } finally {
            $fixture->remove();
        }
    }

    public function testFailedParsingNeverReportsSuccessAndKeepsOriginalException(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($fixture->repository . '/records/posts', FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $item) {
                if ($item->isFile()) {
                    file_put_contents($item->getPathname(), "broken\n");
                    break;
                }
            }
            $messages = [];
            $builder = new ReadModelBuilder($fixture->repository, $fixture->root . '/model.sqlite', new CanonicalRecordRepository($fixture->repository), 'failure_test', static function (string $message) use (&$messages): void { $messages[] = $message; });
            $caught = null;
            try {
                $builder->rebuild();
            } catch (\ForumRewrite\Canonical\CanonicalRecordParseException $exception) {
                $caught = $exception;
            }
            assertSame(true, $caught !== null);
            $output = implode("\n", $messages);
            assertSame(true, str_contains($output, 'parsing post records failed'));
            assertSame(true, str_contains($output, 'index posts failed'));
            assertSame(false, str_contains($output, 'index posts complete'));
        } finally {
            $fixture->remove();
        }
    }

    public function testOptionalReporterKeepsRebuildSilent(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            ob_start();
            try {
                (new ReadModelBuilder($fixture->repository, $fixture->root . '/model.sqlite', new CanonicalRecordRepository($fixture->repository)))->rebuild();
                $output = ob_get_contents();
            } finally {
                ob_end_clean();
            }
            assertSame('', $output);
        } finally {
            $fixture->remove();
        }
    }

    public function testRebuildCapturesExpectedTimingPhases(): void
    {
        $repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-builder-timing-' . bin2hex(random_bytes(6)) . '.sqlite3';
        @unlink($databasePath);

        $builder = new ReadModelBuilder(
            $repositoryRoot,
            $databasePath,
            new CanonicalRecordRepository($repositoryRoot),
            'timing_test',
        );
        $builder->rebuild();

        $timings = $builder->timings();

        assertSame(true, isset($timings['drop_schema']));
        assertSame(true, isset($timings['create_schema']));
        assertSame(true, isset($timings['index_posts']));
        assertSame(true, isset($timings['index_thread_labels']));
        assertSame(true, isset($timings['index_profiles']));
        assertSame(true, isset($timings['derive_approval_state']));
        assertSame(true, isset($timings['derive_profile_counts']));
        assertSame(true, isset($timings['link_post_authors']));
        assertSame(true, isset($timings['index_instance']));
        assertSame(true, isset($timings['index_activity']));
        assertSame(true, isset($timings['write_metadata']));
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}
