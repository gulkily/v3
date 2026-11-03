<?php

declare(strict_types=1);

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\IncrementalReadModelUpdater;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\SignalActors;

require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

/**
 * viewer_tags.eligible must name exactly the reactions the score reducers count, in both
 * the full rebuild and the incremental updater, and SignalActors must read that set.
 */
final class ReadModelSignalEligibilityTest
{
    private const APPROVED = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    private const UNAPPROVED = 'openpgp:cccccccccccccccccccccccccccccccccccccccc';

    public function testEligibleRowsMatchScoreCountsAndActorsResolve(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $this->writeReaction($fixture, 'thread-labels', 't1', 'root-001', 'like', self::APPROVED, '2026-06-01T00:00:01Z');
            $this->writeReaction($fixture, 'thread-labels', 't2', 'root-001', 'like', self::APPROVED, '2026-06-01T00:00:02Z');
            $this->writeReaction($fixture, 'thread-labels', 't3', 'root-001', 'like', self::UNAPPROVED, '2026-06-01T00:00:03Z');
            $this->writeReaction($fixture, 'thread-labels', 't4', 'root-001', 'bug', self::APPROVED, '2026-06-01T00:00:04Z');
            $this->writeReaction($fixture, 'post-reactions', 'p1', 'reply-001', 'like', self::APPROVED, '2026-06-01T00:00:05Z');
            $this->writeReaction($fixture, 'post-reactions', 'p2', 'reply-001', 'flag', self::APPROVED, '2026-06-01T00:00:06Z');
            $this->writeReaction($fixture, 'post-reactions', 'p3', 'reply-001', 'flag', self::APPROVED, '2026-06-01T00:00:07Z');
            $this->writeReaction($fixture, 'post-reactions', 'p4', 'reply-001', 'flag', self::UNAPPROVED, '2026-06-01T00:00:08Z');
            $fixture->git('add', '.');
            $fixture->git('commit', '-qm', 'reactions');

            $databasePath = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $databasePath, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $pdo = new PDO('sqlite:' . $databasePath);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $threadScore = (int) $pdo->query("SELECT claimed_score_total FROM threads WHERE root_post_id = 'root-001'")->fetchColumn();
            $threadLikes = (int) $pdo->query("SELECT COUNT(*) FROM viewer_tags WHERE record_family = 'thread-labels' AND target_id = 'root-001' AND tag = 'like' AND eligible >= 1")->fetchColumn();
            assertSame(1, $threadScore);
            assertSame($threadScore, $threadLikes);
            assertSame(0, (int) $pdo->query("SELECT eligible FROM viewer_tags WHERE target_id = 'root-001' AND author_identity_id = '" . self::UNAPPROVED . "'")->fetchColumn());
            assertSame(0, (int) $pdo->query("SELECT eligible FROM viewer_tags WHERE target_id = 'root-001' AND tag = 'bug'")->fetchColumn());

            $postScore = (int) $pdo->query("SELECT claimed_post_score_total FROM posts WHERE post_id = 'reply-001'")->fetchColumn();
            $postLikes = (int) $pdo->query("SELECT COUNT(*) FROM viewer_tags WHERE record_family = 'post-reactions' AND target_id = 'reply-001' AND tag = 'like' AND eligible >= 1")->fetchColumn();
            $flagCount = (int) $pdo->query("SELECT claimed_approved_flag_count FROM posts WHERE post_id = 'reply-001'")->fetchColumn();
            $flagRows = (int) $pdo->query("SELECT COUNT(*) FROM viewer_tags WHERE record_family = 'post-reactions' AND target_id = 'reply-001' AND tag = 'flag' AND eligible >= 1")->fetchColumn();
            assertSame(1, $flagCount);
            assertSame($flagCount, $flagRows);
            // Likes count +1 and flags -100 in the score; the eligible like row is the single approved liker.
            assertSame(1 - 100, $postScore);
            assertSame(1, $postLikes);
            assertSame(2, (int) $pdo->query("SELECT eligible FROM viewer_tags WHERE target_id = 'reply-001' AND tag = 'flag' AND author_identity_id = '" . self::APPROVED . "'")->fetchColumn());

            $username = (string) $pdo->query("SELECT username FROM profiles WHERE identity_id = '" . self::APPROVED . "'")->fetchColumn();
            $actors = SignalActors::load($pdo, 'root-001', ['root-001', 'reply-001']);
            assertSame([$username], array_column($actors['thread_likes'], 'username'));
            assertSame([$username], array_column($actors['posts']['reply-001']['like'], 'username'));
            assertSame([$username], array_column($actors['posts']['reply-001']['flag'], 'username'));
            assertSame([], $actors['posts']['root-001']['like']);
            assertSame(null, $actors['title_author']);
        } finally {
            $fixture->remove();
        }
    }

    public function testIncrementalReactionWritesMatchRebuildEligibility(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $this->writeReaction($fixture, 'post-reactions', 'p0', 'reply-001', 'flag', self::UNAPPROVED, '2026-06-01T00:00:01Z');
            $fixture->git('add', '.');
            $fixture->git('commit', '-qm', 'baseline reactions');
            $incremental = $fixture->root . '/incremental.sqlite';
            (new ReadModelBuilder($fixture->repository, $incremental, new CanonicalRecordRepository($fixture->repository)))->rebuild();

            $this->writeReaction($fixture, 'post-reactions', 'p1', 'reply-001', 'like', self::APPROVED, '2026-06-01T00:00:02Z');
            $this->writeReaction($fixture, 'post-reactions', 'p2', 'reply-001', 'flag', self::APPROVED, '2026-06-01T00:00:03Z');
            $this->writeReaction($fixture, 'thread-labels', 't1', 'root-001', 'like', self::APPROVED, '2026-06-01T00:00:04Z');
            $this->writeReaction($fixture, 'thread-labels', 't2', 'root-001', 'like', self::UNAPPROVED, '2026-06-01T00:00:05Z');
            $fixture->git('add', '.');
            $fixture->git('commit', '-qm', 'more reactions');
            $updater = new IncrementalReadModelUpdater($incremental, $fixture->repository);
            $updater->applyPostReactionWrite('reply-001', 'deadbeef');
            $updater->applyThreadLabelWrite('root-001', 'deadbeef');

            $rebuilt = $fixture->root . '/rebuilt.sqlite';
            (new ReadModelBuilder($fixture->repository, $rebuilt, new CanonicalRecordRepository($fixture->repository)))->rebuild();

            $sql = 'SELECT record_family, target_id, author_identity_id, tag, eligible FROM viewer_tags ORDER BY 1, 2, 3, 4';
            $incrementalRows = (new PDO('sqlite:' . $incremental))->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            $rebuiltRows = (new PDO('sqlite:' . $rebuilt))->query($sql)->fetchAll(PDO::FETCH_ASSOC);
            assertSame($rebuiltRows, $incrementalRows);
            // Five rows from this test plus the fixture's two pre-existing root-001 labels (bug, needs-review).
            assertSame(7, count($rebuiltRows));
            $levels = [];
            foreach ($rebuiltRows as $row) {
                $levels[$row['author_identity_id'] . ' ' . $row['target_id'] . ' ' . $row['tag']] = (int) $row['eligible'];
            }
            assertSame(2, $levels[self::APPROVED . ' reply-001 like']);
            assertSame(2, $levels[self::APPROVED . ' reply-001 flag']);
            assertSame(2, $levels[self::APPROVED . ' root-001 like']);
            assertSame(0, $levels[self::UNAPPROVED . ' reply-001 flag']);
            assertSame(0, $levels[self::UNAPPROVED . ' root-001 like']);
        } finally {
            $fixture->remove();
        }
    }

    private function writeReaction(RebuildHistoryFixture $fixture, string $family, string $suffix, string $targetId, string $tag, string $identity, string $createdAt): void
    {
        $prefix = $family === 'thread-labels' ? 'thread-label' : 'post-reaction';
        $kind = $family === 'thread-labels' ? 'thread' : 'post';
        $id = $prefix . '-signal-' . $suffix;
        if (!is_dir($fixture->repository . '/records/' . $family)) {
            mkdir($fixture->repository . '/records/' . $family, 0777, true);
        }
        file_put_contents(
            $fixture->repository . '/records/' . $family . '/' . $id . '.txt',
            "Record-ID: {$id}\nCreated-At: {$createdAt}\nTarget-Kind: {$kind}\nTarget-ID: {$targetId}\nOperation: add\nTags: {$tag}\nAuthor-Identity-ID: {$identity}\n\n"
        );
    }
}
