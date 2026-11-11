<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Canonical\RemovalReceipt;
use ForumRewrite\Retraction\HistoryPurgePlanner;

final class HistoryPurgePlannerTest
{
    private const AUTHOR = 'openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testPlanNamesHistoricalTargetsAndBlockers(): void
    {
        $root = $this->repository();
        $git = static fn (array $args): string => LabelRemoval::git($root, $args);

        $this->post($root, 'quote-20260101000000-aaaa0001', '2026-01-01T00:00:00Z', 'first');
        $this->post($root, 'quote-20260102000000-aaaa0002', '2026-01-02T00:00:00Z', 'second');
        file_put_contents($root . '/records/posts/legacy.txt', "Post-ID: legacy\nAuthor-Identity-ID: " . self::AUTHOR . "\nBoard-Tags: general\n\nold prose\n");
        // The first post arrived with a skewed clock, so it carries a receipt.
        file_put_contents($root . '/records/posts/2026/01/01/quote-20260101000000-aaaa0001.txt.received', \ForumRewrite\Canonical\RecordReceipt::contents(strtotime('2026-01-01T00:20:00Z')));
        $git(['add', '.']); $git(['commit', '-q', '-m', 'posts']);

        $plan = HistoryPurgePlanner::plan($root);
        assertSame(0, $plan['receipts']);
        assertSame(['legacy_timestamps_missing', 'no_targets'], $plan['blockers']);
        assertSame(['records/posts/legacy.txt'], $plan['legacy_without_timestamp']);
        assertSame(false, $plan['can_run']);

        // Delete the first post the way delete-record does: remove files, accept a receipt, commit together.
        $deleted = 'records/posts/2026/01/01/quote-20260101000000-aaaa0001.txt';
        $source = file_get_contents($root . '/' . $deleted);
        unlink($root . '/' . $deleted); unlink($root . '/' . $deleted . '.asc'); unlink($root . '/' . $deleted . '.received');
        mkdir($root . '/records/post-removals', 0775, true);
        file_put_contents($root . '/records/post-removals/quote-20260101000000-aaaa0001.txt', $this->receipt('quote-20260101000000-aaaa0001', '2026-01-01T00:00:00Z', $source));
        $legacy = file_get_contents($root . '/records/posts/legacy.txt');
        mkdir($root . '/records/post-timestamps', 0775, true);
        file_put_contents($root . '/records/post-timestamps/legacy.json', \ForumRewrite\Canonical\LegacyPostTimestamp::encode('legacy', $legacy, '2025-12-31T00:00:00Z'));
        $git(['add', '-A']); $git(['commit', '-q', '-m', 'delete first']);

        // A second receipt for a post that never had a file here is counted but purges nothing.
        file_put_contents($root . '/records/post-removals/quote-20260103000000-aaaa0009.txt', $this->receipt('quote-20260103000000-aaaa0009', '2026-01-03T00:00:00Z', 'never committed'));
        $git(['add', '-A']); $git(['commit', '-q', '-m', 'receipt without history']);

        $plan = HistoryPurgePlanner::plan($root);
        assertSame(2, $plan['receipts']);
        assertSame(['quote-20260103000000-aaaa0009'], $plan['receipts_without_history']);
        // Only the dated candidate ever existed; the undated candidate is not reported.
        assertSame([$deleted, $deleted . '.asc', $deleted . '.received'], $plan['targets']);
        assertSame(3, $plan['commits_total']);
        assertSame(2, $plan['commits_affected']);
        assertSame([], $plan['legacy_without_timestamp']);
        assertSame([], $plan['blockers']);
        assertSame(true, $plan['can_run']);

        $second = 'records/posts/2026/01/02/quote-20260102000000-aaaa0002.txt';
        file_put_contents($root . '/' . $second, file_get_contents($root . '/' . $second) . "edit\n");
        assertSame(['dirty_tree'], HistoryPurgePlanner::plan($root)['blockers']);
        $git(['checkout', '--', $second]);
        mkdir($root . '/.git/instance-import', 0775, true);
        file_put_contents($root . '/.git/instance-import/pending.json', '{}');
        assertSame(['interrupted_import'], HistoryPurgePlanner::plan($root)['blockers']);
        unlink($root . '/.git/instance-import/pending.json');

        $this->cleanup($root);
    }

    public function testDryRunRewritesACloneAndLeavesTheSourceIntact(): void
    {
        $root = $this->repository();
        $git = static fn (array $args): string => LabelRemoval::git($root, $args);
        $this->post($root, 'quote-20260101000000-bbbb0001', '2026-01-01T00:00:00Z', 'gone');
        $this->post($root, 'quote-20260102000000-bbbb0002', '2026-01-02T00:00:00Z', 'kept');
        $git(['add', '.']); $git(['commit', '-q', '-m', 'posts']);
        $target = 'records/posts/2026/01/01/quote-20260101000000-bbbb0001.txt';
        $source = file_get_contents($root . '/' . $target);
        unlink($root . '/' . $target); unlink($root . '/' . $target . '.asc');
        mkdir($root . '/records/post-removals', 0775, true);
        file_put_contents($root . '/records/post-removals/quote-20260101000000-bbbb0001.txt', $this->receipt('quote-20260101000000-bbbb0001', '2026-01-01T00:00:00Z', $source));
        $git(['add', '-A']); $git(['commit', '-q', '-m', 'delete']);

        $plan = HistoryPurgePlanner::plan($root);
        assertSame(true, $plan['can_run']);
        $work = $root . '-dryrun';
        $timing = HistoryPurgePlanner::timeDryRun($root, $plan['targets'], $work);
        assertSame(true, $timing['rewrite_seconds'] > 0.0);
        assertSame(2, $timing['commits_total']);
        assertSame(2, $timing['rewritten_commits']);
        assertSame(0, $timing['remaining_commits']);
        // The clone's HEAD tree is unchanged (the target was already deleted there) and nothing is left behind.
        assertSame(trim($git(['rev-parse', 'HEAD^{tree}'])), trim(LabelRemoval::git($work, ['rev-parse', 'HEAD^{tree}'])));
        assertSame(false, is_dir($work . '/.git/history-rewrite'));
        // The clone no longer carries the prose on its live refs; the source still does.
        assertSame('', trim(LabelRemoval::git($work, ['log', 'HEAD', '--format=%H', '--', $target])));
        assertSame(2, count(array_filter(explode("\n", trim($git(['log', '--all', '--format=%H', '--', $target]))))));
        assertSame('kept', trim(explode("\n\n", file_get_contents($work . '/records/posts/2026/01/02/quote-20260102000000-bbbb0002.txt'), 2)[1]));

        try { HistoryPurgePlanner::timeDryRun($root, $plan['targets'], $work); throw new LogicException('Reused directory'); }
        catch (RuntimeException $e) { assertSame(true, str_starts_with($e->getMessage(), 'Dry-run directory already exists')); }
        try { HistoryPurgePlanner::timeDryRun($root, [], $work . '-2'); throw new LogicException('Empty targets'); }
        catch (RuntimeException $e) { assertSame('Nothing to purge.', $e->getMessage()); }

        $this->cleanup($work);
        $this->cleanup($root);
    }

    public function testMissingGitIsTheOnlyBlockerReported(): void
    {
        $root = sys_get_temp_dir() . '/history-purge-nogit-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0775, true);
        $plan = HistoryPurgePlanner::plan($root);
        assertSame(['missing_git'], $plan['blockers']);
        assertSame(false, $plan['can_run']);
        $this->cleanup($root);
    }

    private function repository(): string
    {
        $root = sys_get_temp_dir() . '/history-purge-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0775, true);
        $git = static fn (array $args): string => LabelRemoval::git($root, $args);
        $git(['init', '-q', '-b', 'main']); $git(['config', 'user.name', 'Test']); $git(['config', 'user.email', 'test@example.invalid']);
        return $root;
    }

    private function post(string $root, string $id, string $createdAt, string $body): void
    {
        $path = \ForumRewrite\Canonical\CanonicalPathResolver::datedPost($id, $createdAt);
        if (!is_dir(dirname($root . '/' . $path))) mkdir(dirname($root . '/' . $path), 0775, true);
        file_put_contents($root . '/' . $path, "Post-ID: $id\nCreated-At: $createdAt\nAuthor-Identity-ID: " . self::AUTHOR . "\nBoard-Tags: general\nSubject: $body\n\n$body\n");
        file_put_contents($root . '/' . $path . '.asc', "signature of $id\n");
    }

    private function receipt(string $id, string $createdAt, string $source): string
    {
        return RemovalReceipt::encode(['Format' => 'removal-acceptance-v1', 'Post-ID' => $id, 'Operation' => 'delete',
            'Authority' => 'local-operator', 'Accepted-At' => '2026-10-30T00:00:00Z', 'Actor' => 'local-operator',
            'Source-SHA256' => hash('sha256', $source), 'Thread-ID' => $id, 'Parent-ID' => '', 'Post-Created-At' => $createdAt,
            'Author-Identity-ID' => self::AUTHOR, 'Thread-Type' => '', 'Retraction-Class' => '', 'Retraction-Attribution' => '',
            'Request-SHA256' => '', 'Signature-SHA256' => '']);
    }

    private function cleanup(string $path): void
    {
        if (!is_dir($path)) return;
        exec('rm -rf ' . escapeshellarg($path));
    }
}
