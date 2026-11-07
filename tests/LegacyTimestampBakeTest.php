<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Canonical\LegacyPostTimestamp;
use ForumRewrite\Retraction\HistoryPurgePlanner;
use ForumRewrite\Retraction\LegacyTimestampBake;

final class LegacyTimestampBakeTest
{
    public function testRunBakesTheDateRebuildShowsAndClearsThePurgeBlocker(): void
    {
        $root = sys_get_temp_dir() . '/legacy-bake-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0775, true);
        $git = static fn (array $args): string => LabelRemoval::git($root, $args);
        $git(['init', '-q', '-b', 'main']); $git(['config', 'user.name', 'Test']); $git(['config', 'user.email', 'test@example.invalid']);

        $legacy = "Post-ID: reply-001\nAuthor-Identity-ID: openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\nBoard-Tags: general\n\nold prose\n";
        file_put_contents($root . '/records/posts/reply-001.txt', $legacy);
        file_put_contents($root . '/records/posts/modern.txt', "Post-ID: modern\nCreated-At: 2026-02-01T00:00:00Z\nAuthor-Identity-ID: openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa\nBoard-Tags: general\n\nnew\n");
        file_put_contents($root . '/unrelated.txt', "a\n");
        $git(['add', '.']);
        $git(['commit', '-q', '-m', 'legacy', '--date=2026-04-11T03:33:12+00:00']);
        $expected = (new CanonicalRecordRepository($root, allowLegacyFilesystemTimestamp: false))->loadPost('records/posts/reply-001.txt')->createdAt;
        assertSame('2026-04-11T03:33:12Z', $expected);
        assertSame(['legacy_timestamps_missing', 'no_targets'], HistoryPurgePlanner::plan($root)['blockers']);

        $plan = LegacyTimestampBake::plan($root);
        assertSame([['path' => 'records/posts/reply-001.txt', 'created_at' => $expected]], $plan['baked']);
        assertSame([], $plan['undated']);
        assertSame(false, file_exists($root . '/records/post-timestamps'));

        // An unrelated working-tree edit must not be swept into the bake commit.
        file_put_contents($root . '/unrelated.txt', "edited\n");
        $result = LegacyTimestampBake::run($root);
        assertSame(1, count($result['baked']));
        assertSame(['records/post-timestamps/reply-001.json'], array_values(array_filter(explode("\n", trim($git(['show', '--name-only', '--format=', 'HEAD']))))));
        assertSame(LegacyTimestampBake::COMMIT_MESSAGE, trim($git(['log', '-1', '--format=%s'])));
        assertSame($expected, LegacyPostTimestamp::resolve($root, 'records/posts/reply-001.txt', $legacy));
        assertSame(' M unrelated.txt', rtrim($git(['status', '--porcelain'])));
        $git(['checkout', '--', 'unrelated.txt']);

        $after = HistoryPurgePlanner::plan($root);
        assertSame([], $after['legacy_without_timestamp']);
        assertSame(['no_targets'], $after['blockers']);
        // A repeat run finds nothing and makes no commit.
        $again = LegacyTimestampBake::run($root);
        assertSame([], $again['baked']);
        assertSame(null, $again['commit']);

        exec('rm -rf ' . escapeshellarg($root));
    }
}
