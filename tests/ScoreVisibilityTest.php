<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/RecentPostFixture.php';

use ForumRewrite\ReadModel\AuthoredContentRepository;
use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\ReadModel\RecentPostRepository;
use ForumRewrite\ReadModel\ThreadKeysetPage;
use ForumRewrite\ReadModel\ThreadRepository;

/** Negative-score (flagged) posts stay out of public listings. */
final class ScoreVisibilityTest
{
    private function database(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        foreach (ReadModelSchema::statements() as $statement) $pdo->exec($statement);
        RecentPostFixture::seed($pdo);
        $thread = $pdo->prepare("INSERT INTO threads (root_post_id,root_post_created_at,last_activity_at,body_preview,reply_count,last_post_id,board_tags_json,thread_labels_json)
            VALUES (?,'2026-10-30T12:00:00Z','2026-10-30T12:00:00Z','',0,?,'[]','[]')");
        foreach (['old-thread', 'new-thread'] as $id) $thread->execute([$id, $id]);
        $pdo->exec("UPDATE posts SET author_identity_id='author' WHERE post_id IN ('reply-2','reply-3','new-thread')");
        return $pdo;
    }

    public function testNegativeScoreRootsAndRepliesLeavePublicListings(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE posts SET post_score_total=-100 WHERE post_id IN ('new-thread','reply-2')");
        $pdo->exec("UPDATE posts SET post_score_total=0 WHERE post_id='reply-3'");

        $threads = array_column(ThreadRepository::fetchThreads($pdo), 'root_post_id');
        assertSame(['old-thread'], $threads);
        $keyset = ThreadKeysetPage::select($pdo, ThreadKeysetPage::listedRootsSql(), []);
        assertSame(['old-thread'], array_column($keyset['items'], 'root_post_id'));

        $recent = array_column(RecentPostRepository::page($pdo)['items'], 'post_id');
        assertTrue(!in_array('new-thread', $recent, true));
        assertTrue(!in_array('reply-2', $recent, true));
        assertTrue(in_array('reply-3', $recent, true));

        $profile = array_column(AuthoredContentRepository::visiblePosts($pdo, ['author']), 'post_id');
        assertSame(['reply-3'], $profile);
    }

    public function testFlaggedThreadHidesItsRepliesFromRecent(): void
    {
        $pdo = $this->database();
        $pdo->exec("UPDATE posts SET post_score_total=-100 WHERE post_id='old-thread'");
        $recent = array_column(RecentPostRepository::page($pdo)['items'], 'post_id');
        foreach (['old-thread', 'reply-1', 'reply-4'] as $id) assertTrue(!in_array($id, $recent, true));
        assertTrue(in_array('new-thread', $recent, true));
    }
}
