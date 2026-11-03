<?php

declare(strict_types=1);

use ForumRewrite\Application;
use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\View\TemplateRenderer;

require_once __DIR__ . '/Support/RebuildHistoryFixture.php';

/** The signals line: partial output, and its inclusion on thread, post and Forte cards. */
final class PostSignalsTemplateTest
{
    private const APPROVED = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testPartialHidesWhenEmptyAndCapsNamesAtThreePlusCount(): void
    {
        $renderer = new TemplateRenderer(dirname(__DIR__) . '/templates');
        $empty = $renderer->renderFragment('partials/post_signals.php', ['signals' => ['like' => [], 'flag' => [], 'title_author' => null]]);
        assertStringContains('data-role="post-signals" data-signal-scope="post" hidden>', $empty);
        assertStringContains('data-signal-tag="like" data-signal-count="0" hidden>', $empty);

        $actors = [];
        foreach (['ann', 'bob', 'cy', 'dee', 'eve'] as $name) {
            $actors[] = ['username' => $name, 'profile_slug' => $name === 'cy' ? null : $name . '-slug', 'identity_id' => 'openpgp:x'];
        }
        $full = $renderer->renderFragment('partials/post_signals.php', [
            'signals' => ['like' => $actors, 'flag' => [$actors[0]], 'title_author' => $actors[1]],
            'signalsScope' => 'thread',
            'signalsClass' => 'paned-post-signals',
        ]);
        assertStringContains('class="meta post-signals paned-post-signals" data-role="post-signals" data-signal-scope="thread">', $full);
        assertStringContains('data-signal-tag="like" data-signal-count="5">Liked by <span data-signal-names><a href="/profiles/ann-slug">ann</a>, <a href="/profiles/bob-slug">bob</a>, cy</span><span data-signal-more> +2</span>', $full);
        assertStringContains('data-signal-tag="flag" data-signal-count="1">Flagged by <span data-signal-names><a href="/profiles/ann-slug">ann</a></span><span data-signal-more hidden> +0</span>', $full);
        assertStringContains('<span data-signal-tag="title">Title set by <a href="/profiles/bob-slug">bob</a></span>', $full);
        assertStringNotContains('dee', $full);
    }

    public function testThreadPostAndFortePagesRenderSignalsFromReadModel(): void
    {
        $fixture = new RebuildHistoryFixture(0);
        try {
            $repository = $fixture->repository;
            $write = static function (string $family, string $id, string $kind, string $target, string $tag, string $identity): void {
                file_put_contents($GLOBALS['__signals_repo'] . "/records/{$family}/{$id}.txt", "Record-ID: {$id}\nCreated-At: 2026-06-01T00:00:01Z\nTarget-Kind: {$kind}\nTarget-ID: {$target}\nOperation: add\nTags: {$tag}\nAuthor-Identity-ID: {$identity}\n\n");
            };
            $GLOBALS['__signals_repo'] = $repository;
            @mkdir($repository . '/records/post-reactions');
            $write('thread-labels', 'thread-label-signals-1', 'thread', 'root-001', 'like', self::APPROVED);
            $write('post-reactions', 'post-reaction-signals-1', 'post', 'reply-001', 'flag', self::APPROVED);
            $write('post-reactions', 'post-reaction-signals-2', 'post', 'reply-001', 'like', self::APPROVED);
            $replyPath = $repository . '/records/posts/reply-title-1.txt';
            file_put_contents($replyPath, "Post-ID: reply-title-1\nCreated-At: 2026-06-01T00:00:02Z\nBoard-Tags: general\nThread-ID: root-001\nParent-ID: root-001\nAuthor-Identity-ID: " . self::APPROVED . "\n\ntitle: Signals title\n");
            file_put_contents(
                $repository . '/records/thread-subjects/thread-subject-20260601000003-aa000001.txt',
                "Record-ID: thread-subject-20260601000003-aa000001\nCreated-At: 2026-06-01T00:00:03Z\nThread-ID: root-001\nOperation: set\nSubject: Signals title\nAuthor-Identity-ID: " . self::APPROVED . "\nSource-Post-ID: reply-title-1\nSource-Post-SHA256: " . hash('sha256', (string) file_get_contents($replyPath)) . "\nReason: Title directive in reply\n\n"
            );
            $fixture->git('add', '.');
            $fixture->git('commit', '-qm', 'signals');
            $databasePath = $fixture->root . '/model.sqlite';
            (new ReadModelBuilder($repository, $databasePath, new CanonicalRecordRepository($repository)))->rebuild();
            $username = (string) (new PDO('sqlite:' . $databasePath))->query("SELECT username FROM profiles WHERE identity_id = '" . self::APPROVED . "'")->fetchColumn();
            $application = new Application(dirname(__DIR__), $repository, $databasePath);

            $thread = $this->render($application, 'GET', '/threads/root-001');
            assertStringContains('<h1>Signals title</h1>', $thread);
            assertSame(1, preg_match('/data-signal-scope="thread">.*?Liked by <span data-signal-names><a href="\/profiles\/[^"]+">' . preg_quote($username, '/') . '<\/a>/s', $thread));
            assertStringContains('<span data-signal-tag="title">Title set by <a href="/profiles/', $thread);
            // The reply card: like and flag by the same approved member; the plain reply with the directive has no signals and stays hidden.
            assertSame(1, preg_match('/data-post-id="reply-001".*?data-signal-scope="post">.*?Liked by <span data-signal-names>.*?' . preg_quote($username, '/') . '.*?Flagged by <span data-signal-names>.*?' . preg_quote($username, '/') . '/s', $thread));
            assertSame(1, preg_match('/data-post-id="reply-title-1".*?data-signal-scope="post" hidden>/s', $thread));

            $post = $this->render($application, 'GET', '/posts/reply-001');
            assertSame(1, preg_match('/data-signal-scope="post">.*?Flagged by <span data-signal-names>.*?' . preg_quote($username, '/') . '/s', $post));

            $forte = $this->render($application, 'GET', '/forte?selected=root-001');
            assertStringContains('paned-post-signals', $forte);
            assertSame(1, preg_match('/data-signal-scope="thread">.*?Liked by <span data-signal-names>.*?' . preg_quote($username, '/') . '/s', $forte));
        } finally {
            unset($GLOBALS['__signals_repo']);
            $fixture->remove();
        }
    }

    private function render(Application $application, string $method, string $path): string
    {
        ob_start();
        try {
            $application->handle($method, $path);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }
}
