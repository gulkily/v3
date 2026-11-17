<?php
declare(strict_types=1);

use ForumRewrite\ReadModel\PromptLibrary;
use ForumRewrite\ReadModel\ReadModelSchema;

final class PromptLibraryTest
{
    public function testApprovedAuthorOrApprovedLabelerPutsAPromptInTheLibrary(): void
    {
        $library = PromptLibrary::load($this->fixture());
        assertSame(['by-approved', 'labeled-by-approved', 'tagged-by-label'], $this->ids($library['approved']));
        assertSame(['unapproved-author', 'labeled-by-retired', 'unsigned-claim'], $this->ids($library['waiting']));
    }

    public function testAnApprovedMembersNotpromptLabelTakesAThreadOut(): void
    {
        $library = PromptLibrary::load($this->fixture());
        $listed = array_merge($this->ids($library['approved']), $this->ids($library['waiting']));
        assertSame(false, in_array('removed-by-approved', $listed, true));
        assertSame(false, in_array('removed-waiting', $listed, true));
        // A newcomer's #notprompt, or an approved member's retracted one, removes nothing.
        assertSame(true, in_array('by-approved', $listed, true));
        assertSame(true, in_array('labeled-by-approved', $listed, true));
    }

    public function testTopSortsByScoreAndNewByDate(): void
    {
        $pdo = $this->fixture();
        assertSame(['by-approved', 'labeled-by-approved', 'tagged-by-label'], $this->ids(PromptLibrary::load($pdo, 'top')['approved']));
        assertSame(['tagged-by-label', 'labeled-by-approved', 'by-approved'], $this->ids(PromptLibrary::load($pdo, 'new')['approved']));
    }

    public function testCategoriesAreTheOtherTagsWithoutReactionsOrDefaults(): void
    {
        $approved = PromptLibrary::load($this->fixture())['approved'];
        assertSame(['coding', 'review'], $approved[0]['prompt_tags']);
        assertSame('Review this diff carefully.', $approved[0]['body']);
        assertSame(['2026'], PromptLibrary::categories(['board_tags_json' => '["prompt","2026","General"]', 'thread_labels_json' => '["like"]']));
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
        foreach ([['approved', 1, 1, null], ['newcomer', 0, 0, null], ['retired', 0, 1, '2026-11-01']] as [$name, $approved, $was, $retired]) {
            $profile->execute(['id-' . $name, $name, $name, $name, $name, $approved, $was, $retired]);
        }
        $post = $pdo->prepare('INSERT INTO posts (post_id,created_at,thread_id,subject,body,board_tags_json,author_identity_id,author_label,author_profile_slug,attribution_counted,sequence_number,post_score_total) VALUES (?,?,?,?,?,?,?,?,?,?,?,?)');
        $thread = $pdo->prepare('INSERT INTO threads (root_post_id,root_post_created_at,last_activity_at,subject,body_preview,reply_count,last_post_id,board_tags_json,thread_labels_json,score_total,retracted_at) VALUES (?,?,?,?,?,0,?,?,?,?,?)');
        $label = $pdo->prepare("INSERT INTO viewer_tags (record_family,target_id,author_identity_id,tag) VALUES ('thread-labels',?,?,?)");
        // id, date, author, signed, board tags, labels, score, post score, retracted, labelers
        $rows = [
            // by-approved: a newcomer's #notprompt does not count; labeled-by-approved: a retracted #notprompt (row kept, label gone) does not either.
            ['by-approved', '2026-11-01', 'approved', 1, '["prompt","coding","general"]', '["like","notprompt","review"]', 5, 0, null, ['newcomer:notprompt']],
            ['labeled-by-approved', '2026-11-02', 'newcomer', 1, '["prompt"]', '["prompt"]', 2, 0, null, ['approved', 'approved:notprompt']],
            ['tagged-by-label', '2026-11-03', 'newcomer', 1, '["general"]', '["prompt"]', 0, 0, null, ['approved']],
            ['unapproved-author', '2026-11-04', 'newcomer', 1, '["prompt"]', '[]', 9, 0, null, []],
            ['labeled-by-retired', '2026-11-05', 'newcomer', 1, '["prompt"]', '["prompt"]', 1, 0, null, ['retired']],
            ['unsigned-claim', '2026-11-06', 'approved', 0, '["prompt"]', '[]', 0, 0, null, []],
            // Approved, but out of every listing: retracted, flagged away, or a retracted label only.
            ['retracted', '2026-11-07', 'approved', 1, '["prompt"]', '[]', 0, 0, '2026-11-08', []],
            ['flagged', '2026-11-08', 'approved', 1, '["prompt"]', '[]', 0, -100, null, []],
            ['label-retracted', '2026-11-09', 'newcomer', 1, '["general"]', '[]', 0, 0, null, ['approved']],
            ['removed-by-approved', '2026-11-11', 'approved', 1, '["prompt"]', '["notprompt","prompt"]', 3, 0, null, ['approved', 'approved:notprompt']],
            ['removed-waiting', '2026-11-12', 'newcomer', 1, '["prompt"]', '["notprompt"]', 0, 0, null, ['approved:notprompt']],
            ['not-a-prompt', '2026-11-10', 'approved', 1, '["general"]', '[]', 0, 0, null, []],
        ];
        foreach ($rows as $index => [$id, $date, $author, $signed, $tags, $labels, $score, $postScore, $retracted, $labelers]) {
            $body = $id === 'by-approved' ? 'Review this diff carefully.' : 'Prompt ' . $id;
            $post->execute([$id, $date, $id, 'Title ' . $id, $body, $tags, 'id-' . $author, $author, $author, $signed, $index, $postScore]);
            $thread->execute([$id, $date, $date, 'Title ' . $id, $body, $id, $tags, $labels, $score, $retracted]);
            foreach ($labelers as $labeler) {
                [$who, $tag] = array_pad(explode(':', $labeler, 2), 2, 'prompt');
                $label->execute([$id, 'id-' . $who, $tag]);
            }
        }

        return $pdo;
    }
}
