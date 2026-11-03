<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Http\ProfilePageController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\View\TemplateRenderer;

/**
 * /users/ sorts with the board's sort menu (partials/board_sort.php): Activity is
 * the directory's own order, ?sort=name is alphabetical, anything else is Activity.
 */
final class UsersDirectorySortTest
{
    public function testSortMenuAndOrders(): void
    {
        $databasePath = sys_get_temp_dir() . '/forum-rewrite-users-sort-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            $pdo = new \PDO('sqlite:' . $databasePath);
            foreach (ReadModelSchema::statements() as $statement) {
                $pdo->exec($statement);
            }
            $this->addProfile($pdo, 'openpgp:zed', 'openpgp-zed', 'zed', 1, 5, 9);
            $this->addProfile($pdo, 'openpgp:amy', 'openpgp-amy', 'amy', 1, 1, 2);
            $this->addProfile($pdo, 'openpgp:mel', 'openpgp-mel', 'mel', 1, 3, 1);
            $this->addProfile($pdo, 'openpgp:pending', 'openpgp-pending', 'pending', 0, 9, 9);
            // amy posted last; zed's latest post is hidden, so her visible activity is older; mel never posted.
            $this->addPost($pdo, 'p-zed-1', 'openpgp:zed', '2024-03-03T10:00:00+00:00', 0);
            $this->addPost($pdo, 'p-zed-2', 'openpgp:zed', '2026-10-30T10:00:00+00:00', 1);
            $this->addPost($pdo, 'p-amy-1', 'openpgp:amy', '2026-10-20T10:00:00+00:00', 0);
            $this->addPost($pdo, 'p-pending', 'openpgp:pending', '2026-10-31T10:00:00+00:00', 0);

            $activity = $this->render($databasePath, []);
            assertSame(['zed', 'mel', 'amy'], $this->rowOrder($activity));
            assertStringContains('<details class="board-sort" data-role="board-sort">', $activity);
            assertStringContains('<summary class="nav-link"><span class="visually-hidden">Sort by </span>Activity</summary>', $activity);
            assertStringContains('<a class="nav-link is-active" href="/users/?sort=activity" data-nav-key="activity" aria-current="true">Activity</a>', $activity);
            assertStringContains('<a class="nav-link" href="/users/?sort=active" data-nav-key="active">Last active</a>', $activity);
            assertStringContains('<a class="nav-link" href="/users/?sort=name" data-nav-key="name">Name</a>', $activity);
            // Each row's meta line ends with when the member last posted (a fixed short date, as thread rows do); none for a member without posts.
            assertStringContains('data-user-name="zed" data-user-active="2024-03-03T10:00:00+00:00">', $activity);
            assertStringContains('5 threads · 9 posts · Active <time datetime="2024-03-03T10:00:00+00:00" title="', $activity);
            assertStringContains('>Mar 3, 2024</time></p>', $activity);
            assertStringContains('data-user-name="amy" data-user-active="2026-10-20T10:00:00+00:00">', $activity);
            assertStringContains('1 thread · 2 posts · Active <time datetime="2026-10-20T10:00:00+00:00"', $activity);
            assertStringContains('data-user-name="mel" data-user-active="">', $activity);
            assertStringContains('3 threads · 1 post</p>', $activity);
            assertStringNotContains('<select', $activity);
            // The tabs, the sort menu and the search box share the board toolbar, in that order.
            assertStringContains('<div class="board-toolbar">', $activity);
            assertStringContains('<nav class="board-filter" aria-label="Users" data-member-only>', $activity);
            assertTrue(strpos($activity, 'data-role="board-sort"') < strpos($activity, 'class="board-toolbar__search"'));

            $byName = $this->render($databasePath, ['sort' => 'name']);
            assertSame(['amy', 'mel', 'zed'], $this->rowOrder($byName));
            assertStringContains('<span class="visually-hidden">Sort by </span>Name</summary>', $byName);
            assertStringContains('<a class="nav-link is-active" href="/users/?sort=name" data-nav-key="name" aria-current="true">Name</a>', $byName);

            $byActive = $this->render($databasePath, ['sort' => 'active']);
            assertSame(['amy', 'zed', 'mel'], $this->rowOrder($byActive));
            assertStringContains('<span class="visually-hidden">Sort by </span>Last active</summary>', $byActive);
            assertStringContains('<a class="nav-link is-active" href="/users/?sort=active" data-nav-key="active" aria-current="true">Last active</a>', $byActive);

            $unknown = $this->render($databasePath, ['sort' => 'bogus']);
            assertSame(['zed', 'mel', 'amy'], $this->rowOrder($unknown));
            assertStringContains('<span class="visually-hidden">Sort by </span>Activity</summary>', $unknown);
        } finally {
            @unlink($databasePath);
            @unlink($databasePath . '-journal');
        }
    }

    /** @return list<string> */
    private function rowOrder(string $html): array
    {
        preg_match_all('/data-user-name="([^"]+)"/', $html, $matches);
        return $matches[1];
    }

    /** @param array<string, mixed> $query */
    private function render(string $databasePath, array $query): string
    {
        $services = new RouteServices(
            $databasePath,
            new TemplateRenderer(__DIR__ . '/../templates', 'test'),
            'test',
            false,
            static fn (): ?array => null,
            __DIR__ . '/fixtures/parity_minimal_v1',
            dirname(__DIR__),
            null,
            null,
            FeatureFlagEvaluator::forApplication(__DIR__ . '/fixtures/parity_minimal_v1', dirname(__DIR__)),
            static fn (): ?array => null,
        );
        $controller = new ProfilePageController(
            $services,
            static fn (): ?array => null,
            static fn (array $profile, bool $self, ?string $notice, ?string $error): string => '',
        );

        return $controller->directory($query);
    }

    private function addPost(\PDO $pdo, string $postId, string $identityId, string $createdAt, int $hidden): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO posts (post_id, created_at, thread_id, parent_id, body, board_tags_json, author_identity_id, sequence_number, is_hidden)
             VALUES (:post_id, :created_at, :thread_id, NULL, :body, :tags, :identity_id, 1, :hidden)'
        );
        $stmt->execute(['post_id' => $postId, 'created_at' => $createdAt, 'thread_id' => $postId, 'body' => 'hello', 'tags' => '["general"]', 'identity_id' => $identityId, 'hidden' => $hidden]);
    }

    private function addProfile(\PDO $pdo, string $identityId, string $profileSlug, string $username, int $approved, int $threads, int $posts): void
    {
        $stmt = $pdo->prepare(
            'INSERT INTO profiles (
                identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint,
                bootstrap_post_id, bootstrap_thread_id, public_key, is_approved, thread_count, post_count
             ) VALUES (
                :identity_id, :profile_slug, :username, :username_token, :fallback_label, :signer_fingerprint,
                :bootstrap_post_id, :bootstrap_thread_id, :public_key, :is_approved, :thread_count, :post_count
             )'
        );
        $stmt->execute([
            'identity_id' => $identityId,
            'profile_slug' => $profileSlug,
            'username' => $username,
            'username_token' => $username,
            'fallback_label' => $username,
            'signer_fingerprint' => str_repeat('A', 40),
            'bootstrap_post_id' => 'identity-' . $profileSlug,
            'bootstrap_thread_id' => 'thread-' . $profileSlug,
            'public_key' => 'PUBLIC KEY ' . $profileSlug,
            'is_approved' => $approved,
            'thread_count' => $threads,
            'post_count' => $posts,
        ]);
    }
}
