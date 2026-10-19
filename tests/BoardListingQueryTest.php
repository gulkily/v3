<?php
declare(strict_types=1);

use ForumRewrite\Http\BoardPageController;
use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\Tools\BoardListingQuery;

final class BoardListingQueryTest
{
    public function testAllBoardOrderingMatchesControllerWithoutRowCap(): void
    {
        $pdo = $this->fixture();
        $controller = (new ReflectionClass(BoardPageController::class))->newInstanceWithoutConstructor();
        $compare = new ReflectionMethod(BoardPageController::class, 'compareThreads');
        foreach (['newest', 'oldest', 'top', 'leetness', 'unknown'] as $sort) {
            $expected = ThreadRepository::fetchThreads($pdo);
            usort($expected, fn (array $a, array $b): int => $compare->invoke($controller, $a, $b, $sort, null));
            $actual = $pdo->query(BoardListingQuery::context('all', $sort)['querySql'])->fetchAll(PDO::FETCH_ASSOC);
            assertSame(array_column($expected, 'root_post_id'), array_column($actual, 'root_post_id'));
            assertTrue(count($actual) > 100);
        }
        $pdo->exec('DELETE FROM threads');
        assertSame([], $pdo->query(BoardListingQuery::context('all', 'newest')['querySql'])->fetchAll());
    }

    public function testLikedFiltersAndSortsMatchController(): void
    {
        $pdo = $this->fixture();
        $controller = (new ReflectionClass(BoardPageController::class))->newInstanceWithoutConstructor();
        $matches = new ReflectionMethod(BoardPageController::class, 'matchesView');
        $compare = new ReflectionMethod(BoardPageController::class, 'compareThreads');
        foreach (['liked', 'all', 'unknown'] as $view) {
            foreach (['newest', 'oldest', 'top', 'leetness', 'unknown'] as $sort) {
                $expected = array_values(array_filter(ThreadRepository::fetchThreads($pdo), fn (array $row): bool => $matches->invoke($controller, $row, $view)));
                usort($expected, fn (array $a, array $b): int => $compare->invoke($controller, $a, $b, $sort, null));
                $actual = $pdo->query(BoardListingQuery::context($view, $sort)['querySql'])->fetchAll(PDO::FETCH_ASSOC);
                assertSame(array_column($expected, 'root_post_id'), array_column($actual, 'root_post_id'));
            }
        }
        $pdo->exec('UPDATE posts SET post_score_total = -1');
        assertSame([], $pdo->query(BoardListingQuery::context('liked', 'top')['querySql'])->fetchAll());
    }

    public function fixture(): PDO
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        foreach (ReadModelSchema::statements() as $sql) { $pdo->exec($sql); }
        $post = $pdo->prepare('INSERT INTO posts (post_id,created_at,thread_id,body,board_tags_json,sequence_number,post_score_total,is_hidden) VALUES (?,?,?,?,?,?,?,?)');
        $thread = $pdo->prepare('INSERT INTO threads (root_post_id,root_post_created_at,last_activity_at,body_preview,reply_count,last_post_id,board_tags_json,thread_labels_json,score_total,retracted_at) VALUES (?,?,?,?,?,?,?,?,?,?)');
        for ($i = 0; $i < 130; $i++) {
            $id = sprintf('thread-%03d', $i);
            $date = '2026-10-' . sprintf('%02d', 1 + ($i % 10));
            $tags = $i === 0 ? '["identity"]' : ($i === 1 ? 'broken' : ($i === 2 ? '"identity"' : '[]'));
            $labels = json_encode(array_merge($i % 9 === 0 ? ['pinned'] : [], $i % 2 === 0 ? ['like'] : []));
            $post->execute([$id, $date, $id, 'Body', $tags, $i, ($i % 3) - 1, $i % 2]);
            $thread->execute([$id, $date, $date, 'Body', 0, $id, $tags, $labels, $i % 4, $i === 3 ? $date : null]);
        }
        return $pdo;
    }
}
