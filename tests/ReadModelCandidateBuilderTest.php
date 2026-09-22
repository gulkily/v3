<?php

declare(strict_types=1);

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelCandidateBuilder;
use ForumRewrite\ReadModel\ReadModelCandidatePromoter;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;

final class ReadModelCandidateBuilderTest
{
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
