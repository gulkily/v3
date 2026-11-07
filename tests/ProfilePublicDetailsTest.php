<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Application;
use ForumRewrite\ReadModel\AuthoredContentRepository;
use ForumRewrite\ReadModel\ReadModelMetadata;

final class ProfilePublicDetailsTest
{
    private function environment(): array
    {
        $database = sys_get_temp_dir() . '/profile-public-' . bin2hex(random_bytes(6)) . '.sqlite3';
        $app = new Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $database);
        $this->render($app, '/api/read_model_status');
        $pdo = new PDO('sqlite:' . $database);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        return [$app, $pdo, $database];
    }

    private function render(Application $app, string $url): string
    {
        ob_start();
        try { $app->handle('GET', $url); return (string) ob_get_contents(); }
        finally { ob_end_clean(); }
    }

    private function insert(PDO $pdo, string $table, array $row): void
    {
        $pdo->prepare('INSERT INTO ' . $table . ' (' . implode(',', array_keys($row)) . ') VALUES (' . implode(',', array_fill(0, count($row), '?')) . ')')->execute(array_values($row));
    }

    private function contribution(PDO $pdo, string $id, string $identity, array $overrides = []): void
    {
        $post = $pdo->query('SELECT * FROM posts LIMIT 1')->fetch();
        $post = array_replace($post, ['post_id' => $id, 'thread_id' => $id, 'parent_id' => null,
            'author_identity_id' => $identity, 'is_hidden' => 0, 'retracted_at' => null,
            'board_tags_json' => '["general"]', 'agent_response_json' => null], $overrides);
        $this->insert($pdo, 'posts', $post);
        if ($post['post_id'] === $post['thread_id']) {
            $thread = $pdo->query('SELECT * FROM threads LIMIT 1')->fetch();
            $this->insert($pdo, 'threads', array_replace($thread, ['root_post_id' => $id, 'last_post_id' => $id,
                'board_tags_json' => $post['board_tags_json'], 'retracted_at' => $post['retracted_at']]));
        }
    }

    public function testIdentityCountsExcludeHiddenContentAndNeverAggregateNames(): void
    {
        [$app, $pdo, $database] = $this->environment();
        try {
            $identity = 'openpgp:public-test';
            $profile = $pdo->query('SELECT * FROM profiles LIMIT 1')->fetch();
            foreach ([$identity, 'openpgp:other-test'] as $id) {
                $this->insert($pdo, 'profiles', array_replace($profile, ['identity_id' => $id,
                    'profile_slug' => str_replace(':', '-', $id), 'is_approved' => 0]));
            }
            $this->contribution($pdo, 'visible-root', $identity);
            $this->contribution($pdo, 'visible-reply', $identity, ['thread_id' => 'visible-root', 'parent_id' => 'visible-root']);
            $this->contribution($pdo, 'same-name-other-key', 'openpgp:other-test');
            $this->contribution($pdo, 'hidden-root', $identity, ['is_hidden' => 1]);
            $this->contribution($pdo, 'retracted-root', $identity, ['retracted_at' => '2026-10-27']);
            $this->contribution($pdo, 'bootstrap-root', $identity, ['board_tags_json' => '["identity","internal"]']);
            $this->contribution($pdo, 'generated-root', $identity, ['agent_response_json' => '{"type":"scripture_expansion"}']);
            assertSame(1, AuthoredContentRepository::countVisibleIdentity($pdo, $identity, 'threads'));
            assertSame(2, AuthoredContentRepository::countVisibleIdentity($pdo, $identity, 'posts'));
            $pdo->exec("UPDATE profiles SET is_approved=1 WHERE identity_id='openpgp:public-test'");
            assertSame(2, AuthoredContentRepository::countVisibleIdentity($pdo, $identity, 'posts'));
            assertSame(0, AuthoredContentRepository::countVisibleIdentity($pdo, 'unknown', 'posts'));
        } finally { @unlink($database); }
    }

    public function testVersion30StartupRebuildsBeforeProfileRead(): void
    {
        [$app, $pdo, $database] = $this->environment();
        try {
            $profile = $pdo->query('SELECT * FROM profiles LIMIT 1')->fetch();
            $pdo->exec("UPDATE metadata SET value='30' WHERE key='schema_version'");
            $pdo->exec("UPDATE profiles SET public_key='stale-key', post_count=999");
            $app = new Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $database);
            $html = $this->render($app, '/profiles/' . $profile['profile_slug']);
            assertSame(200, http_response_code());
            assertStringNotContains('stale-key', $html);
            assertSame(ReadModelMetadata::SCHEMA_VERSION, $pdo->query("SELECT value FROM metadata WHERE key='schema_version'")->fetchColumn());
            assertSame($profile['public_key'], $pdo->query('SELECT public_key FROM profiles LIMIT 1')->fetchColumn());
            assertTrue(AuthoredContentRepository::countVisibleIdentity($pdo, $profile['identity_id'], 'posts') >= 0);
        } finally { @unlink($database); }
    }

    public function testPublicKeyIsEscapedCompleteAndCollapsedWithUnavailableRecovery(): void
    {
        [$app, $pdo, $database] = $this->environment();
        try {
            $profile = $pdo->query('SELECT * FROM profiles LIMIT 1')->fetch();
            $url = '/profiles/' . $profile['profile_slug'];
            $html = $this->render($app, $url);
            assertStringContains('<details class="account-key-advanced" data-public-key-details>', $html);
            assertStringContains(htmlspecialchars($profile['public_key'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'), $html);
            assertFingerprintedAsset($html, 'public_key.js');
            $pdo->exec("UPDATE profiles SET public_key='<script>not markup</script>'");
            assertStringContains('&lt;script&gt;not markup&lt;/script&gt;', $this->render($app, $url));
            $pdo->exec("UPDATE profiles SET public_key=''");
            $html = $this->render($app, $url);
            assertStringContains('Public key unavailable.', $html);
            assertStringNotContains('data-copy-public-key', $html);
        } finally { @unlink($database); }
    }
    public function testIdentityPagesAreNumberedAndFindTheirPosts(): void
    {
        [$app, $pdo, $database] = $this->environment();
        try {
            for ($i = 0; $i < 105; $i++) $this->contribution($pdo, sprintf('page-%03d', $i), 'openpgp:paging');
            $ids = []; $number = 1;
            do {
                $page = AuthoredContentRepository::visibleIdentityPage($pdo, 'openpgp:paging', 'posts', $number);
                foreach ($page['items'] as $item) assertSame($number, AuthoredContentRepository::visibleIdentityPageOf($pdo, 'openpgp:paging', 'posts', $item['post_id']));
                $ids = array_merge($ids, array_column($page['items'], 'post_id')); $number++;
            } while ($page['has_next']);
            assertSame(105, count(array_unique($ids)));
            assertSame(3, $number - 1);
            assertSame(105, AuthoredContentRepository::countVisibleIdentity($pdo, 'openpgp:paging', 'posts'));
            assertSame([], AuthoredContentRepository::visibleIdentityPage($pdo, 'openpgp:paging', 'posts', 9)['items']);
            assertSame(null, AuthoredContentRepository::visibleIdentityPageOf($pdo, 'openpgp:another', 'posts', 'page-000'));
            assertSame(100, count(AuthoredContentRepository::visibleIdentityPage($pdo, 'openpgp:paging', 'posts', 1, 9999)['items']));
        } finally { @unlink($database); }
    }

    public function testNormalProfileHistoryIsUnapprovedIdentityScopedWithIndependentRecovery(): void
    {
        [$app, $pdo, $database] = $this->environment();
        try {
            $profile = $pdo->query('SELECT * FROM profiles LIMIT 1')->fetch();
            $pdo->exec('UPDATE profiles SET is_approved=0');
            $pdo->exec('UPDATE posts SET author_identity_id=NULL');
            for ($i = 0; $i < 55; $i++) $this->contribution($pdo, sprintf('own-%03d', $i), $profile['identity_id']);
            $this->contribution($pdo, 'excluded-other-author', 'openpgp:another');
            $this->contribution($pdo, 'excluded-hidden', $profile['identity_id'], ['is_hidden' => 1]);
            $url = '/profiles/' . $profile['profile_slug'];
            $first = $this->render($app, $url);
            assertStringContains('<strong>Posts:</strong> 55', $first);
            assertStringContains('<strong>Threads:</strong> 55', $first);
            assertStringNotContains('excluded-other-author', $first);
            assertStringNotContains('excluded-hidden', $first);
            preg_match_all('/rel="next" href="([^"]+)"/', $first, $matches);
            assertSame(2, count($matches[1]));
            $secondUrl = html_entity_decode($matches[1][0], ENT_QUOTES);
            $second = $this->render($app, $secondUrl);
            assertStringContains('/threads/own-004', $second);
            assertStringNotContains('/threads/own-054', $second);
            assertStringContains('/posts/own-054', $second);
            assertSame($url . '?threads_page=2#threads', $secondUrl);
            preg_match('/rel="next" href="([^"]*posts_page[^"]+)"/', $second, $next);
            assertSame($url . '?threads_page=2&posts_page=2#posts', html_entity_decode($next[1], ENT_QUOTES));
            $invalid = $this->render($app, $url . '?threads_page[]=bad');
            assertStringContains('/threads/own-054', $invalid);
            assertStringContains('/posts/own-054', $invalid);
            assertStringNotContains('role="alert"', $invalid);
            assertStringContains('id="threads-post-own-054"', $first);
            $toThread = $this->render($app, $url . '?with=own-004');
            assertStringContains($url . '?threads_page=2#threads-post-own-004', html_entity_decode($toThread, ENT_QUOTES));
            assertStringContains('id="threads-post-own-054"', $this->render($app, $url . '?with=excluded-other-author'));
            $pdo->exec("DELETE FROM posts WHERE post_id <= 'own-004'");
            $empty = $this->render($app, $secondUrl);
            assertStringContains('No visible threads.', $empty);
            assertStringContains('First page', $empty);
            assertStringContains('>Previous</a>', $empty);
        } finally { @unlink($database); }
    }

    public function testStaticProfileAndLivePagesNeverSubstituteFirstPage(): void
    {
        $workspace = new ImportTestWorkspace();
        $profiles = getenv('FORUM_STATIC_PROFILE_PAGES_ENABLED');
        putenv('FORUM_STATIC_PROFILE_PAGES_ENABLED=true');
        $flag = getenv('FORUM_APPROVED_MEMBERS_ONLY');
        putenv('FORUM_APPROVED_MEMBERS_ONLY=false');
        [$app, $pdo, $database] = $this->environment();
        try {
            $profile = $pdo->query('SELECT * FROM profiles LIMIT 1')->fetch();
            $pdo->exec('UPDATE profiles SET is_approved=0');
            for ($i = 0; $i < 55; $i++) $this->contribution($pdo, sprintf('host-%03d', $i), $profile['identity_id']);
            $public = $workspace->root . '/public';
            $root = __DIR__ . '/fixtures/parity_minimal_v1';
            (new \ForumRewrite\Host\StaticArtifactBuilder(dirname(__DIR__), $root, $database, $public))->buildFromReadModel();
            $artifact = $public . '/profiles/' . $profile['profile_slug'] . '.html';
            file_put_contents($artifact, "<!-- FIRST PROFILE ARTIFACT -->", FILE_APPEND);
            mkdir($workspace->root . '/static');
            symlink($public, $workspace->root . '/static/current');
            $host = new \ForumRewrite\Host\FrontController(dirname(__DIR__), $root, $database, $workspace->root . '/static', $public);
            $render = static function (string $url) use ($host): string {
                ob_start(); try { $host->handle('GET', $url, []); return (string) ob_get_contents(); } finally { ob_end_clean(); }
            };
            $url = '/profiles/' . $profile['profile_slug'];
            $first = $render($url);
            assertStringContains('FIRST PROFILE ARTIFACT', $first);
            assertStringContains('<summary>Public key</summary>', $first);
            preg_match('/rel="next" href="([^"]+)"/', $first, $match);
            $second = $render(html_entity_decode($match[1], ENT_QUOTES));
            assertStringNotContains('FIRST PROFILE ARTIFACT', $second);
            assertStringContains('/threads/host-004', $second);
            assertStringNotContains('/threads/host-054', $second);
            $past = $render($url . '?posts_page=9');
            assertStringNotContains('FIRST PROFILE ARTIFACT', $past);
            assertStringContains('First page', $past);
            assertSame(null, \ForumRewrite\Host\HighTrafficRoute::canonical($url));
        } finally {
            putenv($profiles === false ? 'FORUM_STATIC_PROFILE_PAGES_ENABLED' : 'FORUM_STATIC_PROFILE_PAGES_ENABLED=' . $profiles);
            @unlink($database); unset($workspace); putenv($flag === false ? 'FORUM_APPROVED_MEMBERS_ONLY' : 'FORUM_APPROVED_MEMBERS_ONLY=' . $flag); }
    }

}
