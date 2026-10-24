<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Images\ImageBackup;
use ForumRewrite\ReadModel\ReadModelBuilder;

final class RegistrationBenchmarkTest
{
    public function testRealRegistrationCasesPreserveInputsAndRefuseReusedOutput(): void
    {
        $root = sys_get_temp_dir() . '/registration-benchmark-test-' . bin2hex(random_bytes(6));
        mkdir($root, 0700);
        try {
            $repository = $root . '/repository';
            ImageBackup::copyTree(__DIR__ . '/fixtures/parity_minimal_v1', $repository);
            foreach ([['init', '-q'], ['config', 'user.name', 'Benchmark test'], ['config', 'user.email', 'test@example.invalid'],
                ['config', 'commit.gpgsign', 'false'], ['config', 'core.hooksPath', '/dev/null'], ['add', '.'], ['commit', '-qm', 'Fixture']] as $args) {
                [$exit, , $error] = $this->run(['git', '-C', $repository, ...$args], $root);
                assertSame(0, $exit, $error);
            }
            $database = $root . '/read_model.sqlite3';
            (new ReadModelBuilder($repository, $database, new CanonicalRecordRepository($repository), 'benchmark_fixture'))->rebuild();
            $observer = new PDO('sqlite:' . $database);
            $observer->exec('CREATE TABLE benchmark_profile_updates (identity_id TEXT NOT NULL)');
            $observer->exec('CREATE TRIGGER benchmark_profile_write AFTER UPDATE ON profiles BEGIN INSERT INTO benchmark_profile_updates VALUES (NEW.identity_id); END');
            $observer = null;
            $hash = hash_file('sha256', $database);
            $source = new PDO('sqlite:' . $database);
            $profiles = (int) $source->query('SELECT COUNT(*) FROM profiles')->fetchColumn();
            $source = null;
            $args = [PHP_BINARY, dirname(__DIR__) . '/scripts/benchmark_profile_registration.php',
                '--repository-root=' . $repository, '--database-path=' . $database,
                '--output-dir=' . $root . '/run', '--site=qdb'];
            [$exit, $stdout, $error] = $this->run($args, $root);
            assertSame(0, $exit, $error);
            assertStringContains('Report:', $stdout);
            $report = json_decode(file_get_contents($root . '/run/report.json'), true, flags: JSON_THROW_ON_ERROR);
            assertSame(true, $report['source_inputs_unchanged']);
            assertSame($hash, hash_file('sha256', $database));
            assertSame(['fresh_signed', 'historical_link'], array_keys($report['cases']));
            foreach ($report['cases'] as $name => $case) {
                assertSame($profiles + 1, $case['counts_after']['profiles']);
                assertSame('write_incremental', $case['rebuild_reason']);
                assertSame('ok', $case['quick_check']);
                assertSame(false, file_exists($root . '/run/' . $name . '/gnupg'));
                $expectedPosts = $case['counts_before']['posts'] + ($name === 'fresh_signed' ? 1 : 0);
                assertSame($expectedPosts, $case['counts_after']['posts']);
                $observed = new PDO('sqlite:' . $root . '/run/' . $name . '/read_model.sqlite3');
                $writes = (int) $observed->query('SELECT COUNT(*) FROM benchmark_profile_updates')->fetchColumn();
                assertSame($name === 'fresh_signed' ? 0 : 1, $writes);
                assertSame($writes, $case['identity_update_metrics']['profile_rows_updated']);
                $observed = null;
                assertSame('incremental', $case['identity_update_metrics']['path']);
                foreach (['profiles_considered', 'posts_considered', 'profile_rows_updated', 'activity_update_statements', 'activity_row_updates', 'process_peak_memory_bytes'] as $metric) {
                    assertSame(true, is_int($case['identity_update_metrics'][$metric]));
                    assertSame(true, $case['identity_update_metrics'][$metric] >= 0);
                    assertSame(false, isset($case['timings_ms'][$metric]));
                }
                foreach (['lock_wait', 'lock_hold', 'read_model_incremental_profile_load', 'read_model_incremental_profile_posts_load', 'read_model_incremental_profile_approval_derive', 'read_model_incremental_profile_counts_derive', 'read_model_incremental_profile_rows_update', 'read_model_incremental_profile_activity_refresh', 'read_model_incremental_transaction_commit', 'artifact_invalidate'] as $timing) {
                    assertSame(true, isset($case['timings_ms'][$timing]), $timing);
                    assertSame(true, $case['timings_ms'][$timing] >= 0);
                }
            }
            $reportHash = hash_file('sha256', $root . '/run/report.json');
            [$exit, , $error] = $this->run($args, $root);
            assertSame(1, $exit);
            assertStringContains('Output directory must not exist', $error);
            assertSame($reportHash, hash_file('sha256', $root . '/run/report.json'));

            // A mismatched source pair must never become a measured "baseline".
            $source = new PDO('sqlite:' . $database);
            $source->exec("UPDATE metadata SET value = 'wrong-head' WHERE key = 'repository_head'");
            $source = null;
            $mismatchHash = hash_file('sha256', $database);
            $args[4] = '--output-dir=' . $root . '/mismatch';
            [$exit, , $error] = $this->run($args, $root);
            assertSame(1, $exit);
            assertStringContains('heads do not match', $error);
            assertSame($mismatchHash, hash_file('sha256', $database));
            assertSame(false, is_dir($root . '/mismatch/fresh_signed'));
        } finally {
            ImageBackup::removeTree($root);
        }
    }

    /** @param list<string> $args @return array{int,string,string} */
    private function run(array $args, string $work): array
    {
        $process = proc_open($args, [0 => ['file', '/dev/null', 'r'], 1 => ['file', $work . '/stdout', 'w'], 2 => ['file', $work . '/stderr', 'w']], $pipes);
        if (!is_resource($process)) {
            throw new RuntimeException('Could not start benchmark test process.');
        }
        return [proc_close($process), file_get_contents($work . '/stdout'), file_get_contents($work . '/stderr')];
    }
}
