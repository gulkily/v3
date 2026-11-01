<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Canonical\RemovalReceipt;
use ForumRewrite\Retraction\HistoryPurge;
use ForumRewrite\Retraction\HistoryPurgePlanner;

final class HistoryPurgeTest
{
    private const AUTHOR = 'openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
    private const GONE = 'records/posts/2026/01/01/quote-20260101000000-dddd0001.txt';
    private const KEPT = 'records/posts/2026/01/02/quote-20260102000000-dddd0002.txt';
    private const RECEIPT = 'records/post-removals/quote-20260101000000-dddd0001.txt';

    public function testExecutePurgesVerifiesAndLeavesNoOldObject(): void
    {
        [$root, $git] = $this->fixture();
        $before = $this->snapshot($root);
        $plan = HistoryPurgePlanner::plan($root);
        assertSame(true, $plan['can_run']);

        $result = HistoryPurge::execute($root, $plan);

        assertSame(true, $result['verified']);
        assertSame([], $result['lingering']);
        assertSame(false, $result['restored_interrupted_run']);
        assertSame(2, $result['targets']);
        assertSame(4, $result['commits_total']);
        assertSame(4, $result['commits_changed']); // the first commit added the purged post, so every commit moved
        assertSame(3, $result['blobs_removed']); // two post versions and one signature
        assertSame(['snapshot', 'rewrite', 'verify_refs', 'prune', 'verify_objects'], array_keys($result['seconds']));
        assertSame(false, is_dir($root . '/.git/history-purge'));
        assertSame(null, HistoryPurge::interrupted($root));
        // Nothing on any ref touches the purged paths, and the old objects are gone from the object database entirely.
        assertSame('', trim($git(['log', '--all', '--format=%H', '--', self::GONE, self::GONE . '.asc'])));
        assertSame([], HistoryPurge::lingering($root, array_merge($before['blobs'], $before['commits'])));
        foreach ($before['blobs'] as $blob) {
            assertSame(false, $this->objectExists($root, $blob));
        }
        assertSame('', trim($git(['fsck', '--unreachable', '--no-reflogs'])));
        // Surviving records, dates, messages, refs and the working tree are intact; the live refs point at mapped commits.
        assertSame($before['receipt'], file_get_contents($root . '/' . self::RECEIPT));
        assertSame($before['kept'], file_get_contents($root . '/' . self::KEPT));
        assertSame($before['kept_blob'], trim($git(['rev-parse', 'HEAD:' . self::KEPT])));
        assertSame($before['log'], $git(['log', '--all', '--format=%an %ad %cd %s', '--date=iso-strict']));
        assertSame($before['head_tree'], trim($git(['rev-parse', 'HEAD^{tree}'])));
        assertSame('refs/heads/main', trim($git(['symbolic-ref', 'HEAD'])));
        assertSame('', trim($git(['status', '--porcelain'])));
        foreach ($before['refs'] as $ref => $old) {
            assertSame($result['map'][$old], trim($git(['rev-parse', $ref])));
        }
        assertSame(false, is_file($root . '/.git/ORIG_HEAD'));
        // A second plan finds nothing left to purge.
        assertSame(['no_targets'], HistoryPurgePlanner::plan($root)['blockers']);
        $this->cleanup($root);
    }

    public function testFailureAfterRewriteRestoresTheOriginalRefs(): void
    {
        [$root, $git] = $this->fixture();
        $before = $this->snapshot($root);
        $plan = HistoryPurgePlanner::plan($root);
        try {
            HistoryPurge::execute($root, $plan, static function (): void { throw new RuntimeException('injected'); });
            throw new LogicException('Did not fail');
        } catch (RuntimeException $e) {
            assertSame(true, str_starts_with($e->getMessage(), 'Purge aborted before any history was discarded; original refs restored. injected'));
        }
        assertSame($before['refs'], $this->refs($root));
        assertSame('refs/heads/main', trim($git(['symbolic-ref', 'HEAD'])));
        assertSame(false, is_dir($root . '/.git/history-purge'));
        assertSame(false, is_dir($root . '/.git/history-rewrite'));
        assertSame('', trim($git(['status', '--porcelain'])));
        // The prose is still in history: nothing was discarded.
        assertSame(3, count(array_filter(explode("\n", trim($git(['log', '--all', '--format=%H', '--', self::GONE]))))));
        foreach ($before['blobs'] as $blob) {
            assertSame(true, $this->objectExists($root, $blob));
        }
        // The repository is runnable again and the purge then succeeds.
        assertSame(true, HistoryPurgePlanner::plan($root)['can_run']);
        assertSame(true, HistoryPurge::execute($root, HistoryPurgePlanner::plan($root))['verified']);
        $this->cleanup($root);
    }

    public function testLeakedObjectFailsVerificationAndIsReportedUntilRestoredFromBackup(): void
    {
        [$root, $git] = $this->fixture();
        $before = $this->snapshot($root);
        $secret = $before['blobs'][0];
        assertSame([$secret], HistoryPurge::lingering($root, [$secret, str_repeat('f', 40)]));
        // Keep one purged blob alive in a kept pack, which gc never deletes.
        $leak = static function (string $root) use ($secret): void {
            $pipes = [];
            $process = proc_open(['git', '-C', $root, 'pack-objects', '-q', $root . '/.git/objects/pack/pack-leak'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            fwrite($pipes[0], $secret . "\n"); fclose($pipes[0]);
            $packId = trim(stream_get_contents($pipes[1])); fclose($pipes[1]); fclose($pipes[2]); proc_close($process);
            touch($root . '/.git/objects/pack/pack-leak-' . $packId . '.keep');
        };
        $result = HistoryPurge::execute($root, HistoryPurgePlanner::plan($root), $leak);
        assertSame(false, $result['verified']);
        assertSame([$secret], $result['lingering']);
        assertSame(true, $this->objectExists($root, $secret));
        // The journal stays so the next plan says the purge did not complete, and restore refuses: old history is gone.
        assertSame('lingering', HistoryPurge::interrupted($root)['phase']);
        assertSame(true, in_array('interrupted_purge', HistoryPurgePlanner::plan($root)['blockers'], true));
        try { HistoryPurge::restore($root); throw new LogicException('Restored after prune'); }
        catch (RuntimeException $e) { assertSame(true, str_contains($e->getMessage(), 'restore the repository from the pre-purge backup')); }
        try { HistoryPurge::execute($root, HistoryPurgePlanner::plan($root)); throw new LogicException('Ran over a finished purge'); }
        catch (RuntimeException $e) { assertSame(true, str_contains($e->getMessage(), 'pre-purge backup')); }
        $this->cleanup($root);
    }

    public function testInterruptedRunBeforePruneIsRestoredAndRerun(): void
    {
        [$root, $git] = $this->fixture();
        $before = $this->snapshot($root);
        // Simulate a run killed after the journal was written and a branch had already moved.
        mkdir($root . '/.git/history-purge', 0700);
        $lines = '';
        foreach ($before['refs'] as $ref => $sha) { $lines .= $sha . ' ' . $ref . "\n"; }
        file_put_contents($root . '/.git/history-purge/refs-before.txt', $lines);
        file_put_contents($root . '/.git/history-purge/journal.txt', "Phase: rewrite\nHEAD: refs/heads/main\n");
        $git(['update-ref', 'refs/heads/side', 'HEAD~2']);
        $git(['update-ref', 'refs/heads/stray', 'HEAD']);
        assertSame(true, in_array('interrupted_purge', HistoryPurgePlanner::plan($root)['blockers'], true));
        assertSame(['phase' => 'rewrite', 'head' => 'refs/heads/main', 'restorable' => true], HistoryPurge::interrupted($root));

        assertSame(['phase' => 'rewrite', 'refs' => count($before['refs'])], HistoryPurge::restore($root));
        assertSame($before['refs'], $this->refs($root));
        assertSame(null, HistoryPurge::interrupted($root));
        try { HistoryPurge::restore($root); throw new LogicException('Restored twice'); }
        catch (RuntimeException $e) { assertSame('No interrupted purge to restore.', $e->getMessage()); }

        // execute() restores an interrupted run itself before purging.
        mkdir($root . '/.git/history-purge', 0700);
        file_put_contents($root . '/.git/history-purge/refs-before.txt', $lines);
        file_put_contents($root . '/.git/history-purge/journal.txt', "Phase: verify-refs\nHEAD: refs/heads/main\n");
        $git(['update-ref', 'refs/heads/side', 'HEAD~1']);
        $plan = HistoryPurgePlanner::plan($root);
        assertSame(false, $plan['can_run']);
        $result = HistoryPurge::execute($root, $plan);
        assertSame(true, $result['restored_interrupted_run']);
        assertSame(true, $result['verified']);
        assertSame($result['map'][$before['refs']['refs/heads/side']], trim($git(['rev-parse', 'refs/heads/side'])));
        $this->cleanup($root);
    }

    /** @return array{0:string,1:callable} */
    private function fixture(): array
    {
        $root = sys_get_temp_dir() . '/history-purge-run-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0775, true);
        $git = static fn (array $args): string => LabelRemoval::git($root, $args);
        $git(['init', '-q', '-b', 'main']); $git(['config', 'user.name', 'Test']); $git(['config', 'user.email', 'test@example.invalid']);
        $this->post($root, 'quote-20260101000000-dddd0001', '2026-01-01T00:00:00Z', 'secret prose');
        $git(['add', '.']); $git(['commit', '-q', '-m', 'first post', '--date', '2026-01-01T00:00:00Z']);
        $this->post($root, 'quote-20260102000000-dddd0002', '2026-01-02T00:00:00Z', 'surviving prose');
        $git(['add', '.']); $git(['commit', '-q', '-m', 'second post', '--date', '2026-01-02T00:00:00Z']);
        $git(['branch', '-q', 'side']);
        $git(['tag', 'v1']);
        file_put_contents($root . '/' . self::GONE, file_get_contents($root . '/' . self::GONE) . "more secret\n");
        $git(['add', '.']); $git(['commit', '-q', '-m', 'edit first', '--date', '2026-01-03T00:00:00Z']);
        $source = file_get_contents($root . '/' . self::GONE);
        unlink($root . '/' . self::GONE); unlink($root . '/' . self::GONE . '.asc');
        mkdir($root . '/records/post-removals', 0775, true);
        file_put_contents($root . '/' . self::RECEIPT, RemovalReceipt::encode(['Format' => 'removal-acceptance-v1',
            'Post-ID' => 'quote-20260101000000-dddd0001', 'Operation' => 'delete', 'Authority' => 'local-operator',
            'Accepted-At' => '2026-10-30T00:00:00Z', 'Actor' => 'local-operator', 'Source-SHA256' => hash('sha256', $source),
            'Thread-ID' => 'quote-20260101000000-dddd0001', 'Parent-ID' => '', 'Post-Created-At' => '2026-01-01T00:00:00Z',
            'Author-Identity-ID' => self::AUTHOR, 'Thread-Type' => '', 'Retraction-Class' => '', 'Retraction-Attribution' => '',
            'Request-SHA256' => '', 'Signature-SHA256' => '']));
        $git(['add', '-A']); $git(['commit', '-q', '-m', 'delete first', '--date', '2026-01-04T00:00:00Z']);
        $git(['update-ref', 'ORIG_HEAD', 'HEAD']);
        return [$root, $git];
    }

    private function snapshot(string $root): array
    {
        $git = static fn (array $args): string => LabelRemoval::git($root, $args);
        $blobs = [];
        foreach (explode("\n", $git(['log', '--all', '--format=', '--raw', '--no-abbrev', '--', self::GONE, self::GONE . '.asc'])) as $line) {
            if (preg_match('/^:\d+ \d+ ([0-9a-f]{40}) ([0-9a-f]{40}) /', $line, $m) === 1) {
                foreach ([$m[1], $m[2]] as $sha) { if (trim($sha, '0') !== '') { $blobs[$sha] = true; } }
            }
        }
        return [
            'refs' => $this->refs($root),
            'commits' => array_values(array_filter(explode("\n", trim($git(['rev-list', '--all']))))),
            'blobs' => array_keys($blobs),
            'receipt' => file_get_contents($root . '/' . self::RECEIPT),
            'kept' => file_get_contents($root . '/' . self::KEPT),
            'kept_blob' => trim($git(['rev-parse', 'HEAD:' . self::KEPT])),
            'log' => $git(['log', '--all', '--format=%an %ad %cd %s', '--date=iso-strict']),
            'head_tree' => trim($git(['rev-parse', 'HEAD^{tree}'])),
        ];
    }

    private function refs(string $root): array
    {
        $refs = [];
        foreach (explode("\n", trim(LabelRemoval::git($root, ['for-each-ref', '--format=%(objectname) %(refname)']))) as $line) {
            [$sha, $ref] = explode(' ', $line, 2);
            $refs[$ref] = $sha;
        }
        return $refs;
    }

    private function objectExists(string $root, string $sha): bool
    {
        try { LabelRemoval::git($root, ['cat-file', '-e', $sha]); return true; }
        catch (RuntimeException) { return false; }
    }

    private function post(string $root, string $id, string $createdAt, string $body): void
    {
        $path = CanonicalPathResolver::datedPost($id, $createdAt);
        if (!is_dir(dirname($root . '/' . $path))) mkdir(dirname($root . '/' . $path), 0775, true);
        file_put_contents($root . '/' . $path, "Post-ID: $id\nCreated-At: $createdAt\nAuthor-Identity-ID: " . self::AUTHOR . "\nBoard-Tags: general\nSubject: $body\n\n$body\n");
        file_put_contents($root . '/' . $path . '.asc', "signature of $id\n");
    }

    private function cleanup(string $path): void
    {
        if (!is_dir($path)) return;
        exec('rm -rf ' . escapeshellarg($path));
    }
}
