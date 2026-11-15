<?php

declare(strict_types=1);

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\IncrementalReadModelUpdater;
use ForumRewrite\ReadModel\ReadModelBuilder;

require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

/**
 * A post reaction write that names the files it added reads only that post's records,
 * and must leave the read model exactly as the full family scan does.
 */
final class ScopedPostReactionUpdateTest
{
    private const APPROVED = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    private const UNAPPROVED = 'openpgp:cccccccccccccccccccccccccccccccccccccccc';

    public function testScopedWriteMatchesFullScan(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $this->writeReaction($fixture, 'a1', 'reply-001', 'like', self::APPROVED, '2026-06-01T00:00:01Z');
            $this->writeReaction($fixture, 'a2', 'reply-001', 'flag', self::UNAPPROVED, '2026-06-01T00:00:02Z');
            $this->writeReaction($fixture, 'a3', 'root-001', 'like', self::APPROVED, '2026-06-01T00:00:03Z');
            // A signature that does not verify keeps its own row either way.
            file_put_contents($fixture->repository . '/records/post-reactions/post-reaction-scoped-a1.txt.asc', "not a signature\n");
            $fixture->git('add', '.');
            $fixture->git('commit', '-qm', 'baseline reactions');
            $scoped = $fixture->root . '/scoped.sqlite';
            (new ReadModelBuilder($fixture->repository, $scoped, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $full = $fixture->root . '/full.sqlite';
            copy($scoped, $full);

            // Close, then Resolve, as two writes.
            $closed = $this->writeReaction($fixture, 'b1', 'reply-001', 'closed', self::APPROVED, '2026-06-01T00:00:04Z');
            (new IncrementalReadModelUpdater($scoped, $fixture->repository))->applyPostReactionWrite('reply-001', 'deadbeef', [$closed]);
            (new IncrementalReadModelUpdater($full, $fixture->repository))->applyPostReactionWrite('reply-001', 'deadbeef');
            $resolved = $this->writeReaction($fixture, 'b2', 'reply-001', 'resolved', self::APPROVED, '2026-06-01T00:00:05Z');
            (new IncrementalReadModelUpdater($scoped, $fixture->repository))->applyPostReactionWrite('reply-001', 'deadbeef', [$resolved]);
            (new IncrementalReadModelUpdater($full, $fixture->repository))->applyPostReactionWrite('reply-001', 'deadbeef');

            foreach ([
                'SELECT * FROM posts ORDER BY post_id',
                'SELECT created_at, kind, post_id, label, author_identity_id, source_path FROM activity WHERE kind = \'post_reaction_add\' ORDER BY source_path',
                'SELECT * FROM viewer_tags ORDER BY 1, 2, 3, 4',
                'SELECT * FROM reaction_signatures ORDER BY source_path',
            ] as $sql) {
                assertSame($this->rows($full, $sql), $this->rows($scoped, $sql));
            }
            $post = $this->rows($scoped, "SELECT resolution_state FROM posts WHERE post_id = 'reply-001'");
            assertSame('resolved', $post[0]['resolution_state']);
            // a1's bad signature keeps it out of the reactions in both paths; a2, b1 and b2 remain.
            assertSame(3, count($this->rows($scoped, "SELECT 1 FROM activity WHERE kind = 'post_reaction_add' AND post_id = 'reply-001'")));
            $signature = $this->rows($scoped, "SELECT signature_status FROM reaction_signatures WHERE source_path = 'records/post-reactions/post-reaction-scoped-a1.txt'");
            assertSame('invalid', $signature[0]['signature_status']);
        } finally {
            $fixture->remove();
        }
    }

    public function testScopedWriteSkipsFilesThatAreGone(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $gone = $this->writeReaction($fixture, 'c1', 'reply-001', 'flag', self::APPROVED, '2026-06-01T00:00:01Z');
            $fixture->git('add', '.');
            $fixture->git('commit', '-qm', 'baseline reaction');
            $database = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $database, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            unlink($fixture->repository . '/' . $gone);

            $liked = $this->writeReaction($fixture, 'c2', 'reply-001', 'like', self::APPROVED, '2026-06-01T00:00:02Z');
            (new IncrementalReadModelUpdater($database, $fixture->repository))->applyPostReactionWrite('reply-001', 'deadbeef', [$liked]);

            $rows = $this->rows($database, "SELECT source_path FROM activity WHERE kind = 'post_reaction_add' AND post_id = 'reply-001'");
            assertSame([$liked], array_column($rows, 'source_path'));
            $post = $this->rows($database, "SELECT claimed_approved_flag_count, claimed_post_score_total FROM posts WHERE post_id = 'reply-001'");
            assertSame(0, (int) $post[0]['claimed_approved_flag_count']);
            assertSame(1, (int) $post[0]['claimed_post_score_total']);
        } finally {
            $fixture->remove();
        }
    }

    /** @return list<array<string, mixed>> */
    private function rows(string $database, string $sql): array
    {
        return (new PDO('sqlite:' . $database))->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    }

    private function writeReaction(RebuildHistoryFixture $fixture, string $suffix, string $postId, string $tag, string $identity, string $createdAt): string
    {
        $id = 'post-reaction-scoped-' . $suffix;
        $path = 'records/post-reactions/' . $id . '.txt';
        if (!is_dir($fixture->repository . '/records/post-reactions')) {
            mkdir($fixture->repository . '/records/post-reactions', 0777, true);
        }
        file_put_contents(
            $fixture->repository . '/' . $path,
            "Record-ID: {$id}\nCreated-At: {$createdAt}\nTarget-Kind: post\nTarget-ID: {$postId}\nOperation: add\nTags: {$tag}\nAuthor-Identity-ID: {$identity}\n\n"
        );

        return $path;
    }
}
