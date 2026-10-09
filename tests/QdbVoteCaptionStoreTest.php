<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Qdb\QdbVoteCaptionDatabaseConfig;
use ForumRewrite\Qdb\QdbVoteCaptionStore;

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
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting identical values.');
        }
    }
}
