<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Security\ApprovalAcceptance;
use ForumRewrite\Security\ApprovalReceiptText;
use ForumRewrite\Support\ApprovalReceiptMaintenance;

final class ApprovalReceiptMaintenanceTest
{
    private function fixture(): array
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('receipts', true);
        $context = ['actor_identity_id' => 'openpgp:' . str_repeat('a', 40), 'actor_username' => 'alice',
            'target_identity_id' => 'openpgp:' . str_repeat('b', 40), 'target_username' => 'bob', 'target_profile_slug' => 'bob',
            'target_thread_id' => 'root-001', 'target_post_id' => 'root-001', 'existing_key_count' => 0,
            'classification' => 'new_account', 'actor_is_operator' => false, 'cross_account_allowed' => false, 'allowed' => true];
        $grant = "Post-ID: grant\nCreated-At: 2026-01-01T00:00:00Z\nBoard-Tags: approval\nThread-ID: root-001\nParent-ID: root-001\nAuthor-Identity-ID: " . $context['actor_identity_id']
            . "\n\nApprove-Identity-ID: " . $context['target_identity_id'] . "\nApproval-Context-SHA256: " . hash('sha256', json_encode($context)) . "\n";
        $w->put($root . '/records/posts/grant.txt', $grant);
        $text = ApprovalAcceptance::encode($grant, $context, '2026-01-01T00:01:00Z', 'direct');
        $w->put($root . '/records/approval-acceptances/grant.json', json_encode(ApprovalReceiptText::decode($text)));
        $w->git($root);
        return [$w, $root, $w->root . '/cache/index.sqlite3', $grant, $text];
    }

    public function testCommandPreviewConversionAndRepeatPreserveGrant(): void
    {
        [$w, $root, $db, $grant, $text] = $this->fixture();
        $command = [__DIR__ . '/../v3', 'approval', 'receipts', 'convert', '--repository-root=' . $root, '--database-path=' . $db];
        [$code, $out] = $w->command([...$command, '--dry-run']); assertSame(0, $code, $out);
        assertStringContains('Status: preview', $out); assertSame(false, is_file($db));
        assertSame(false, is_file($root . '/.git/approval-receipt-conversion.json'));
        [$code, $out] = $w->command($command); assertSame(0, $code, $out);
        assertStringContains('Status: converted', $out);
        assertSame($text, file_get_contents($root . '/' . ApprovalAcceptance::path('grant')));
        assertSame($grant, file_get_contents($root . '/records/posts/grant.txt'));
        assertSame(false, is_file($root . '/records/approval-acceptances/grant.json'));
        [$code, $out] = $w->command([...$command, '--json']); assertSame(0, $code, $out);
        assertSame('unchanged', json_decode($out, true)['status']);
    }

    public function testInterruptedConversionAndCommittedRefreshRetry(): void
    {
        foreach (['journal', 'text-written', 'committed', 'refreshed'] as $point) {
            [$w, $root, $db, $grant, $text] = $this->fixture();
            $service = new ApprovalReceiptMaintenance($root, $db, function ($actual) use ($point) {
                if ($actual === $point) throw new RuntimeException('Interrupted at ' . $point);
            });
            $this->rejects(fn () => $service->convert(false), 'Interrupted');
            assertSame(true, is_file($root . '/.git/approval-receipt-conversion.json'));
            $result = (new ApprovalReceiptMaintenance($root, $db))->convert(false);
            assertSame('converted', $result['status']);
            assertSame($text, file_get_contents($root . '/' . ApprovalAcceptance::path('grant')));
            assertSame($grant, file_get_contents($root . '/records/posts/grant.txt'));
            assertSame(false, is_file($root . '/.git/approval-receipt-conversion.json'));
            [$code, $count] = $w->command(['git', '-C', $root, 'rev-list', '--count', 'HEAD']);
            assertSame('2', trim($count));
        }
    }

    public function testFailedCommitRetriesAndInterveningEditsArePreserved(): void
    {
        [$w, $root, $db] = $this->fixture();
        $hook = $root . '/.git/hooks/pre-commit'; file_put_contents($hook, "#!/bin/sh\nexit 1\n"); chmod($hook, 0700);
        $service = new ApprovalReceiptMaintenance($root, $db);
        $this->rejects(fn () => $service->convert(false), 'Git operation failed');
        unlink($hook);
        [$code, $staged] = $w->command(['git', '-C', $root, 'diff', '--cached', '--name-only']);
        assertSame('', trim($staged));
        $new = $root . '/' . ApprovalAcceptance::path('grant'); $bytes = file_get_contents($new);
        file_put_contents($new, 'Intervening edit');
        $this->rejects(fn () => $service->convert(false), 'Receipt changed');
        assertSame('Intervening edit', file_get_contents($new));
        file_put_contents($new, $bytes);
        assertSame('converted', $service->convert(false)['status']);
    }

    public function testDirtyConflictOrLockPreventsMutation(): void
    {
        [$w, $root, $db, $grant, $text] = $this->fixture();
        $service = new ApprovalReceiptMaintenance($root, $db);
        file_put_contents($root . '/unrelated', 'keep');
        $this->rejects(fn () => $service->convert(false), 'clean'); unlink($root . '/unrelated');
        if (!is_dir(dirname($db))) mkdir(dirname($db), 0700, true); $lock = fopen(dirname($db) . '/forum-rewrite.lock', 'c+'); flock($lock, LOCK_EX);
        $prior = getenv('FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS'); putenv('FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=0');
        try { $this->rejects(fn () => $service->convert(false), 'Timed out'); }
        finally { flock($lock, LOCK_UN); fclose($lock); putenv($prior === false ? 'FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS' : 'FORUM_EXECUTION_LOCK_TIMEOUT_SECONDS=' . $prior); }
        $bad = ApprovalReceiptText::decode($text); $bad['accepted_at'] = '2026-01-01T00:02:00Z';
        $w->put($root . '/' . ApprovalAcceptance::path('grant'), ApprovalReceiptText::encode($bad));
        $w->command(['git', '-C', $root, 'add', '.']); $w->command(['git', '-C', $root, 'commit', '-qm', 'Conflict fixture']);
        assertSame(1, count($service->inspect()['blockers']));
        $this->rejects(fn () => $service->convert(false), 'Conflicting');
        assertSame(true, is_file($root . '/records/approval-acceptances/grant.json'));
        assertSame(false, is_file($root . '/.git/approval-receipt-conversion.json'));
    }

    public function testReadinessAndTextOnlyOperationWithoutLegacyDecoder(): void
    {
        [$w, $root, $db, $grant, $text] = $this->fixture();
        $service = new ApprovalReceiptMaintenance($root, $db);
        assertSame(false, $service->readiness()['live_repository_ready']);
        assertSame(false, is_file($db));
        $service->convert(false);
        assertSame(true, $service->readiness()['live_repository_ready']);
        $blocker = $w->root . '/no-legacy.php';
        file_put_contents($blocker, '<?php spl_autoload_register(function ($class) { if ($class === "ForumRewrite\\Security\\LegacyApprovalReceipt") throw new RuntimeException("Legacy decoder unavailable"); }, true, true);');
        [$code, $out] = $w->command(['php', '-d', 'auto_prepend_file=' . $blocker, __DIR__ . '/../scripts/approval_receipts.php', 'readiness', '--repository-root=' . $root, '--database-path=' . $db]);
        assertSame(0, $code, $out); assertStringContains('Live-Repository-Ready: yes', $out);
        // New-format encode/read and normal rebuild run in a fresh process without the adapter.
        $probe = $w->root . '/probe.php';
        file_put_contents($probe, '<?php require ' . var_export(realpath(__DIR__ . '/../autoload.php'), true) . ';'
            . '$root = $argv[1]; $data = \ForumRewrite\Security\ApprovalAcceptance::read($root, "records/posts/grant.txt");'
            . '$text = \ForumRewrite\Security\ApprovalAcceptance::encode(file_get_contents($root . "/records/posts/grant.txt"), $data["context"], $data["accepted_at"], $data["kind"]);'
            . 'if ($text !== file_get_contents($root . "/records/approval-acceptances/grant.txt")) throw new RuntimeException("Mismatch");'
            . '(new \ForumRewrite\ReadModel\ReadModelBuilder($root, $argv[2], new \ForumRewrite\Canonical\CanonicalRecordRepository($root)))->rebuild();');
        [$code, $out] = $w->command(['php', '-d', 'auto_prepend_file=' . $blocker, $probe, $root, $db]); assertSame(0, $code, $out);
        $archive = $w->root . '/text.tar.gz';
        [$code, $out] = $w->command(['tar', '-czf', $archive, '-C', $root, 'records']); assertSame(0, $code, $out);
        $restore = $w->repository('restore', true); $w->git($restore);
        [$code, $out] = $w->command(['php', '-d', 'auto_prepend_file=' . $blocker, __DIR__ . '/../scripts/import_repository_archive.php', $archive, $restore, $w->root . '/restore.sqlite3']);
        assertSame(0, $code, $out);
        assertSame($text, file_get_contents($restore . '/' . ApprovalAcceptance::path('grant')));
        file_put_contents($root . '/records/approval-acceptances/grant.json', json_encode(ApprovalReceiptText::decode($text)));
        [$code, $out] = $w->command(['php', '-d', 'auto_prepend_file=' . $blocker, __DIR__ . '/../scripts/approval_receipts.php', 'readiness', '--repository-root=' . $root, '--database-path=' . $db]);
        assertSame(1, $code, $out); assertStringContains('Legacy decoder unavailable', $out);
        unlink($root . '/records/approval-acceptances/grant.json');
        file_put_contents($root . '/.git/approval-receipt-conversion.json', '{}');
        assertSame(false, $service->readiness()['live_repository_ready']);
        unlink($root . '/.git/approval-receipt-conversion.json');
        file_put_contents($root . '/records/approval-acceptances/orphan.txt', $text);
        assertSame(false, $service->readiness()['live_repository_ready']);
    }

    public function testSymlinkDirectoryIsRejectedAndExistingEquivalentTextIsPreserved(): void
    {
        [$w, $root, $db, $grant, $text] = $this->fixture();
        $dir = $root . '/records/approval-acceptances'; $outside = $w->root . '/outside';
        rename($dir, $outside); symlink($outside, $dir);
        $service = new ApprovalReceiptMaintenance($root, $db);
        $this->rejects(fn () => $service->inspect(), 'symlink');
        assertSame(true, is_file($outside . '/grant.json'));
        unlink($dir); rename($outside, $dir);
        $lines = explode("\n", rtrim($text, "\n"));
        [$lines[1], $lines[2]] = [$lines[2], $lines[1]];
        $equivalent = implode("\n", $lines) . "\n";
        file_put_contents($root . '/' . ApprovalAcceptance::path('grant'), $equivalent);
        $w->command(['git', '-C', $root, 'add', '.']); $w->command(['git', '-C', $root, 'commit', '-qm', 'Equivalent text fixture']);
        assertSame('converted', $service->convert(false)['status']);
        assertSame($equivalent, file_get_contents($root . '/' . ApprovalAcceptance::path('grant')));
    }

    private function rejects(callable $call, string $message): void
    {
        try { $call(); } catch (Throwable $e) { assertStringContains($message, $e->getMessage()); return; }
        throw new RuntimeException('Expected failure: ' . $message);
    }
}
