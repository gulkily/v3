<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Retraction\HistoryRewrite;

final class HistoryRewriteTest
{
    public function testRewriteDropsTargetsFromAllHistoryAndMapsEveryCommit(): void
    {
        $root = $this->repository();
        $git = static fn (array $args): string => LabelRemoval::git($root, $args);
        $gone = 'records/posts/2026/01/01/quote-20260101000000-cccc0001.txt';
        $kept = 'records/posts/2026/01/02/quote-20260102000000-cccc0002.txt';
        $this->write($root, $gone, "Post-ID: cccc0001\n\nsecret prose\n");
        $this->write($root, $gone . '.asc', "signature one\n");
        $git(['add', '.']); $git(['commit', '-q', '-m', 'first post', '--date', '2026-01-01T00:00:00Z']);
        $this->write($root, $kept, "Post-ID: cccc0002\n\nsurviving prose\n");
        $this->write($root, $kept . '.asc', "signature two\n");
        $git(['add', '.']); $git(['commit', '-q', '-m', "second post\n\nwith a body", '--date', '2026-01-02T00:00:00Z']);
        $git(['branch', '-q', 'side']);
        $this->write($root, $gone, "Post-ID: cccc0001\n\nedited secret\n");
        $git(['add', '.']); $git(['commit', '-q', '-m', 'edit first', '--date', '2026-01-03T00:00:00Z']);
        unlink($root . '/' . $gone); unlink($root . '/' . $gone . '.asc');
        $this->write($root, 'records/post-removals/quote-20260101000000-cccc0001.txt', "Format: removal-acceptance-v1\nPost-ID: quote-20260101000000-cccc0001\n");
        $git(['add', '-A']); $git(['commit', '-q', '-m', 'delete first', '--date', '2026-01-04T00:00:00Z']);

        $oldCommits = array_filter(explode("\n", trim($git(['rev-list', '--all']))));
        $oldHeadTree = trim($git(['rev-parse', 'HEAD^{tree}']));
        $oldDates = $git(['log', '--all', '--format=%an %ad %cd %s', '--date=iso-strict']);
        $receiptBefore = file_get_contents($root . '/records/post-removals/quote-20260101000000-cccc0001.txt');
        $keptBlobBefore = trim($git(['rev-parse', 'HEAD:' . $kept]));
        $sideMessageBefore = $git(['log', '-1', '--format=%B', 'side']);

        $map = HistoryRewrite::rewrite($root, [$gone, $gone . '.asc']);

        // Every old commit is mapped to a distinct new commit, and the live refs point at mapped commits.
        assertSame(count($oldCommits), count($map));
        assertSame(count($map), count(array_unique($map)));
        foreach ($oldCommits as $old) { assertSame(true, isset($map[$old])); }
        assertSame($map[$oldCommits[0]], trim($git(['rev-parse', 'HEAD']))); // rev-list is newest first
        assertSame(true, in_array(trim($git(['rev-parse', 'side'])), $map, true));
        // The purged paths are absent from every ref; nothing else moved.
        assertSame('', trim($git(['log', '--all', '--format=%H', '--', $gone, $gone . '.asc'])));
        assertSame(0, HistoryRewrite::remainingCommits($root, [$gone, $gone . '.asc']));
        assertSame($oldHeadTree, trim($git(['rev-parse', 'HEAD^{tree}'])));
        assertSame($keptBlobBefore, trim($git(['rev-parse', 'HEAD:' . $kept])));
        assertSame($receiptBefore, file_get_contents($root . '/records/post-removals/quote-20260101000000-cccc0001.txt'));
        assertSame($oldDates, $git(['log', '--all', '--format=%an %ad %cd %s', '--date=iso-strict']));
        assertSame($sideMessageBefore, $git(['log', '-1', '--format=%B', 'side']));
        // The commit that only added the purged post survives as an empty commit, keeping the map one to one.
        assertSame(4, (int) trim($git(['rev-list', '--count', 'HEAD'])));
        assertSame('', trim($git(['show', '--format=', '--name-only', $map[$oldCommits[count($oldCommits) - 1]]])));
        assertSame(false, is_dir($root . '/.git/history-rewrite'));

        $this->cleanup($root);
    }

    public function testRewriteRefusesBadInput(): void
    {
        $root = $this->repository();
        try { HistoryRewrite::rewrite($root, []); throw new LogicException('Empty'); }
        catch (RuntimeException $e) { assertSame('Nothing to rewrite.', $e->getMessage()); }
        foreach (['../etc/passwd', 'records/posts/../x.txt', 'public/index.php', "records/a b.txt"] as $bad) {
            try { HistoryRewrite::rewrite($root, [$bad]); throw new LogicException('Accepted ' . $bad); }
            catch (RuntimeException $e) { assertSame(true, str_starts_with($e->getMessage(), 'Refusing to rewrite an unexpected path')); }
        }
        mkdir($root . '/.git/history-rewrite');
        try { HistoryRewrite::rewrite($root, ['records/posts/x.txt']); throw new LogicException('Concurrent'); }
        catch (RuntimeException $e) { assertSame(true, str_starts_with($e->getMessage(), 'A history rewrite is already in progress')); }
        rmdir($root . '/.git/history-rewrite');
        $this->cleanup($root);
    }

    private function repository(): string
    {
        $root = sys_get_temp_dir() . '/history-rewrite-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0775, true);
        $git = static fn (array $args): string => LabelRemoval::git($root, $args);
        $git(['init', '-q', '-b', 'main']); $git(['config', 'user.name', 'Test']); $git(['config', 'user.email', 'test@example.invalid']);
        return $root;
    }

    private function write(string $root, string $path, string $contents): void
    {
        if (!is_dir(dirname($root . '/' . $path))) mkdir(dirname($root . '/' . $path), 0775, true);
        file_put_contents($root . '/' . $path, $contents);
    }

    private function cleanup(string $path): void
    {
        if (!is_dir($path)) return;
        exec('rm -rf ' . escapeshellarg($path));
    }
}
