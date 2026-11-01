<?php
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalPathResolver;
use ForumRewrite\Canonical\LabelRemoval;
use ForumRewrite\Canonical\RemovalReceipt;

final class PurgeHistoryCommandTest
{
    private const AUTHOR = 'openpgp:aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';

    public function testHelpAndOptionValidationFollowTheCliContract(): void
    {
        $project = dirname(__DIR__);
        [$code, $out, $err] = $this->run($project, './v3 purge-history --help');
        assertSame(0, $code); assertSame(true, str_starts_with($out, 'Usage:')); assertSame('', $err);
        [$code, $out, $err] = $this->run($project, './v3 purge-history');
        assertSame(1, $code); assertSame('', $out); assertSame(true, str_starts_with($err, 'Usage:'));
        [$code, $out, $err] = $this->run($project, './v3 purge-history preview --unknown');
        assertSame(1, $code); assertSame('', $out);
        assertSame(true, str_contains($err, 'Error: Unknown option: --unknown')); assertSame(true, str_contains($err, 'Usage:')); assertSame(false, str_contains($err, 'Stack trace:'));
        [$code, , $err] = $this->run($project, './v3 purge-history preview --confirm=/x');
        assertSame(1, $code); assertSame(true, str_contains($err, 'Error: Unknown option: --confirm=/x'));
        [$code, , $err] = $this->run($project, './v3 purge-history squash');
        assertSame(1, $code); assertSame(true, str_contains($err, 'Error: Unknown command: squash'));
        [$code, , $err] = $this->run($project, './v3 purge-history preview --repository-root=/nonexistent/repository');
        assertSame(1, $code); assertSame(true, str_contains($err, 'Repository root does not exist'));
        $root = $this->fixture();
        [$code, $out, $err] = $this->run($project, './v3 purge-history run --repository-root=' . escapeshellarg($root));
        assertSame(1, $code); assertSame('', $out);
        assertSame(true, str_contains($err, 'run needs --confirm=' . realpath($root)));
        [$code, , $err] = $this->run($project, './v3 purge-history run --confirm=/elsewhere --repository-root=' . escapeshellarg($root));
        assertSame(1, $code); assertSame(true, str_contains($err, 'run needs --confirm='));
        // Nothing changed in the repository.
        assertSame(2, count(array_filter(explode("\n", trim(LabelRemoval::git($root, ['log', '--all', '--format=%H', '--', 'records/posts/2026/01/01']))))));
        $this->cleanup($root);
    }

    public function testPreviewReportsBlockersAndRunPurgesThroughTheDispatcher(): void
    {
        $project = dirname(__DIR__);
        $empty = sys_get_temp_dir() . '/purge-cli-empty-' . bin2hex(random_bytes(6));
        mkdir($empty . '/records/posts', 0775, true);
        LabelRemoval::git($empty, ['init', '-q', '-b', 'main']);
        [$code, $out, $err] = $this->run($project, './v3 purge-history preview --repository-root=' . escapeshellarg($empty));
        assertSame(1, $code); assertSame('', $err);
        assertSame(true, str_contains($out, "Blocked:\n  - no accepted delete receipt names a path that exists in history; nothing to purge"));
        assertSame(false, str_contains($out, 'Next:'));
        $this->cleanup($empty);

        $root = $this->fixture();
        $state = $root . '-state';
        mkdir($state . '/static', 0775, true);
        $database = $state . '/post_index.sqlite3';
        $target = 'records/posts/2026/01/01/quote-20260101000000-ffff0001.txt';
        $common = ' --repository-root=' . escapeshellarg($root) . ' --database-path=' . escapeshellarg($database) . ' --static-html-root=' . escapeshellarg($state . '/static');

        [$code, $out, $err] = $this->run($project, './v3 purge-history preview' . $common);
        assertSame(0, $code, $err); assertSame('', $err);
        assertSame(true, str_contains($out, "Accepted delete receipts: 1\nPaths to purge: 2\n  " . $target . "\n  " . $target . ".asc\n"));
        assertSame(true, str_contains($out, 'Dry run on a temporary clone: clone'));
        assertSame(true, str_contains($out, '2 of 2 commits rewritten, 0 still touching purged paths'));
        assertSame(true, str_contains($out, 'Next: back up the repository including .git, verify the backup, then run ./v3 purge-history run --confirm=' . realpath($root)));
        [$code, $out] = $this->run($project, './v3 purge-history preview --skip-dry-run --json' . $common);
        assertSame(0, $code);
        $json = json_decode($out, true, 16, JSON_THROW_ON_ERROR);
        assertSame('ready', $json['status']); assertSame([$target, $target . '.asc'], $json['targets']); assertSame(null, $json['dry_run']); assertSame([], $json['blockers']);
        // The preview left no trace: history still holds the prose and no dry-run directory remains.
        assertSame(2, count(array_filter(explode("\n", trim(LabelRemoval::git($root, ['log', '--all', '--format=%H', '--', $target]))))));
        assertSame([], glob(sys_get_temp_dir() . '/history-purge-dry-run-*') ?: []);

        [$code, $out, $err] = $this->run($project, './v3 purge-history run --confirm=' . escapeshellarg($root) . $common);
        assertSame(0, $code, $out . $err); assertSame('', $err);
        assertSame(true, str_starts_with($out, "History purge complete\n"));
        assertSame(true, str_contains($out, "Paths purged: 2\nCommits rewritten: 2 of 2\nOld objects removed: 2 blobs\n"));
        assertSame(true, str_contains($out, 'History rewrite record: records/history-rewrites/history-rewrite-'));
        assertSame(true, str_contains($out, "Verified: no purged path or pre-purge commit remains in the object database.\n"));
        assertSame(true, str_contains($out, 'Not reached by this purge: your own backups, other clones and mirrors'));
        assertSame(true, str_contains($out, 'take a fresh image backup'));
        assertSame('', trim(LabelRemoval::git($root, ['log', '--all', '--format=%H', '--', $target])));
        assertSame(true, str_starts_with(trim(LabelRemoval::git($root, ['log', '-1', '--format=%s'])), 'Record history purge '));
        assertSame(true, is_file($database));
        assertSame(true, is_file($state . '/static/index.html'));
        [$code, $out] = $this->run($project, './v3 purge-history preview --skip-dry-run' . $common);
        assertSame(1, $code);
        assertSame(true, str_contains($out, 'nothing to purge'));
        [$code, , $err] = $this->run($project, './v3 purge-history run --confirm=' . escapeshellarg($root) . $common);
        assertSame(1, $code);
        assertSame(true, str_contains($err, "History purge refused:\n  - no accepted delete receipt"));

        exec('rm -rf ' . escapeshellarg($state));
        $this->cleanup($root);
    }

    /** A repository with one surviving post, one deleted post with a local receipt, and an instance record. */
    private function fixture(): string
    {
        $root = sys_get_temp_dir() . '/purge-cli-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0775, true);
        mkdir($root . '/records/instance', 0775, true);
        copy(dirname(__DIR__) . '/tests/fixtures/parity_minimal_v1/records/instance/public.txt', $root . '/records/instance/public.txt');
        $git = static fn (array $args): string => LabelRemoval::git($root, $args);
        $git(['init', '-q', '-b', 'main']); $git(['config', 'user.name', 'Test']); $git(['config', 'user.email', 'test@example.invalid']);
        foreach ([['quote-20260101000000-ffff0001', '2026-01-01T00:00:00Z', 'secret prose'], ['quote-20260102000000-ffff0002', '2026-01-02T00:00:00Z', 'surviving prose']] as [$id, $createdAt, $body]) {
            $path = CanonicalPathResolver::datedPost($id, $createdAt);
            mkdir(dirname($root . '/' . $path), 0775, true);
            file_put_contents($root . '/' . $path, "Post-ID: $id\nCreated-At: $createdAt\nAuthor-Identity-ID: " . self::AUTHOR . "\nBoard-Tags: general\nSubject: $body\n\n$body\n");
            file_put_contents($root . '/' . $path . '.asc', "signature of $id\n");
        }
        $git(['add', '-A']); $git(['commit', '-q', '-m', 'posts']);
        $gone = 'records/posts/2026/01/01/quote-20260101000000-ffff0001.txt';
        $source = file_get_contents($root . '/' . $gone);
        unlink($root . '/' . $gone); unlink($root . '/' . $gone . '.asc');
        mkdir($root . '/records/post-removals', 0775, true);
        file_put_contents($root . '/records/post-removals/quote-20260101000000-ffff0001.txt', RemovalReceipt::encode(['Format' => 'removal-acceptance-v1',
            'Post-ID' => 'quote-20260101000000-ffff0001', 'Operation' => 'delete', 'Authority' => 'local-operator', 'Accepted-At' => '2026-10-30T00:00:00Z',
            'Actor' => 'local-operator', 'Source-SHA256' => hash('sha256', $source), 'Thread-ID' => 'quote-20260101000000-ffff0001', 'Parent-ID' => '',
            'Post-Created-At' => '2026-01-01T00:00:00Z', 'Author-Identity-ID' => self::AUTHOR, 'Thread-Type' => '', 'Retraction-Class' => '',
            'Retraction-Attribution' => '', 'Request-SHA256' => '', 'Signature-SHA256' => '']));
        $git(['add', '-A']); $git(['commit', '-q', '-m', 'delete secret']);
        return $root;
    }

    /** @return array{0:int,1:string,2:string} */
    private function run(string $cwd, string $command): array
    {
        $pipes = [];
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd);
        if (!is_resource($process)) throw new RuntimeException('Unable to run command.');
        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]); $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process), (string) $stdout, (string) $stderr];
    }

    private function cleanup(string $path): void
    {
        if (is_dir($path)) exec('rm -rf ' . escapeshellarg($path));
    }
}
