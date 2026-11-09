<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Http\ProfilePageController;

final class PersonIdentityOrderTest
{
    public function testIdentitiesSortByLatestVisiblePostWithQuietKeysLast(): void
    {
        $profiles = [
            ['profile_slug' => 'openpgp-quiet-a'],
            ['profile_slug' => 'openpgp-older'],
            ['profile_slug' => 'openpgp-quiet-b'],
            ['profile_slug' => 'openpgp-newer'],
        ];
        // Visible posts arrive newest first, as AuthoredContentRepository::visiblePosts returns them.
        $posts = [
            ['post_id' => 'p3', 'author_profile_slug' => 'openpgp-newer', 'created_at' => '2026-11-08T10:00:00Z'],
            ['post_id' => 'p2', 'author_profile_slug' => 'openpgp-older', 'created_at' => '2026-11-01T10:00:00Z'],
            ['post_id' => 'p1', 'author_profile_slug' => 'openpgp-newer', 'created_at' => '2026-10-01T10:00:00Z'],
        ];

        $method = new ReflectionMethod(ProfilePageController::class, 'identitiesByLatestPost');
        $rows = $method->invoke(null, $profiles, $posts);

        assertSame(
            ['openpgp-newer', 'openpgp-older', 'openpgp-quiet-a', 'openpgp-quiet-b'],
            array_column($rows, 'profile_slug')
        );
        assertSame('p3', $rows[0]['latest_post']['post_id']);
        assertSame('p2', $rows[1]['latest_post']['post_id']);
        assertSame(null, $rows[2]['latest_post']);
    }
}
