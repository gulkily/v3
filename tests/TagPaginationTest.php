<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Application;
use ForumRewrite\ReadModel\TagPage;
use ForumRewrite\ReadModel\TagGrouping;
use ForumRewrite\ReadModel\ThreadRepository;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\Pagination\PageNavigation;

final class TagPaginationTest
{
    public function environment(): array
    {
        $database = tempnam(sys_get_temp_dir(), 'tag-pages-');
        $app = new Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $database);
        $this->render($app, '/tags/');
        $pdo = new PDO('sqlite:' . $database);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $post = $pdo->prepare("INSERT INTO posts (post_id,created_at,thread_id,body,board_tags_json,sequence_number) VALUES (?, '2026-10-30', ?, 'Tag body', '[]', ?)");
        $thread = $pdo->prepare("INSERT INTO threads (root_post_id,root_post_created_at,last_activity_at,subject,body_preview,reply_count,last_post_id,board_tags_json,thread_labels_json) VALUES (?, '2026-10-30', ?, ?, 'Tag preview', 0, ?, ?, ?)");
        for ($i = 0; $i < 65; $i++) {
            $id = sprintf('paged-%03d', $i);
            $post->execute([$id, $id, $i]);
            $thread->execute([$id, '2026-10-' . sprintf('%02d', 1 + intdiv($i, 10)), $id, $id,
                $i % 2 ? '["PAGE","page"]' : '[]', '["Page","page"]']);
        }
        return [$app, $pdo, $database];
    }

    public function render(Application $app, string $url): string
    {
        ob_start();
        $app->handle('GET', $url);
        return (string) ob_get_clean();
    }

    public function testFirstPageMatchesLegacyMembershipAndRendersNativeNext(): void
    {
        [$app, $pdo, $database] = $this->environment();
        try {
            $pdo->exec("UPDATE threads SET board_tags_json = '[\"identity\",\"page\"]' WHERE root_post_id = 'paged-064'");
            $pdo->exec("UPDATE threads SET retracted_at = '2026-10-30' WHERE root_post_id = 'paged-063'");
            $pdo->exec("UPDATE threads SET board_tags_json = 'broken', thread_labels_json = '{\"x\":\"PAGE\",\"y\":12}' WHERE root_post_id = 'paged-062'");
            $expected = TagGrouping::find(TagGrouping::byTag(ThreadRepository::fetchThreads($pdo)), 'page')['threads'];
            $page = TagPage::page($pdo, 'PAGE');
            assertSame(array_column(array_slice($expected, 0, 25), 'root_post_id'), array_column($page['items'], 'root_post_id'));
            $html = $this->render($app, '/tags/PaGe');
            assertStringContains('rel="next"', $html);
            assertStringContains('paged-062', $html);
            assertStringNotContains('paged-064', $html);
            assertStringNotContains('paged-000', $html);
            $this->render($app, '/tags/no-such-tag');
            assertSame(404, http_response_code());
        } finally { @unlink($database); }
    }

    public function testVersion38StartupRebuildsBeforeTagQuery(): void
    {
        [$app, $pdo, $database] = $this->environment();
        try {
            $pdo->exec("UPDATE metadata SET value = '38' WHERE key = 'schema_version'");
            $fresh = new Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $database);
            $html = $this->render($fresh, '/tags/general');
            assertStringContains('Hello world', $html);
            assertSame(ReadModelMetadata::SCHEMA_VERSION, $pdo->query("SELECT value FROM metadata WHERE key='schema_version'")->fetchColumn());
            assertSame(0, (int) $pdo->query("SELECT COUNT(*) FROM threads WHERE root_post_id LIKE 'paged-%'")->fetchColumn());
        } finally { @unlink($database); }
    }
    public function testCompleteForwardBackwardTraversalAndDeletedAnchor(): void
    {
        [$app, $pdo, $database] = $this->environment();
        try {
            $expected = array_column(TagGrouping::find(TagGrouping::byTag(ThreadRepository::fetchThreads($pdo)), 'page')['threads'], 'root_post_id');
            $seen = []; $boundary = null; $pages = [];
            do {
                $page = TagPage::page($pdo, 'page', $boundary);
                $pages[] = $page;
                $seen = array_merge($seen, array_column($page['items'], 'root_post_id'));
                $boundary = $page['next'];
            } while ($boundary !== null);
            assertSame($expected, $seen);
            assertSame([25, 25, 15], array_map(static fn ($p) => count($p['items']), $pages));
            for ($i = 2; $i > 0; $i--) {
                $back = TagPage::page($pdo, 'page', $pages[$i]['previous'], 'previous');
                assertSame(array_column($pages[$i-1]['items'], 'root_post_id'), array_column($back['items'], 'root_post_id'));
            }
            $anchor = $pages[0]['next'];
            $pdo->prepare('DELETE FROM threads WHERE root_post_id = ?')->execute([$anchor['id']]);
            $after = TagPage::page($pdo, 'page', $anchor);
            assertSame(array_column($pages[1]['items'], 'root_post_id'), array_column($after['items'], 'root_post_id'));
            $url = '/tags/PAGE?position=' . PageNavigation::encode(TagPage::context('page'), 'next', $anchor);
            assertStringContains('paged-039', $this->render($app, $url));
            assertStringNotContains('paged-064', $this->render($app, $url));
        } finally { @unlink($database); }
    }

    public function testInvalidForeignAndExhaustedPositionsOfferRecovery(): void
    {
        [$app, $pdo, $database] = $this->environment();
        try {
            $urls = [
                '/tags/page?position=bad', '/tags/page?position[]=bad',
                '/tags/page?position=' . str_repeat('a', 8193),
                '/tags/page?position=' . PageNavigation::encode(TagPage::context('news'), 'next', ['activity'=>'2026-01-01', 'id'=>'x']),
                '/tags/page?position=' . PageNavigation::encode(TagPage::context('page'), 'next', ['wrong'=>'x']),
                '/tags/page?position=' . PageNavigation::encode(TagPage::context('page'), 'next', ['activity'=>'1000', 'id'=>'x']),
            ];
            foreach ($urls as $url) {
                $html = $this->render($app, $url);
                assertStringContains('href="/tags/page">First page</a>', $html);
                assertStringNotContains('paged-064', $html);
            }
            $first = TagPage::page($pdo, 'page');
            $pdo->exec("UPDATE threads SET last_activity_at='2027-01-01' WHERE root_post_id='paged-000'");
            $next = TagPage::page($pdo, 'page', $first['next']);
            assertTrue(!in_array('paged-000', array_column($next['items'], 'root_post_id'), true));
            assertSame('paged-000', TagPage::page($pdo, 'page')['items'][0]['root_post_id']);
        } finally { @unlink($database); }
    }

    public function testStaticFirstPageAndRuntimeContinuationAcrossProfiles(): void
    {
        $prior = getenv('FORUM_SITE_ID');
        try {
            foreach (['zenmemes', 'qdb'] as $profile) {
                putenv('FORUM_SITE_ID=' . $profile);
                [$app, $pdo, $database] = $this->environment();
                $root = sys_get_temp_dir() . '/tag-static-' . bin2hex(random_bytes(6));
                mkdir($root . '/public', 0700, true);
                try {
                    $publisher = new \ForumRewrite\Host\StaticArtifactReleasePublisher(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $root . '/static');
                    $release = $publisher->build($database);
                    $publisher->activate($release);
                    $artifact = (string) file_get_contents($release . '/tags/page.html');
                    assertStringContains('paged-064', $artifact);
                    assertStringNotContains('paged-000', $artifact);
                    assertTrue(preg_match('/rel="next" href="([^"]+)"/', $artifact, $match) === 1);
                    $url = html_entity_decode($match[1], ENT_QUOTES);
                    file_put_contents($release . '/tags/page.html', $artifact . '<!-- first-page-sentinel -->');
                    $front = new \ForumRewrite\Host\FrontController(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $database, $root . '/static', $root . '/public');
                    ob_start(); $front->handle('GET', '/tags/PaGe'); $first = (string) ob_get_clean();
                    assertStringContains('first-page-sentinel', $first);
                    ob_start(); $front->handle('GET', $url); $later = (string) ob_get_clean();
                    assertStringNotContains('first-page-sentinel', $later);
                    assertStringContains('paged-039', $later);
                    assertStringNotContains('paged-064', $later);
                    $refresh = $publisher->buildSharedRefresh($database);
                    assertSame((string) file_get_contents($release . '/tags/page.html'), (string) file_get_contents($refresh . '/tags/page.html'));
                } finally {
                    $this->removeTree($root);
                    @unlink($database);
                }
            }
        } finally { $prior === false ? putenv('FORUM_SITE_ID') : putenv('FORUM_SITE_ID=' . $prior); }
    }

    private function removeTree(string $root): void
    {
        if (is_link($root) || is_file($root)) { unlink($root); return; }
        foreach (scandir($root) ?: [] as $name) {
            if ($name !== '.' && $name !== '..') $this->removeTree($root . '/' . $name);
        }
        rmdir($root);
    }

}
