<?php

declare(strict_types=1);

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelCandidateBuilder;
use ForumRewrite\ReadModel\ReadModelCandidatePromoter;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;

require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

final class ReadModelCandidateBuilderTest
{
    public function testFailedInitialValidationCleansCandidateAndPreservesLiveModel(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $livePath = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $livePath, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $hash = hash_file('sha256', $livePath);
            $messages = [];
            $report = static function (string $message) use (&$messages, $fixture): void {
                $messages[] = $message;
                if ($message === 'Read model: validating candidate...') {
                    $candidate = (glob($fixture->root . '/.*.candidate-*.sqlite3') ?: [])[0];
                    (new PDO('sqlite:' . $candidate))->exec("UPDATE metadata SET value = 'wrong-head' WHERE key = 'repository_head'");
                }
            };
            $failed = false;
            try {
                (new ReadModelCandidateBuilder($fixture->repository, $livePath, 'validation_test', $report))->build();
            } catch (RuntimeException $exception) {
                $failed = str_contains($exception->getMessage(), 'metadata does not match');
            }
            assertSame(true, $failed);
            assertSame($hash, hash_file('sha256', $livePath));
            assertSame([], glob($fixture->root . '/.*.candidate-*.sqlite3'));
            $output = implode("\n", $messages);
            assertSame(true, str_contains($output, 'validating candidate failed'));
            assertSame(false, str_contains($output, 'validating candidate complete'));
        } finally {
            $fixture->remove();
        }
    }

    public function testPromotionFailureReportsOutcomeAndPreservesLiveModel(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $livePath = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $livePath, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $hash = hash_file('sha256', $livePath);
            $candidate = (new ReadModelCandidateBuilder($fixture->repository, $livePath))->build();
            file_put_contents($livePath . '-wal', 'test sidecar');
            $messages = [];
            try {
                (new ReadModelCandidatePromoter($fixture->repository, $livePath, static function (string $message) use (&$messages): void { $messages[] = $message; }))->promote($candidate);
                throw new RuntimeException('Expected sidecar rejection');
            } catch (RuntimeException $exception) {
                assertSame(true, str_contains($exception->getMessage(), 'sidecar prevents'));
            }
            assertSame($hash, hash_file('sha256', $livePath));
            assertSame(true, is_file($candidate));
            assertSame('test sidecar', file_get_contents($livePath . '-wal'));
            $output = implode("\n", $messages);
            assertSame(true, str_contains($output, 'Replacing the live read model failed'));
            assertSame(false, str_contains($output, 'Read-model promotion complete'));
        } finally {
            $fixture->remove();
        }
    }

    public function testLockTimeoutReportsFailureWithoutPromoting(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        $oldTimeout = getenv('FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS');
        $lock = fopen($fixture->root . '/forum-rewrite.lock', 'c+');
        try {
            $livePath = $fixture->root . '/model.sqlite';
            $candidate = (new ReadModelCandidateBuilder($fixture->repository, $livePath))->build();
            flock($lock, LOCK_EX);
            putenv('FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=0');
            $messages = [];
            try {
                (new ReadModelCandidatePromoter($fixture->repository, $livePath, static function (string $message) use (&$messages): void { $messages[] = $message; }))->promote($candidate);
                throw new RuntimeException('Expected lock timeout');
            } catch (RuntimeException $exception) {
                assertSame(true, str_contains($exception->getMessage(), 'Timed out waiting for execution lock'));
            }
            assertSame(false, is_file($livePath));
            assertSame(true, is_file($candidate));
            assertSame(true, str_contains(implode("\n", $messages), 'lock wait failed'));
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
            putenv($oldTimeout === false ? 'FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS' : 'FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=' . $oldTimeout);
            $fixture->remove();
        }
    }

    public function testBuildCreatesValidatedCandidateWithoutChangingLiveDatabase(): void
    {
        $repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
        $directory = sys_get_temp_dir() . '/forum-rewrite-candidate-' . bin2hex(random_bytes(6));
        $livePath = $directory . '/post_index.sqlite3';
        mkdir($directory, 0777, true);

        try {
            (new ReadModelBuilder($repositoryRoot, $livePath, new CanonicalRecordRepository($repositoryRoot)))->rebuild();
            $liveHash = hash_file('sha256', $livePath);
            $candidatePath = (new ReadModelCandidateBuilder($repositoryRoot, $livePath, 'candidate_test'))->build();

            assertSame(true, is_file($candidatePath));
            assertSame(true, $candidatePath !== $livePath);
            assertSame($liveHash, hash_file('sha256', $livePath));

            $candidate = new PDO('sqlite:' . $candidatePath);
            assertSame(ReadModelMetadata::SCHEMA_VERSION, $candidate->query("SELECT value FROM metadata WHERE key = 'schema_version'")->fetchColumn());
            assertSame(ReadModelMetadata::expectedSchemaIdentity()['schema_fingerprint'], $candidate->query("SELECT value FROM metadata WHERE key = 'schema_fingerprint'")->fetchColumn());
            assertSame(true, (int) $candidate->query('SELECT COUNT(*) FROM posts')->fetchColumn() > 0);
            @unlink($candidatePath);
        } finally {
            @unlink($livePath);
            @rmdir($directory);
        }
    }

    public function testPromotionAtomicallyReplacesTheLiveCandidateAndClearsStaleness(): void
    {
        $repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
        $directory = sys_get_temp_dir() . '/forum-rewrite-candidate-' . bin2hex(random_bytes(6));
        $livePath = $directory . '/post_index.sqlite3';
        mkdir($directory, 0777, true);

        try {
            (new ReadModelBuilder($repositoryRoot, $livePath, new CanonicalRecordRepository($repositoryRoot)))->rebuild();
            $candidatePath = (new ReadModelCandidateBuilder($repositoryRoot, $livePath, 'candidate_test'))->build();
            $candidateHash = hash_file('sha256', $candidatePath);
            $staleMarker = new ReadModelStaleMarker($livePath);
            $staleMarker->mark(['reason' => 'test']);

            (new ReadModelCandidatePromoter($repositoryRoot, $livePath))->promote($candidatePath);

            assertSame(false, is_file($candidatePath));
            assertSame($candidateHash, hash_file('sha256', $livePath));
            assertSame(false, $staleMarker->exists());
        } finally {
            @unlink($livePath);
            @unlink($directory . '/read_model_stale.json');
            @rmdir($directory);
        }
    }

    public function testPromotionExplainsHowToSafelyRecoverFromSqliteSidecars(): void
    {
        $repositoryRoot = __DIR__ . '/fixtures/parity_minimal_v1';
        $directory = sys_get_temp_dir() . '/forum-rewrite-candidate-' . bin2hex(random_bytes(6));
        $livePath = $directory . '/post_index.sqlite3';
        mkdir($directory, 0777, true);

        try {
            (new ReadModelBuilder($repositoryRoot, $livePath, new CanonicalRecordRepository($repositoryRoot)))->rebuild();
            $candidatePath = (new ReadModelCandidateBuilder($repositoryRoot, $livePath, 'candidate_test'))->build();
            file_put_contents($livePath . '-journal', 'test journal');

            try {
                (new ReadModelCandidatePromoter($repositoryRoot, $livePath))->promote($candidatePath);
                throw new RuntimeException('Expected SQLite sidecar promotion to fail.');
            } catch (RuntimeException $exception) {
                assertSame(true, str_contains($exception->getMessage(), 'A SQLite sidecar prevents safe read-model promotion'));
                assertSame(true, str_contains($exception->getMessage(), 'Do not delete the sidecar manually'));
            }
        } finally {
            @unlink($livePath);
            @unlink($livePath . '-journal');
            foreach (glob($directory . '/.*.candidate-*.sqlite3') ?: [] as $candidatePath) {
                @unlink($candidatePath);
            }
            @rmdir($directory);
        }
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
            );
        }
    }
}
