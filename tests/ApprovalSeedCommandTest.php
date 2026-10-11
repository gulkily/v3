<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelBuilder;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\Support\LocalRepositoryBootstrap;
use ForumRewrite\Write\LocalWriteService;

final class ApprovalSeedCommandTest
{
    private const ID = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';

    public function testBothSpellingsSeedTheSelectedInstance(): void
    {
        foreach ([['approve'], ['approval', 'seed']] as $command) {
            $this->withFixture(function (string $root, string $db, string $public, string $static) use ($command): void {
                [$code, $out, $err] = $this->runSeed($root, $db, $public, $static, $command);
                if ($code !== 0) { throw new RuntimeException($err); }
                assertSame(0, $code);
                assertStringContains('Seeded approval for ' . self::ID, $out);
                $pdo = new PDO('sqlite:' . $db);
                assertSame(1, (int) $pdo->query('SELECT is_approved FROM profiles')->fetchColumn());
                assertSame('root', $pdo->query('SELECT approved_by_label FROM profiles')->fetchColumn());
                assertSame('write_incremental', ReadModelMetadata::readMetadata($pdo)['rebuild_reason']);
                assertSame(ReadModelMetadata::repositoryHead($root), ReadModelMetadata::readMetadata($pdo)['repository_head']);
                assertSame(0666 & ~umask(), fileperms($root . '/' . $this->seedPath()) & 0777);
                assertSame('test seed', (new CanonicalRecordRepository($root))->loadApprovalSeed($this->seedPath())->seedReason);
            });
        }
    }

    public function testSeedRefreshMatchesRebuildForTransitiveApprovalScoresAndAttribution(): void
    {
        $this->withFixture(function (string $root, string $db, string $public): void {
            $target = $this->addApprovalChain($root);
            $this->rebuild($root, $db);
            $pdo = new PDO('sqlite:' . $db);
            $postCount = $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn();
            $service = new LocalWriteService($root, $db, $public, new CanonicalRecordRepository($root));
            $result = $service->seedApprovedIdentity(self::ID, 'test seed');
            assertTrue(isset($result['timings']['read_model_approval_seed_incremental']));
            assertSame(2, (int) $pdo->query('SELECT COUNT(*) FROM profiles WHERE is_approved = 1')->fetchColumn());
            assertSame(1, (int) $pdo->query("SELECT score_total FROM threads WHERE root_post_id = 'root-001'")->fetchColumn());
            assertSame(1, (int) $pdo->query("SELECT approved_flag_count FROM posts WHERE post_id = 'reply-001'")->fetchColumn());
            assertSame($postCount, $pdo->query('SELECT COUNT(*) FROM posts')->fetchColumn());
            $this->assertParity($root, $db);
            $service->seedApprovedIdentity($target, 'promote to root');
            assertSame(2, (int) $pdo->query("SELECT COUNT(*) FROM profiles WHERE approved_by_label = 'root'")->fetchColumn());
            $this->assertParity($root, $db);
        });
    }

    public function testNonGitSeedUsesRecordedFreshnessEvidence(): void
    {
        $this->withFixture(function (string $root, string $db, string $public, string $static): void {
            [$code, , $err] = $this->runSeed($root, $db, $public, $static);
            if ($code !== 0) { throw new RuntimeException($err); }
            $metadata = ReadModelMetadata::readMetadata(new PDO('sqlite:' . $db));
            assertSame('write_incremental', $metadata['rebuild_reason']);
            assertSame(ReadModelMetadata::canonicalFingerprint($root), $metadata['canonical_fingerprint']);
            $this->assertParity($root, $db);
        }, false);
    }

    private function addApprovalChain(string $root): string
    {
        $fingerprint = str_repeat('b', 40);
        $target = 'openpgp:' . $fingerprint;
        copy($root . '/records/public-keys/openpgp-' . strtoupper(substr(self::ID, 8)) . '.asc', $root . '/records/public-keys/openpgp-' . strtoupper($fingerprint) . '.asc');
        $identity = file_get_contents($root . '/records/identity/identity-' . str_replace(':', '-', self::ID) . '.txt');
        $identity = str_replace([substr(self::ID, 8), strtoupper(substr(self::ID, 8)), 'root-001'], [$fingerprint, strtoupper($fingerprint), 'target-root'], $identity);
        file_put_contents($root . '/records/identity/identity-openpgp-' . $fingerprint . '.txt', $identity);
        file_put_contents($root . '/records/posts/target-root.txt', "Post-ID: target-root\nCreated-At: 2026-04-11T00:00:00Z\nBoard-Tags: identity internal\n\nTarget bootstrap.\n");
        file_put_contents($root . '/records/posts/early-approval.txt', "Post-ID: early-approval\nCreated-At: 2026-04-12T00:00:00Z\nBoard-Tags: identity approval\nThread-ID: target-root\nParent-ID: target-root\nAuthor-Identity-ID: " . self::ID . "\n\nApprove-Identity-ID: {$target}\n");
        file_put_contents($root . '/records/thread-labels/seed-like.txt', "Record-ID: seed-like\nCreated-At: 2026-04-13T00:00:00Z\nThread-ID: root-001\nOperation: add\nLabels: like\nAuthor-Identity-ID: {$target}\n\n");
        mkdir($root . '/records/post-reactions');
        file_put_contents($root . '/records/post-reactions/seed-flag.txt', "Record-ID: seed-flag\nCreated-At: 2026-04-13T00:00:00Z\nPost-ID: reply-001\nOperation: add\nTags: flag\nAuthor-Identity-ID: {$target}\n\n");
        $this->git($root, ['add', 'records']);
        $this->git($root, ['commit', '-m', 'Add dormant approval and reactions']);
        return $target;
    }

    private function assertParity(string $root, string $db): void
    {
        $fresh = dirname($db) . '/fresh.sqlite3';
        $this->rebuild($root, $fresh);
        $actual = new PDO('sqlite:' . $db);
        $expected = new PDO('sqlite:' . $fresh);
        foreach (['profiles' => 'identity_id', 'posts' => 'post_id', 'threads' => 'root_post_id', 'activity' => 'id', 'username_routes' => 'username_token'] as $table => $order) {
            assertSame($expected->query("SELECT * FROM {$table} ORDER BY {$order}")->fetchAll(PDO::FETCH_ASSOC), $actual->query("SELECT * FROM {$table} ORDER BY {$order}")->fetchAll(PDO::FETCH_ASSOC));
        }
    }

    private function git(string $root, array $args): string
    {
        [$code, $out, $err] = $this->process(['git', ...$args], $root);
        if ($code !== 0) { throw new RuntimeException($err); }
        return trim($out);
    }

    public function testUnreadyModelsAndDirtyRecordsFailBeforeWriting(): void
    {
        foreach (['missing', 'stale', 'schema', 'root', 'behind', 'dirty'] as $case) {
            $this->withFixture(function (string $root, string $db, string $public, string $static) use ($case): void {
                $pdo = new PDO('sqlite:' . $db);
                if ($case === 'missing') { unlink($db); }
                if ($case === 'stale') { file_put_contents(dirname($db) . '/read_model_stale.json', '{"reason":"unrelated"}'); }
                if ($case === 'schema') { $pdo->exec("UPDATE metadata SET value = 'old' WHERE key = 'schema_version'"); }
                if ($case === 'root') { $pdo->exec("UPDATE metadata SET value = '/wrong' WHERE key = 'repository_root'"); }
                if ($case === 'behind') { $this->git($root, ['commit', '--allow-empty', '-m', 'Unindexed head']); }
                if ($case === 'dirty') { file_put_contents($root . '/records/posts/reply-001.txt', "Changed body.\n", FILE_APPEND); }
                $head = ReadModelMetadata::repositoryHead($root);
                [$code, $out, $err] = $this->runSeed($root, $db, $public, $static);
                assertSame(1, $code);
                assertSame('', $out);
                assertStringContains('Approval seed not written.', $err);
                assertFalse(is_file($root . '/' . $this->seedPath()));
                assertSame($head, ReadModelMetadata::repositoryHead($root));
                if ($case === 'missing') { assertFalse(is_file($db)); }
                if ($case === 'stale') { assertSame('{"reason":"unrelated"}', file_get_contents(dirname($db) . '/read_model_stale.json')); }
            });
        }
    }

    public function testNonGitChangesRequireExplicitRepair(): void
    {
        $this->withFixture(function (string $root, string $db, string $public, string $static): void {
            file_put_contents($root . '/records/posts/reply-001.txt', "Changed body.\n", FILE_APPEND);
            [$code, , $err] = $this->runSeed($root, $db, $public, $static);
            assertSame(1, $code);
            assertStringContains('freshness evidence', $err);
            assertFalse(is_file($root . '/' . $this->seedPath()));
            $this->rebuild($root, $db);
            [$code] = $this->runSeed($root, $db, $public, $static);
            assertSame(0, $code);
            $this->assertParity($root, $db);
        }, false);
    }

    public function testRefreshFailureRollsBackAndRetryAfterRepairDoesNotDuplicateSeed(): void
    {
        $this->withFixture(function (string $root, string $db, string $public, string $static): void {
            $pdo = new PDO('sqlite:' . $db);
            $pdo->exec("CREATE TRIGGER fail_seed BEFORE UPDATE ON profiles BEGIN SELECT RAISE(ABORT, 'injected refresh failure'); END");
            [$code, $out, $err] = $this->runSeed($root, $db, $public, $static);
            assertSame(1, $code);
            assertSame('', $out);
            assertStringContains('persisted and committed', $err);
            assertStringContains('injected refresh failure', $err);
            assertSame(0, (int) $pdo->query('SELECT is_approved FROM profiles')->fetchColumn());
            assertTrue(is_file(dirname($db) . '/read_model_stale.json'));
            $head = ReadModelMetadata::repositoryHead($root);
            [$code, , $err] = $this->runSeed($root, $db, $public, $static);
            assertSame(1, $code);
            assertStringContains('persisted and committed', $err);
            // Explicit operator repair, followed by a normal retry.
            $this->repair($root, $db);
            [$code] = $this->runSeed($root, $db, $public, $static);
            assertSame(0, $code);
            assertSame($head, ReadModelMetadata::repositoryHead($root));
            $this->assertParity($root, $db);
        });
    }

    public function testCommitFailureRetainsSeedAndPreservesUnrelatedStagedWork(): void
    {
        $this->withFixture(function (string $root, string $db, string $public, string $static): void {
            file_put_contents($root . '/operator-notes.txt', 'keep staged');
            $this->git($root, ['add', 'operator-notes.txt']);
            file_put_contents($root . '/.git/hooks/pre-commit', "#!/bin/sh\nexit 1\n");
            chmod($root . '/.git/hooks/pre-commit', 0755);
            [$code, , $err] = $this->runSeed($root, $db, $public, $static);
            assertSame(1, $code);
            assertStringContains('persisted but not committed', $err);
            assertTrue(is_file($root . '/' . $this->seedPath()));
            unlink($root . '/.git/hooks/pre-commit');
            $this->repair($root, $db);
            [$code, , $err] = $this->runSeed($root, $db, $public, $static);
            if ($code !== 0) { throw new RuntimeException($err); }
            assertSame($this->seedPath(), $this->git($root, ['diff-tree', '--no-commit-id', '--name-only', '-r', 'HEAD']));
            assertSame('operator-notes.txt', $this->git($root, ['diff', '--cached', '--name-only']));
            $this->assertParity($root, $db);
        });
    }

    public function testPersistenceFailureLeavesNoPartialSeed(): void
    {
        $this->withFixture(function (string $root, string $db, string $public, string $static): void {
            mkdir($root . '/' . $this->seedPath());
            [$code, , $err] = $this->runSeed($root, $db, $public, $static);
            assertSame(1, $code);
            assertStringContains('Approval seed not written.', $err);
            assertSame([], glob($root . '/records/approval-seeds/.approval-seed-*'));
            assertFalse(is_file(dirname($db) . '/read_model_stale.json'));
        });
    }

    public function testMatchingRetriesAreIdempotentAndConflictingReasonsFail(): void
    {
        $this->withFixture(function (string $root, string $db, string $public, string $static): void {
            [$code] = $this->runSeed($root, $db, $public, $static);
            assertSame(0, $code);
            $head = ReadModelMetadata::repositoryHead($root);
            [$code] = $this->runSeed($root, $db, $public, $static, ['approval', 'seed']);
            assertSame(0, $code);
            [$code, , $err] = $this->runSeed($root, $db, $public, $static, ['approve'], 'different reason');
            assertSame(1, $code);
            assertStringContains('different reason', $err);
            assertSame($head, ReadModelMetadata::repositoryHead($root));
        });
    }

    public function testContendingSeedCommandsSerializeWithoutDuplicateCommits(): void
    {
        $this->withFixture(function (string $root, string $db, string $public, string $static): void {
            $lock = fopen(dirname($db) . '/forum-rewrite.lock', 'c+');
            flock($lock, LOCK_EX);
            $children = [];
            $env = array_merge(getenv(), ['FORUM_PUBLIC_ARTIFACT_ROOT' => $public, 'FORUM_STATIC_HTML_ROOT' => $static]);
            foreach ([['approve'], ['approval', 'seed']] as $command) {
                $process = proc_open([dirname(__DIR__) . '/v3', ...$command, self::ID, 'test seed', $root, $db], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__), $env);
                fclose($pipes[0]);
                $children[] = [$process, $pipes];
            }
            usleep(150000);
            assertFalse(is_file($root . '/' . $this->seedPath()));
            flock($lock, LOCK_UN);
            fclose($lock);
            foreach ($children as [$process, $pipes]) {
                stream_get_contents($pipes[1]);
                $err = stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                $code = proc_close($process);
                if ($code !== 0) { throw new RuntimeException($err); }
            }
            assertSame('2', $this->git($root, ['rev-list', '--count', 'HEAD']));
            $this->assertParity($root, $db);
        });
    }

    private function repair(string $root, string $db): void
    {
        $this->rebuild($root, $db);
        (new \ForumRewrite\ReadModel\ReadModelStaleMarker($db))->clear();
    }

    private function seedPath(): string
    {
        return 'records/approval-seeds/openpgp-' . substr(self::ID, 8) . '.txt';
    }

    private function withFixture(callable $test, bool $git = true): void
    {
        $base = sys_get_temp_dir() . '/approval seed-' . bin2hex(random_bytes(6));
        $root = $base . '/repository';
        $db = $base . '/cache/read.sqlite3';
        $public = $base . '/public';
        $static = $base . '/static';
        mkdir($root, 0777, true);
        mkdir($public);
        mkdir($static);
        try {
            $source = __DIR__ . '/fixtures/parity_minimal_v1';
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($source, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::SELF_FIRST);
            foreach ($iterator as $item) {
                $target = $root . '/' . $iterator->getSubPathName();
                $item->isDir() ? mkdir($target, 0777, true) : copy($item->getPathname(), $target);
            }
            unlink($root . '/' . $this->seedPath());
            if ($git) {
                LocalRepositoryBootstrap::initializeGitRepository($root);
            }
            $this->rebuild($root, $db);
            $test($root, $db, $public, $static);
        } finally {
            $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
            foreach ($iterator as $item) {
                $item->isDir() && !$item->isLink() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($base);
        }
    }

    private function rebuild(string $root, string $db): void
    {
        (new ReadModelBuilder($root, $db, new CanonicalRecordRepository($root)))->rebuild();
    }

    private function runSeed(string $root, string $db, string $public, string $static, array $command = ['approve'], string $reason = 'test seed'): array
    {
        return $this->process([
            dirname(__DIR__) . '/v3', ...$command,
            str_replace(':', '-', strtoupper(self::ID)), $reason, $root, $db,
        ], dirname(__DIR__), array_merge(getenv(), [
            'FORUM_PUBLIC_ARTIFACT_ROOT' => $public,
            'FORUM_STATIC_HTML_ROOT' => $static,
            'FORUM_SITE_ID' => 'chouse',
        ]));
    }

    private function process(array $command, string $cwd, ?array $env = null): array
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, $cwd, $env);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }
}
