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
}
