<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Activity\ActivityService;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\AuthoredContentRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\Tools\BoardListingQuery;

final class ReadModelPostRetractionsTest
{
    private const AUTHOR = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
    private const MARKER = 'RETRACTED-PROSE-MARKER';

    public function testRetractedRootIsSuppressedEverywhereAndRepliesRemain(): void
    {
        $root = $this->fixtureWithThread();
        $this->writeRetraction($root, 'thread-gone', self::AUTHOR);
        file_put_contents(
            $root . '/records/thread-subjects/thread-subject-20260415153100-ab12cd35.txt',
            "Record-ID: thread-subject-20260415153100-ab12cd35\nCreated-At: 2026-04-15T15:31:00Z\nThread-ID: thread-gone\nOperation: set\nSubject: " . self::MARKER . " fetched title\n\n"
        );

        $pdo = $this->rebuild($root);

        assertSame(0, $this->markerCount($pdo));
        $post = $pdo->query("SELECT subject, body, retracted_at FROM posts WHERE post_id = 'thread-gone'")->fetch(PDO::FETCH_ASSOC);
        assertSame(null, $post['subject']);
        assertSame('', $post['body']);
        assertSame('2026-10-13T12:00:00Z', $post['retracted_at']);
        $thread = $pdo->query("SELECT subject, body_preview, reply_count, retracted_at FROM threads WHERE root_post_id = 'thread-gone'")->fetch(PDO::FETCH_ASSOC);
        assertSame(null, $thread['subject']);
        assertSame('', $thread['body_preview']);
        assertSame(1, (int) $thread['reply_count']);
        assertSame('2026-10-13T12:00:00Z', $thread['retracted_at']);
        $reply = $pdo->query("SELECT body, retracted_at FROM posts WHERE post_id = 'reply-to-gone'")->fetch(PDO::FETCH_ASSOC);
        assertSame('Reply that stays visible.', trim($reply['body']));
        assertSame(null, $reply['retracted_at']);
        assertSame('Deleted post', $pdo->query("SELECT label FROM activity WHERE post_id = 'thread-gone' AND kind = 'thread'")->fetchColumn());
    }

    public function testRetractionBySomeoneElseOrOfAuthorityPostIsIgnored(): void
    {
        $root = $this->fixtureWithThread();
        $this->writeRetraction($root, 'thread-gone', 'openpgp:' . str_repeat('b', 40));

        $pdo = $this->rebuild($root);

        assertSame(null, $pdo->query("SELECT retracted_at FROM posts WHERE post_id = 'thread-gone'")->fetchColumn());
        assertSame(true, $this->markerCount($pdo) > 0);
    }

    public function testReimportingTheOriginalPostFileKeepsItSuppressed(): void
    {
        $root = $this->fixtureWithThread();
        $this->writeRetraction($root, 'thread-gone', self::AUTHOR);
        $original = (string) file_get_contents($root . '/records/posts/thread-gone.txt');
        unlink($root . '/records/posts/thread-gone.txt');
        $withoutPost = $this->rebuild($root);
        assertSame(0, (int) $withoutPost->query("SELECT COUNT(*) FROM posts WHERE post_id = 'thread-gone'")->fetchColumn());

        file_put_contents($root . '/records/posts/thread-gone.txt', $original);
        $pdo = $this->rebuild($root);

        assertSame(0, $this->markerCount($pdo));
        assertSame('2026-10-13T12:00:00Z', $pdo->query("SELECT retracted_at FROM posts WHERE post_id = 'thread-gone'")->fetchColumn());
    }

    public function testRetractedRootIsOmittedFromThreadListingsButStillResolvable(): void
    {
        $root = $this->fixtureWithThread();
        $pdo = $this->rebuild($root);
        assertTrue(in_array('thread-gone', array_column(ThreadRepository::fetchThreads($pdo), 'root_post_id'), true));

        $this->writeRetraction($root, 'thread-gone', self::AUTHOR);
        $pdo = $this->rebuild($root);

        assertSame(false, in_array('thread-gone', array_column(ThreadRepository::fetchThreads($pdo), 'root_post_id'), true));
        $listing = $pdo->query(BoardListingQuery::context('all', 'newest')['querySql'])->fetchAll(PDO::FETCH_ASSOC);
        assertSame(false, in_array('thread-gone', array_column($listing, 'root_post_id'), true));
        assertSame('thread-gone', ThreadRepository::byId($pdo, 'thread-gone')['root_post_id'] ?? null);
    }

    public function testRetractedPostsLeaveAuthoredContentListsAndCounts(): void
    {
        $root = $this->fixtureWithThread();
        $pdo = $this->rebuild($root);
        $before = AuthoredContentRepository::visiblePosts($pdo, [self::AUTHOR]);
        assertTrue(in_array('thread-gone', array_column($before, 'post_id'), true));
        assertTrue(in_array('thread-gone', array_column(AuthoredContentRepository::visibleThreads($pdo, [self::AUTHOR]), 'root_post_id'), true));

        $this->writeRetraction($root, 'thread-gone', self::AUTHOR);
        $pdo = $this->rebuild($root);

        $posts = AuthoredContentRepository::visiblePosts($pdo, [self::AUTHOR]);
        assertSame(false, in_array('thread-gone', array_column($posts, 'post_id'), true));
        assertSame(false, in_array('thread-gone', array_column(AuthoredContentRepository::visibleThreads($pdo, [self::AUTHOR]), 'root_post_id'), true));
        assertSame(count($before) - 1, AuthoredContentRepository::countVisible($pdo, [self::AUTHOR], false));
    }

    public function testRetractedPostsLeaveContentActivityAndItsTotal(): void
    {
        $root = $this->fixtureWithThread();
        $this->writeRetraction($root, 'thread-gone', self::AUTHOR);
        $pdo = $this->rebuild($root);
        $databasePath = (string) $pdo->query('PRAGMA database_list')->fetch(PDO::FETCH_ASSOC)['file'];
        $service = new ActivityService(static fn (): PDO => new PDO('sqlite:' . $databasePath), $root, $databasePath);

        $content = $service->fetchActivityRows('content', 'date', 'desc')['items'];
        $all = $service->fetchActivityRows('all', 'date', 'desc')['items'];

        assertSame(false, in_array('thread-gone', array_column($content, 'post_id'), true));
        assertTrue(in_array('reply-to-gone', array_column($content, 'post_id'), true));
        assertTrue(in_array('thread-gone', array_column($all, 'post_id'), true));
        assertSame(count($content), $service->countActivityViewTotal('content'));
    }

    public function testRetractionWithdrawsOnlyContentBoundLabelsAndSurvivesRebuild(): void
    {
        $root = $this->fixtureWithThread();
        $bytes = file_get_contents($root . '/records/posts/thread-gone.txt');
        $base = "Created-At: 2026-04-11T12:00:00Z\nThread-ID: thread-gone\nOperation: add\nLabels: pinned\nAuthor-Identity-ID: " . self::AUTHOR . "\n";
        file_put_contents($root . '/records/thread-labels/label-owned.txt', "Record-ID: label-owned\n" . $base . "Source-Post-ID: thread-gone\nSource-Post-SHA256: " . hash('sha256', $bytes) . "\n\n");
        $pdo = $this->rebuild($root);
        assertSame('["pinned"]', $pdo->query("SELECT thread_labels_json FROM threads WHERE root_post_id='thread-gone'")->fetchColumn());
        $this->writeRetraction($root, 'thread-gone', self::AUTHOR);
        $pdo = $this->rebuild($root);
        assertSame('[]', $pdo->query("SELECT thread_labels_json FROM threads WHERE root_post_id='thread-gone'")->fetchColumn());
        file_put_contents($root . '/records/thread-labels/label-independent.txt', "Record-ID: label-independent\n" . $base . "\n");
        $pdo = $this->rebuild($root);
        assertSame('["pinned"]', $pdo->query("SELECT thread_labels_json FROM threads WHERE root_post_id='thread-gone'")->fetchColumn());
        assertSame('Reply that stays visible.', trim($pdo->query("SELECT body FROM posts WHERE post_id='reply-to-gone'")->fetchColumn()));
    }

    public function testPhysicalRootRemovalKeepsContentFreeRootAndRepliesAfterReimport(): void
    {
        $root = $this->fixtureWithThread();
        $git = static fn (array $args): string => \ForumRewrite\Canonical\LabelRemoval::git($root, $args);
        $git(['init', '-q']); $git(['config', 'user.name', 'Test']); $git(['config', 'user.email', 'test@example.test']);
        $git(['add', 'records']); $git(['commit', '-qm', 'Fixture']);
        $original = file_get_contents($root . '/records/posts/thread-gone.txt');
        $record = \ForumRewrite\Canonical\LabelRemoval::prepare($root, 'thread-gone', 'delete');
        $path = \ForumRewrite\Canonical\LabelRemoval::store($root, $record);
        $git(['add', $path]); $git(['rm', 'records/posts/thread-gone.txt']);
        $git(['commit', '-qm', 'Accept source label cleanup thread-gone']);
        foreach ([false, true] as $reimport) {
            if ($reimport) file_put_contents($root . '/records/posts/thread-gone.txt', $original);
            $pdo = $this->rebuild($root);
            assertSame('', $pdo->query("SELECT body FROM posts WHERE post_id='thread-gone'")->fetchColumn());
            assertSame(null, $pdo->query("SELECT subject FROM threads WHERE root_post_id='thread-gone'")->fetchColumn());
            assertSame('Reply that stays visible.', trim($pdo->query("SELECT body FROM posts WHERE post_id='reply-to-gone'")->fetchColumn()));
            assertSame('thread-gone', ThreadRepository::byId($pdo, 'thread-gone')['root_post_id'] ?? null);
        }
    }

    private function fixtureWithThread(): string
    {
        $root = sys_get_temp_dir() . '/forum-rewrite-retraction-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        $this->copyDirectory(__DIR__ . '/fixtures/parity_minimal_v1', $root);
        file_put_contents(
            $root . '/records/posts/thread-gone.txt',
            "Post-ID: thread-gone\nCreated-At: 2026-04-11T12:00:00Z\nBoard-Tags: general\nAuthor-Identity-ID: " . self::AUTHOR . "\nSubject: " . self::MARKER . " subject\n\n" . self::MARKER . " body\n"
        );
        file_put_contents(
            $root . '/records/posts/reply-to-gone.txt',
            "Post-ID: reply-to-gone\nCreated-At: 2026-04-11T12:05:00Z\nBoard-Tags: general\nThread-ID: thread-gone\nParent-ID: thread-gone\n\nReply that stays visible.\n"
        );

        return $root;
    }

    private function writeRetraction(string $root, string $postId, string $authorIdentityId): void
    {
        @mkdir($root . '/records/post-retractions', 0777, true);
        file_put_contents(
            $root . '/records/post-retractions/post-retraction-' . $postId . '.txt',
            "Record-ID: post-retraction-{$postId}\nCreated-At: 2026-10-13T12:00:00Z\nPost-ID: {$postId}\nOperation: retract\nAuthor-Identity-ID: {$authorIdentityId}\n\n"
        );
    }

    private function rebuild(string $root): PDO
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-retraction-' . bin2hex(random_bytes(6)) . '.sqlite3';
        (new ReadModelBuilder($root, $databasePath, new CanonicalRecordRepository($root), 'retraction_test'))->rebuild();

        return new PDO('sqlite:' . $databasePath);
    }

    /** Counts marker occurrences across every text column of every table. */
    private function markerCount(PDO $pdo): int
    {
        $total = 0;
        $tables = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($tables as $table) {
            foreach ($pdo->query('PRAGMA table_info(' . $table . ')')->fetchAll(PDO::FETCH_ASSOC) as $column) {
                $stmt = $pdo->prepare('SELECT COUNT(*) FROM "' . $table . '" WHERE CAST("' . $column['name'] . '" AS TEXT) LIKE :marker');
                $stmt->execute(['marker' => '%' . self::MARKER . '%']);
                $total += (int) $stmt->fetchColumn();
            }
        }

        return $total;
    }

    private function copyDirectory(string $source, string $destination): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );
        foreach ($iterator as $item) {
            $target = $destination . '/' . $iterator->getSubPathName();
            if ($item->isDir()) {
                @mkdir($target, 0777, true);
                continue;
            }
            copy($item->getPathname(), $target);
        }
    }
}
