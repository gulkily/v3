<?php
declare(strict_types=1);

use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\ReadModel\ZenStrip;
use ForumRewrite\SiteProfileRegistry;

final class ZenStripTest
{
    public function testEachTagMustComeFromAnApprovedMember(): void
    {
        assertSame(
            ['mixed-sources', 'both-labeled', 'retired-author', 'by-approved'],
            $this->ids(ZenStrip::load($this->fixture(), ['zen', 'meme'])),
        );
    }

    public function testLimitAndEmptyTags(): void
    {
        $pdo = $this->fixture();
        assertSame(['mixed-sources', 'both-labeled'], $this->ids(ZenStrip::load($pdo, ['zen', 'meme'], 2)));
        assertSame([], ZenStrip::load($pdo, []));
        assertSame($this->ids(ZenStrip::load($pdo, ['zen', 'meme'])), $this->ids(ZenStrip::load($pdo, ['Meme', 'ZEN'])));
    }

    public function testTheHighestScoreLeadsAndATieGoesToTheNewest(): void
    {
        $pdo = $this->fixture();
        // Equal scores: newest first, as before.
        assertSame('mixed-sources', ZenStrip::load($pdo, ['zen', 'meme'])[0]['root_post_id']);

        $pdo->exec("UPDATE threads SET score_total = 2 WHERE root_post_id IN ('by-approved', 'retired-author')");
        $pdo->exec("UPDATE threads SET score_total = -1 WHERE root_post_id = 'mixed-sources'");
        $rows = ZenStrip::load($pdo, ['zen', 'meme']);
        assertSame(['retired-author', 'by-approved', 'both-labeled', 'mixed-sources'], $this->ids($rows));
        assertSame([2, 2, 0, -1], array_column($rows, 'score_total'));
    }

    public function testRowsCarryWhatACardShows(): void
    {
        $row = ZenStrip::load($this->fixture(), ['zen', 'meme'])[0];
        assertSame(['root_post_id', 'root_post_created_at', 'subject', 'body_preview', 'score_total'], array_keys($row));
        assertSame('Title mixed-sources', $row['subject']);
    }

    public function testZenmemesProfileNamesItsTags(): void
    {
        assertSame(['zen', 'meme'], SiteProfileRegistry::all()['zenmemes']['zenStripTags']);
        assertSame(false, isset(SiteProfileRegistry::all()['chouse']['zenStripTags']));
    }

    /** @param list<array<string, mixed>> $rows */
    private function ids(array $rows): array
    {
        return array_column($rows, 'root_post_id');
    }

    private function fixture(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        foreach (ReadModelSchema::statements() as $sql) { $pdo->exec($sql); }
        $profile = $pdo->prepare("INSERT INTO profiles (identity_id,profile_slug,username,username_token,fallback_label,signer_fingerprint,bootstrap_post_id,bootstrap_thread_id,public_key,is_approved,was_approved,retired_at) VALUES (?,?,?,?,?,'fp','b','b','k',?,?,?)");
        foreach ([['approved', 1, 1, null], ['pending', 0, 0, null], ['retired', 0, 1, '2026-11-01']] as [$name, $approved, $was, $retired]) {
            $profile->execute(['id-' . $name, $name, $name, $name, $name, $approved, $was, $retired]);
        }
        $post = $pdo->prepare('INSERT INTO posts (post_id,created_at,thread_id,subject,body,board_tags_json,author_identity_id,author_label,author_profile_slug,attribution_counted,sequence_number,post_score_total) VALUES (?,?,?,?,?,?,?,?,?,?,?,0)');
        $thread = $pdo->prepare('INSERT INTO threads (root_post_id,root_post_created_at,last_activity_at,subject,body_preview,reply_count,last_post_id,board_tags_json,thread_labels_json,score_total,retracted_at) VALUES (?,?,?,?,?,0,?,?,?,0,?)');
        $label = $pdo->prepare("INSERT INTO viewer_tags (record_family,target_id,author_identity_id,tag) VALUES ('thread-labels',?,?,?)");
        // id, date, author, signed, board tags, labels on the thread, retracted, label records (labeler:tag)
        $rows = [
            ['by-approved', '2026-11-01', 'approved', 1, '["zen","meme"]', '[]', null, []],
            ['retired-author', '2026-11-02', 'retired', 1, '["zen","meme"]', '[]', null, []],
            ['both-labeled', '2026-11-03', 'pending', 1, '["general"]', '["zen","meme"]', null, ['approved:zen', 'approved:meme']],
            ['mixed-sources', '2026-11-04', 'approved', 1, '["zen"]', '["meme"]', null, ['approved:meme']],
            // Out: one tag from a pending key, an unsigned post, one tag only, retracted label or thread, a retired labeler.
            ['pending-label', '2026-11-05', 'approved', 1, '["zen"]', '["meme"]', null, ['pending:meme']],
            ['pending-author', '2026-11-06', 'pending', 1, '["zen","meme"]', '[]', null, []],
            ['unsigned', '2026-11-07', 'approved', 0, '["zen","meme"]', '[]', null, []],
            ['zen-only', '2026-11-08', 'approved', 1, '["zen"]', '[]', null, []],
            ['label-retracted', '2026-11-09', 'pending', 1, '["general"]', '["zen"]', null, ['approved:zen', 'approved:meme']],
            ['retracted', '2026-11-10', 'approved', 1, '["zen","meme"]', '[]', '2026-11-11', []],
            ['retired-labeler', '2026-11-12', 'pending', 1, '["general"]', '["zen","meme"]', null, ['retired:zen', 'retired:meme']],
        ];
        foreach ($rows as $index => [$id, $date, $author, $signed, $tags, $labels, $retracted, $labelers]) {
            $post->execute([$id, $date, $id, 'Title ' . $id, 'Body ' . $id, $tags, 'id-' . $author, $author, $author, $signed, $index]);
            $thread->execute([$id, $date, $date, 'Title ' . $id, 'Body ' . $id, $id, $tags, $labels, $retracted]);
            foreach ($labelers as $labeler) {
                [$who, $tag] = explode(':', $labeler, 2);
                $label->execute([$id, 'id-' . $who, $tag]);
            }
        }

        return $pdo;
    }
}
