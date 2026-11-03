<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Canonical\AcceptedRetraction;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\PostRetractionRecordParser;
use ForumRewrite\Canonical\RetractedPosts;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\Write\LocalWriteService;
use ForumRewrite\Write\SourceLabelRemoval;

require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

/**
 * A retitle follows its source reply: retraction and delete-record withdraw it, and while
 * it stands every title consumer, static artifact and the offline snapshot queue see it.
 */
final class ThreadTitleWithdrawalAndConsumersTest
{
    private const OPERATOR = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testAuthorRetractionOfTheSourceReplyWithdrawsTheRetitle(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $this->writeAuthoredRetitle($fixture, 'Renamed before retraction');
            $databasePath = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $databasePath, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $pdo = new PDO('sqlite:' . $databasePath);
            assertSame('Renamed before retraction', $pdo->query("SELECT subject FROM threads WHERE root_post_id = 'root-001'")->fetchColumn());

            $this->writeAcceptedRetraction($fixture->repository, 'reply-title-1', self::OPERATOR);
            (new ReadModelBuilder($fixture->repository, $databasePath, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $pdo = new PDO('sqlite:' . $databasePath);
            $row = $pdo->query("SELECT subject, subject_author_identity_id FROM threads WHERE root_post_id = 'root-001'")->fetch(PDO::FETCH_ASSOC);
            assertSame('Hello world', $row['subject']);
            assertSame(null, $row['subject_author_identity_id']);
            assertSame('Hello world', $pdo->query("SELECT subject FROM posts WHERE post_id = 'root-001'")->fetchColumn());
            assertSame(1, (int) $pdo->query("SELECT COUNT(*) FROM posts WHERE post_id = 'reply-title-1' AND retracted_at IS NOT NULL")->fetchColumn());
        } finally {
            $fixture->remove();
        }
    }

    public function testDeleteRecordOfTheSourceReplyWithdrawsTheRetitleAndLeavesAnInertRecord(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        $artifacts = $fixture->root . '/static';
        mkdir($artifacts, 0777, true);
        try {
            $this->writeAuthoredRetitle($fixture, 'Renamed before deletion');
            $databasePath = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $databasePath, new CanonicalRecordRepository($fixture->repository)))->rebuild();

            $result = SourceLabelRemoval::run($fixture->repository, $databasePath, 'reply-title-1', false, [], $artifacts);
            assertSame('ok', $result['status']);
            $pdo = new PDO('sqlite:' . $databasePath);
            $row = $pdo->query("SELECT subject, subject_author_identity_id FROM threads WHERE root_post_id = 'root-001'")->fetch(PDO::FETCH_ASSOC);
            assertSame('Hello world', $row['subject']);
            assertSame(null, $row['subject_author_identity_id']);
            assertSame(false, is_file($fixture->repository . '/records/posts/reply-title-1.txt'));
            // The authored subject record is retained as history; without its source it has no effect.
            assertSame(true, is_file($fixture->repository . '/records/thread-subjects/thread-subject-20260601000003-aa000001.txt'));
            $application = new Application(dirname(__DIR__), $fixture->repository, $databasePath);
            assertStringContains('<h1>Hello world</h1>', $this->render($application, '/threads/root-001'));
        } finally {
            $fixture->remove();
        }
    }

    public function testRetitleReachesEveryTitleConsumerInvalidatesArtifactsAndEnqueuesSnapshot(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        $artifacts = $fixture->root . '/static';
        foreach (['/index.html', '/recent/index.html', '/threads/root-001.html', '/threads/root-001/index.html', '/posts/root-001.html'] as $path) {
            @mkdir(dirname($artifacts . $path), 0777, true);
            file_put_contents($artifacts . $path, 'stale');
        }
        try {
            $databasePath = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($fixture->repository, $databasePath, new CanonicalRecordRepository($fixture->repository)))->rebuild();
            $enqueued = 0;
            $service = new LocalWriteService($fixture->repository, $databasePath, $artifacts, new CanonicalRecordRepository($fixture->repository), enqueueOfflineSnapshotPublication: static function () use (&$enqueued): void { $enqueued++; });

            $service->createReply(['thread_id' => 'root-001', 'parent_id' => 'root-001', 'body' => "title: Title for every consumer\nbody", 'author_identity_id' => self::OPERATOR]);

            assertSame(1, $enqueued);
            foreach (['/index.html', '/recent/index.html', '/threads/root-001.html', '/threads/root-001/index.html', '/posts/root-001.html'] as $path) {
                assertSame(false, is_file($artifacts . $path), 'stale artifact should be invalidated: ' . $path);
            }

            $application = new Application(dirname(__DIR__), $fixture->repository, $databasePath);
            foreach ([
                '/?view=all' => 'Title for every consumer',
                '/tags/general' => 'Title for every consumer',
                '/threads/root-001' => '<h1>Title for every consumer</h1>',
                '/recent/' => 'Title for every consumer',
                '/forte' => 'Title for every consumer',
                '/forte?selected=root-001' => 'Title for every consumer',
                '/api/get_thread?thread_id=root-001' => 'Title for every consumer',
                '/api/list_index' => "root-001\tTitle for every consumer",
                '/?format=rss' => 'Title for every consumer',
                '/threads/root-001?format=rss' => 'Title for every consumer',
            ] as $path => $expected) {
                $html = $this->render($application, $path);
                assertStringContains($expected, $html);
            }
            assertStringNotContains("root-001\tHello world", $this->render($application, '/api/list_index'));
        } finally {
            $fixture->remove();
        }
    }

    private function writeAuthoredRetitle(RebuildHistoryFixture $fixture, string $title): void
    {
        $replyPath = $fixture->repository . '/records/posts/reply-title-1.txt';
        file_put_contents($replyPath, "Post-ID: reply-title-1\nCreated-At: 2026-06-01T00:00:02Z\nBoard-Tags: general\nThread-ID: root-001\nParent-ID: root-001\nAuthor-Identity-ID: " . self::OPERATOR . "\n\ntitle: {$title}\n");
        file_put_contents(
            $fixture->repository . '/records/thread-subjects/thread-subject-20260601000003-aa000001.txt',
            "Record-ID: thread-subject-20260601000003-aa000001\nCreated-At: 2026-06-01T00:00:03Z\nThread-ID: root-001\nOperation: set\nSubject: {$title}\nAuthor-Identity-ID: " . self::OPERATOR . "\nSource-Post-ID: reply-title-1\nSource-Post-SHA256: " . hash('sha256', (string) file_get_contents($replyPath)) . "\nReason: Title directive in reply\n\n"
        );
        $fixture->git('add', '.');
        $fixture->git('commit', '-qm', 'authored retitle');
    }

    /** Mirrors ReadModelPostRetractionsTest: an accepted author retraction without live signatures. */
    private function writeAcceptedRetraction(string $root, string $postId, string $authorIdentityId): void
    {
        @mkdir($root . '/records/post-retractions', 0777, true);
        $requestPath = $root . '/records/post-retractions/post-retraction-' . $postId . '.txt';
        file_put_contents($requestPath, "Record-ID: post-retraction-{$postId}\nCreated-At: 2026-10-13T12:00:00Z\nPost-ID: {$postId}\nOperation: retract\nAuthor-Identity-ID: {$authorIdentityId}\n\n");
        $source = $root . '/records/posts/' . $postId . '.txt';
        $post = (new CanonicalRecordRepository($root))->loadPost('records/posts/' . $postId . '.txt');
        $request = (new PostRetractionRecordParser())->parse((string) file_get_contents($requestPath));
        assertSame(true, RetractedPosts::applies($post, $request));
        $record = AcceptedRetraction::prepare($post, $request, (string) file_get_contents($requestPath), 'fixture signature', 'author', hash_file('sha256', $source));
        foreach (AcceptedRetraction::files($record) as $path => $bytes) {
            if (!is_dir(dirname($root . '/' . $path))) {
                mkdir(dirname($root . '/' . $path), 0775, true);
            }
            file_put_contents($root . '/' . $path, $bytes);
        }
    }

    private function render(Application $application, string $path): string
    {
        ob_start();
        try {
            $application->handle('GET', $path);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
