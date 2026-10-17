<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Canonical\CanonicalRecordRepository;
use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\ReadModel\ReadModelMetadata;
use ForumRewrite\ReadModel\ReadModelStaleMarker;
use ForumRewrite\Security\ApprovalPolicy;
use ForumRewrite\Write\LocalWriteService;

final class ApprovalPolicyTest
{
    public function testPermissionMatrixAndLiveFlagChanges(): void
    {
        $this->withFixture(function (PDO $pdo, string $root, string $db): void {
            $policy = new ApprovalPolicy($pdo, $root);
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=false');
            assertSame(true, $policy->evaluate($this->id(2), $this->id(4))['allowed']); // new username
            assertSame(true, $policy->evaluate($this->id(2), $this->id(5))['allowed']); // own key
            assertSame(false, $policy->evaluate($this->id(2), $this->id(6))['allowed']); // existing Bob
            assertSame(true, $policy->evaluate($this->id(1), $this->id(6))['allowed']); // operator
            assertSame(false, $policy->evaluate($this->id(2), $this->id(6))['actor_is_operator']);
            assertSame(1, $policy->evaluate($this->id(2), $this->id(6))['existing_key_count']);
            assertSame('Add this key to my account', ApprovalPolicy::review($policy->evaluate($this->id(2), $this->id(5)))['label']);
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=true');
            assertSame(true, $policy->evaluate($this->id(2), $this->id(6))['allowed']);
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=false');
            // A competing first-key acceptance removes the sponsorship exception.
            $pdo->exec("UPDATE profiles SET is_approved = 1 WHERE identity_id = '" . $this->id(4) . "'");
            $pdo->exec("UPDATE profiles SET username_token = 'new' WHERE identity_id = '" . $this->id(6) . "'");
            assertSame(false, $policy->evaluate($this->id(2), $this->id(6))['allowed']);
            foreach ([[$this->id(5), $this->id(6)], [$this->id(2), $this->id(2)], [$this->id(2), $this->id(3)]] as [$actor, $target]) {
                $this->rejects(fn () => $policy->evaluate($actor, $target));
            }
        });
    }

    public function testInvitationReplayOrdersIssuanceBeforeSameSecondRedemption(): void
    {
        $posts = [];
        foreach (['a-redemption' => 'redeem', 'z-issuance' => 'issue'] as $id => $action) {
            $posts[] = ['post_id' => $id, 'created_at' => '2026-01-01T00:00:00Z',
                'body' => "Invitation-Action: {$action}\n"];
        }
        $ordered = \ForumRewrite\Invitation\InvitationReplayOrder::sortPosts($posts);
        assertSame(['z-issuance', 'a-redemption'], array_column($ordered, 'post_id'));
        $posts[0]['created_at'] = '2025-12-31T23:59:59Z';
        assertSame(['a-redemption', 'z-issuance'], array_column(\ForumRewrite\Invitation\InvitationReplayOrder::sortPosts($posts), 'post_id'));
    }

    public function testWriterEnforcesPolicyAndRejectsStaleStateAndProtocolSmuggling(): void
    {
        $this->withFixture(function (PDO $pdo, string $root, string $db): void {
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=false');
            $writer = new LocalWriteService($root, $db, $root . '/public', new CanonicalRecordRepository($root));
            $input = ['approver_identity_id' => $this->id(2), 'target_identity_id' => $this->id(6),
                'target_profile_slug' => 'key-6', 'thread_id' => 'boot-6', 'parent_id' => 'boot-6'];
            $this->rejects(fn () => $writer->prepareApproval($input), 'same username or a root-approved operator');
            $this->rejects(fn () => $writer->approveUser($input), 'same username or a root-approved operator');
            (new ReadModelStaleMarker($db))->mark(['reason' => 'test']);
            $this->rejects(fn () => $writer->prepareApproval($input), 'state is stale');
            (new ReadModelStaleMarker($db))->clear();
            $pdo->exec("UPDATE metadata SET value = 'outdated' WHERE key = 'repository_head'");
            $this->rejects(fn () => $writer->prepareApproval($input), 'state is stale');
            foreach (['identity approval', 'identity invitation internal', 'identity internal'] as $tags) {
                $this->rejects(fn () => $writer->prepareReply(['board_tags' => $tags, 'thread_id' => 'boot-6', 'parent_id' => 'boot-6', 'body' => 'Approve-Identity-ID: ' . $this->id(6)]), 'dedicated endpoint');
                $this->rejects(fn () => $writer->createReply(['board_tags' => $tags, 'thread_id' => 'boot-6', 'parent_id' => 'boot-6', 'body' => 'Approve-Identity-ID: ' . $this->id(6)]), 'dedicated endpoint');
            }
            assertSame([], glob($root . '/records/posts/*') ?: []);
        });
    }

    public function testOperatorAuthorityRequiresMatchingCanonicalSeed(): void
    {
        $this->withFixture(function (PDO $pdo, string $root, string $db): void {
            $path = $root . '/records/approval-seeds/openpgp-' . substr($this->id(2), 8) . '.txt';
            assertSame(false, ApprovalPolicy::isOperator($root, $this->id(2)));
            file_put_contents($path, 'Approved-Identity-ID: ' . $this->id(1) . "\nSeed-Reason: test\n\nMismatch.\n");
            assertSame(false, ApprovalPolicy::isOperator($root, $this->id(2)));
            unlink($path);
            symlink($root . '/records/approval-seeds/openpgp-' . substr($this->id(1), 8) . '.txt', $path);
            assertSame(false, ApprovalPolicy::isOperator($root, $this->id(2)));
            unlink($path);
            $writer = new LocalWriteService($root, $db, $root . '/public', new CanonicalRecordRepository($root));
            $this->rejects(fn () => $writer->prepareFeatureFlagChange([
                'key' => 'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL', 'value' => 'false',
            ], $this->id(2)), 'approved operator identity');
        });
    }

    public function testPreparedApprovalRechecksLivePolicyAndCompetingClaims(): void
    {
        $this->withFixture(function (PDO $pdo, string $root, string $db): void {
            mkdir($root . '/records/posts', 0777, true);
            foreach ([4, 6] as $n) {
                file_put_contents($root . '/records/posts/boot-' . $n . '.txt', "Post-ID: boot-{$n}\nCreated-At: 2026-01-01T00:00:00Z\nBoard-Tags: identity internal\n\nBootstrap.\n");
            }
            $writer = new LocalWriteService($root, $db, $root . '/public', new CanonicalRecordRepository($root));
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=true');
            $prepare = fn (int $n) => $writer->prepareApproval([
                'approver_identity_id' => $this->id(2), 'target_identity_id' => $this->id($n),
                'target_profile_slug' => 'key-' . $n, 'thread_id' => 'boot-' . $n, 'parent_id' => 'boot-' . $n,
            ]);
            $cross = $prepare(6);
            assertSame('Grant this key access as bob', $cross['approval_review']['label']);
            assertStringContains('Approval-Context-SHA256:', $cross['canonical_record']);
            $pdo->exec("UPDATE profiles SET username_token = 'bob', is_approved = 1 WHERE identity_id = '" . $this->id(4) . "'");
            $this->rejects(fn () => $writer->finalizePreparedApproval($cross + ['author_identity_id' => $this->id(2)]), 'Approval context changed');
            $pdo->exec("UPDATE profiles SET username_token = 'new', is_approved = 0 WHERE identity_id = '" . $this->id(4) . "'");
            $first = $prepare(4);
            assertSame('Approve new member new', $first['approval_review']['label']);
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=false');
            $this->rejects(fn () => $writer->finalizePreparedApproval($first + ['author_identity_id' => $this->id(2)]), 'Approval context changed');
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=true');
            $fresh = $prepare(4);
            $this->rejects(fn () => $writer->finalizePreparedApproval(array_merge($fresh, ['author_identity_id' => $this->id(2), 'canonical_record' => str_replace($this->id(4), $this->id(6), $fresh['canonical_record'])])), 'canonical record mismatch');

            $expired = $prepare(4);
            $preparedPath = $root . '/prepared-posts/' . $expired['prepare_token'] . '.json';
            $metadata = json_decode(file_get_contents($preparedPath), true, 512, JSON_THROW_ON_ERROR);
            $metadata['expires_at'] = '2000-01-01T00:00:00Z';
            file_put_contents($preparedPath, json_encode($metadata, JSON_THROW_ON_ERROR));
            $this->rejects(fn () => $writer->finalizePreparedApproval($expired), 'expired');

            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=false');
            $this->rejects(fn () => $writer->finalizePreparedApproval($cross + ['author_identity_id' => $this->id(2)]), 'same username or a root-approved operator');
            $pdo->exec("UPDATE profiles SET username_token = 'new', is_approved = 1 WHERE identity_id = '" . $this->id(6) . "'");
            $this->rejects(fn () => $writer->finalizePreparedApproval($first + ['author_identity_id' => $this->id(2)]), 'same username or a root-approved operator');
            $this->rejects(fn () => $writer->createPreparedPost($cross), 'dedicated finalization endpoint');
            assertSame(2, count(glob($root . '/records/posts/*.txt')));
        });
    }

    public function testInvitationRestrictionUsesIssuerAndRecipientAccount(): void
    {
        $this->withFixture(function (PDO $pdo, string $root, string $db): void {
            mkdir($root . '/records/posts', 0777, true);
            $token = str_repeat('a', 32);
            $hash = 'sha256:' . hash('sha256', $token);
            $expiry = gmdate('Y-m-d\TH:i:s\Z', time() + 3600);
            file_put_contents($root . '/records/posts/issued.txt', "Post-ID: issued\nCreated-At: 2026-01-01T00:00:00Z\nBoard-Tags: identity invitation internal\nThread-ID: boot-2\nParent-ID: boot-2\nAuthor-Identity-ID: " . $this->id(2) . "\n\nInvitation-ID: invite-aaaaaaaaaaaaaaaa\nInvitation-Action: issue\nVerification-Hash: {$hash}\nExpires-At: {$expiry}\n\n");
            $writer = new LocalWriteService($root, $db, $root . '/public', new CanonicalRecordRepository($root));
            $redeem = fn (int $n, string $thread = '') => $writer->prepareInvitationRedemption([
                'recipient_identity_id' => $this->id($n), 'invite_token' => $token,
                'thread_id' => $thread ?: 'boot-' . $n, 'parent_id' => 'boot-' . $n,
            ]);
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=false');
            $this->rejects(fn () => $redeem(6), 'same username or a root-approved operator');
            assertSame('ok', $redeem(4)['status']);
            assertSame('ok', $redeem(5)['status']);
            $this->rejects(fn () => $redeem(4, 'boot-6'), 'bootstrap thread is invalid');
            putenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=true');
            assertSame('ok', $redeem(6)['status']);
            $pdo->exec("UPDATE profiles SET is_approved = 0 WHERE identity_id = '" . $this->id(2) . "'");
            $this->rejects(fn () => $redeem(6), 'Only approved users');
            assertSame(1, count(glob($root . '/records/posts/*.txt')));
        });
    }

    private function rejects(callable $call, string $message = ''): void
    {
        try { $call(); } catch (RuntimeException $exception) {
            if ($message !== '' && !str_contains($exception->getMessage(), $message)) {
                throw new RuntimeException('Expected rejection containing "' . $message . '", got: ' . $exception->getMessage(), 0, $exception);
            }
            return;
        }
        throw new RuntimeException('Expected rejection.');
    }

    private function id(int $n): string { return 'openpgp:' . str_pad((string) $n, 40, '0', STR_PAD_LEFT); }

    private function withFixture(callable $test): void
    {
        $old = getenv('FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL');
        $root = sys_get_temp_dir() . '/approval-policy-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/approval-seeds', 0777, true);
        mkdir($root . '/records/identity', 0777, true);
        $db = $root . '/index.sqlite3';
        try {
            $pdo = new PDO('sqlite:' . $db);
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            foreach (ReadModelSchema::statements() as $sql) $pdo->exec($sql);
            $stmt = $pdo->prepare('INSERT INTO metadata VALUES (?, ?)');
            foreach (array_merge(ReadModelMetadata::expectedSchemaIdentity(), ['repository_root' => $root, 'repository_head' => 'no-git']) as $k => $v) $stmt->execute([$k, $v]);
            file_put_contents($root . '/records/approval-seeds/openpgp-' . substr($this->id(1), 8) . '.txt', 'Approved-Identity-ID: ' . $this->id(1) . "\nSeed-Reason: test\n\nRoot.\n");
            $stmt = $pdo->prepare('INSERT INTO profiles (identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint, bootstrap_post_id, bootstrap_thread_id, public_key, is_approved, approved_by_label) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ([[1,'operator',1], [2,'alice',1], [3,'bob',1], [4,'new',0], [5,'alice',0], [6,'bob',0]] as [$n,$name,$approved]) {
                file_put_contents($root . '/' . \ForumRewrite\Canonical\CanonicalPathResolver::identity(substr($this->id($n), 8)), 'Identity-ID: ' . $this->id($n) . "\n");
                $stmt->execute([$this->id($n), 'key-' . $n, $name, $name, $name, substr($this->id($n),8), 'boot-' . $n, 'boot-' . $n, '', $approved, 'root']);
            }
            $test($pdo, $root, $db);
        } finally {
            putenv($old === false ? 'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL' : 'FORUM_ALLOW_NON_OPERATOR_CROSS_ACCOUNT_APPROVAL=' . $old);
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $item) {
                $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
            }
            rmdir($root);
        }
    }
}
