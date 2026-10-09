<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Qdb\QdbVoteCaptionDatabaseConfig;
use ForumRewrite\Qdb\QdbVoteCaptionCatalog;
use ForumRewrite\Qdb\QdbVoteCaptionStore;
use ForumRewrite\Qdb\QdbVoteScoringPolicy;

final class QdbVoteCaptionStoreTest
{
    public function testDefaultsPreserveArchivedPairsAndAreIdempotent(): void
    {
        $path = sys_get_temp_dir() . '/qdb-vote-caption-store-' . bin2hex(random_bytes(8)) . '.sqlite3';
        try {
            $store = QdbVoteCaptionStore::open($path);
            $store->bootstrap();
            $store->bootstrap();

            $rows = $store->members();
            assertSame(24, count($rows));
            assertSame(['caption_set_id' => 0, 'active' => 0, 'direction' => 1, 'tag' => 'good', 'label' => 'Good', 'score' => 1], $rows[0]);
            assertSame(['caption_set_id' => 10, 'active' => 1, 'direction' => 1, 'tag' => 'keep-it', 'label' => 'Keep It', 'score' => 1], $rows[20]);
            assertSame(['caption_set_id' => 10, 'active' => 1, 'direction' => -1, 'tag' => 'trash-it', 'label' => 'Trash It', 'score' => -1], $rows[21]);

            $bySet = [];
            foreach ($rows as $row) {
                $bySet[$row['caption_set_id']][] = $row['tag'];
            }
            assertSame(['funny', 'awful'], $bySet[6]);
            assertSame(['funny', 'awful'], $bySet[8]);
        } finally {
            @unlink($path);
        }
    }

    public function testDefaultPathLivesBesideReadModel(): void
    {
        putenv('FORUM_QDB_VOTE_CAPTIONS_DATABASE_PATH');
        assertSame('/var/state/qdb_vote_captions.sqlite3', QdbVoteCaptionDatabaseConfig::path('/var/state/forum.sqlite3'));
    }

    public function testCatalogSelectsOnlyCompleteActivePairsAndRetainsRetiredTags(): void
    {
        $path = sys_get_temp_dir() . '/qdb-vote-caption-catalog-' . bin2hex(random_bytes(8)) . '.sqlite3';
        try {
            $store = QdbVoteCaptionStore::open($path);
            $store->bootstrap();
            $catalog = new QdbVoteCaptionCatalog($store);

            assertSame(9, count($catalog->activePairs()));
            assertSame(true, $catalog->isActiveTag('good'));
            assertSame(false, $catalog->isActiveTag('not'));
            assertSame(true, $catalog->isKnownTag('not'));
            assertSame(-1, $catalog->scoreForTag('not'));

            $pair = $catalog->selectActivePair();
            assertSame(true, $pair['positive']['score'] === 1 && $pair['negative']['score'] === -1);
        } finally {
            @unlink($path);
        }
    }

    public function testScoringPolicyRetainsLegacyAndArchivedCaptionScores(): void
    {
        $path = sys_get_temp_dir() . '/qdb-vote-caption-scoring-' . bin2hex(random_bytes(8)) . '.sqlite3';
        try {
            $store = QdbVoteCaptionStore::open($path);
            $store->bootstrap();
            $policy = new QdbVoteScoringPolicy(new QdbVoteCaptionCatalog($store));

            assertSame(1, $policy->scoreValueForTag('good'));
            assertSame(-1, $policy->scoreValueForTag('trash-it'));
            assertSame(true, $policy->countsTowardVoteTotal('not'));
            assertSame(true, $policy->isVoteTag('upvote'));
            assertSame(true, $policy->isVoteTag('good'));
            assertSame(false, $policy->isScoredTag('not-a-real-tag'));
        } finally {
            @unlink($path);
        }
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting identical values.');
        }
    }
}
