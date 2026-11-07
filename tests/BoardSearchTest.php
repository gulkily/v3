<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ThreadSearchPage;

/** Board search over top-level threads (`/search?q=`) on non-QDB profiles. */
final class BoardSearchTest
{
    /** @return array{0:Application,1:PDO,2:string} */
    private function environment(int $matching = 0): array
    {
        $database = tempnam(sys_get_temp_dir(), 'board-search-');
        $app = new Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $database);
        $this->render($app, '/search');
        $pdo = new PDO('sqlite:' . $database);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        for ($i = 0; $i < $matching; $i++) {
            $this->thread($pdo, sprintf('paged-%03d', $i), sprintf('2026-09-%02d', 1 + intdiv($i, 3)), 'Paged title', 'Body with Needle inside');
        }
        return [$app, $pdo, $database];
    }

    private function thread(PDO $pdo, string $id, string $activity, ?string $subject, string $body, string $tags = '[]'): void
    {
        static $sequence = 1000;
        $pdo->prepare("INSERT INTO posts (post_id,created_at,thread_id,subject,body,board_tags_json,sequence_number) VALUES (?, ?, ?, ?, ?, ?, ?)")
            ->execute([$id, $activity, $id, $subject, $body, $tags, $sequence++]);
        $pdo->prepare("INSERT INTO threads (root_post_id,root_post_created_at,last_activity_at,subject,body_preview,reply_count,last_post_id,board_tags_json,thread_labels_json) VALUES (?, ?, ?, ?, ?, 0, ?, ?, '[]')")
            ->execute([$id, $activity, $activity, $subject, substr($body, 0, 40), $id, $tags]);
    }

    private function render(Application $app, string $url): string
    {
        http_response_code(200);
        ob_start();
        $app->handle('GET', $url);
        return (string) ob_get_clean();
    }

    /** @param list<string> $ids */
    private function ids(array $page): array
    {
        return array_column($page['items'], 'root_post_id');
    }

    public function testMatchesTitleOrOpeningBodyLiterallyWithBoardEligibility(): void
    {
        [$app, $pdo, $database] = $this->environment();
        try {
            $this->thread($pdo, 'title-hit', '2026-10-03', 'All about NEEDLES', 'Plain body');
            $this->thread($pdo, 'body-hit', '2026-10-02', 'Plain title', 'a needle (literal) here');
            $this->thread($pdo, 'miss', '2026-10-04', 'Plain title', 'Nothing relevant');
            $this->thread($pdo, 'retracted-hit', '2026-10-05', 'needle', 'needle');
            $pdo->exec("UPDATE threads SET retracted_at = '2026-10-06' WHERE root_post_id = 'retracted-hit'");
            $this->thread($pdo, 'bootstrap-hit', '2026-10-05', 'needle', 'needle', '["identity"]');
            $this->thread($pdo, 'reply-only', '2026-10-01', 'Plain title', 'Plain body');
            $pdo->exec("INSERT INTO posts (post_id,created_at,thread_id,parent_id,body,board_tags_json,sequence_number) VALUES ('reply-only-r','2026-10-01','reply-only','reply-only','needle in a reply','[]',5000)");

            assertSame(['title-hit', 'body-hit'], $this->ids(ThreadSearchPage::page($pdo, '  NeEdLe ')));
            assertSame(['body-hit'], $this->ids(ThreadSearchPage::page($pdo, 'needle (literal)')));
            assertSame([], $this->ids(ThreadSearchPage::page($pdo, 'need%le')));

            $html = $this->render($app, '/search?q=needle');
            assertSame(200, http_response_code());
            assertStringContains('value="needle"', $html);
            assertStringContains('href="/threads/title-hit"', $html);
            assertStringContains('href="/threads/body-hit"', $html);
            foreach (['miss', 'retracted-hit', 'bootstrap-hit', 'reply-only'] as $excluded) {
                assertStringNotContains('href="/threads/' . $excluded . '"', $html);
            }
            assertStringNotContains('rel="next"', $html);
        } finally { @unlink($database); }
    }

    public function testEmptyAndUnmatchedQueriesRenderTheFormAndEmptyState(): void
    {
        [$app, , $database] = $this->environment();
        try {
            foreach (['/search', '/search?q=', '/search?q=%20%20', '/search?q[]=x'] as $url) {
                $html = $this->render($app, $url);
                assertSame(200, http_response_code(), $url);
                assertStringContains('action="/search" method="get" role="search"', $html);
                assertStringContains('name="q" value=""', $html);
                assertStringNotContains('class="thread-row"', $html);
                assertStringNotContains('No matching threads', $html);
            }
            $html = $this->render($app, '/search?q=' . rawurlencode('<b>absent</b>'));
            assertStringContains('No matching threads', $html);
            assertStringContains('value="&lt;b&gt;absent&lt;/b&gt;"', $html);
            assertStringNotContains('<b>absent</b>', $html);
        } finally { @unlink($database); }
    }

    public function testNumberedPagesCoverResultsAndLiveRetraction(): void
    {
        [$app, $pdo, $database] = $this->environment(60);
        try {
            $expected = $pdo->query("SELECT root_post_id FROM threads WHERE root_post_id LIKE 'paged-%' ORDER BY last_activity_at DESC, root_post_id DESC")->fetchAll(PDO::FETCH_COLUMN);
            $pages = []; $number = 1;
            do {
                $pages[] = $page = ThreadSearchPage::page($pdo, 'needle', $number);
                foreach ($page['items'] as $thread) assertSame($number, ThreadSearchPage::pageOf($pdo, 'needle', $thread['root_post_id']));
                $number++;
            } while ($page['has_next']);
            assertSame(3, count($pages));
            assertSame($expected, array_merge(...array_map(fn (array $page): array => $this->ids($page), $pages)));

            $html = $this->render($app, '/search?q=needle');
            assertStringContains('rel="next" href="/search?q=needle&amp;page=2"', $html);
            $second = $this->render($app, '/search?q=needle&page=2');
            assertStringContains('href="/threads/' . $expected[25] . '"', $second);
            assertStringContains('rel="prev" href="/search?q=needle"', $second);
            assertStringNotContains('href="/threads/' . $expected[0] . '"', $second);
            assertStringContains('/search?q=needle&amp;page=2#post-' . $expected[25], $this->render($app, '/search?q=needle&with=' . $expected[25]));

            $pdo->exec("UPDATE threads SET retracted_at = '2026-11-03' WHERE root_post_id = '" . $expected[0] . "'");
            assertStringNotContains('href="/threads/' . $expected[0] . '"', $this->render($app, '/search?q=needle'));
            assertSame($expected[1], ThreadSearchPage::page($pdo, 'needle')['items'][0]['root_post_id']);
            assertSame(null, ThreadSearchPage::pageOf($pdo, 'needle', $expected[0]));
        } finally { @unlink($database); }
    }

    public function testMalformedAndExhaustedPagesOfferFirstPage(): void
    {
        [$app, , $database] = $this->environment(30);
        try {
            foreach (['/search?q=needle&page=bad', '/search?q=needle&page[]=bad', '/search?q=needle&position=old', '/search?q=needle&with=no-such-thread'] as $url) {
                $html = $this->render($app, $url);
                assertSame(200, http_response_code(), $url);
                assertSame(25, substr_count($html, 'class="thread-row"'), $url . ' shows page 1');
            }
            $past = $this->render($app, '/search?q=needle&page=9');
            assertStringContains('href="/search?q=needle">First page</a>', $past);
            assertStringNotContains('class="thread-row"', $past);
        } finally { @unlink($database); }
    }

    public function testVersion46StartupRebuildsBeforeSearchQuery(): void
    {
        [, $pdo, $database] = $this->environment(3);
        try {
            $pdo->exec("UPDATE metadata SET value = '46' WHERE key = 'schema_version'");
            $fresh = new Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $database);
            $html = $this->render($fresh, '/search?q=hello');
            assertStringContains('href="/threads/root-001"', $html);
            assertSame(ReadModelMetadata::SCHEMA_VERSION, $pdo->query("SELECT value FROM metadata WHERE key='schema_version'")->fetchColumn());
            assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM threads WHERE root_post_id LIKE 'paged-%'")->fetchColumn());
        } finally { @unlink($database); }
    }

    public function testQdbKeepsClassicSearchAndOtherProfilesRejectTheQdbFlag(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'board-search-qdb-');
        try {
            putenv('FORUM_SITE_ID=qdb');
            $qdb = $this->render(new Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $database), '/search?q=hello&search=hello');
            assertStringContains('placeholder="Search quotes..."', $qdb);
            assertStringNotContains('name="q"', $qdb);
        } finally {
            putenv('FORUM_SITE_ID');
            @unlink($database);
        }
        [$app, , $database] = $this->environment();
        try {
            $this->render($app, '/?search=hello');
            assertSame(404, http_response_code());
            $html = $this->render($app, '/search?search=hello');
            assertSame(200, http_response_code());
            assertStringNotContains('class="thread-row"', $html);
        } finally { @unlink($database); }
    }

    public function testMembersOnlySitesKeepSearchFromUnapprovedVisitors(): void
    {
        $database = tempnam(sys_get_temp_dir(), 'board-search-members-');
        try {
            putenv('FORUM_APPROVED_MEMBERS_ONLY=true');
            $app = new Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $database);
            $html = $this->render($app, '/search?q=hello');
            assertSame(401, http_response_code());
            assertStringNotContains('Hello world', $html);
        } finally {
            putenv('FORUM_APPROVED_MEMBERS_ONLY');
            @unlink($database);
        }
    }
}
