<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Security\ApprovalAcceptance;
use ForumRewrite\Invitation\InvitationApprovalReplay;
use ForumRewrite\Import\ArchiveRecordCatalog;

final class ApprovalAcceptanceTest
{
    public function testReceiptBindsExactBytesAndReviewedContext(): void
    {
        $this->fixture(function (string $root, array $context): void {
            $body = 'Approve-Identity-ID: ' . $context['target_identity_id'] . "\nApproval-Context-SHA256: " . hash('sha256', json_encode($context, JSON_THROW_ON_ERROR)) . "\n";
            $contents = $this->post('grant', 'identity approval', $context['actor_identity_id'], $body);
            $path = 'records/posts/grant.txt';
            file_put_contents($root . '/' . $path, $contents);
            $receiptPath = $root . '/' . ApprovalAcceptance::path('grant');
            $bytes = ApprovalAcceptance::encode($contents, $context, '2026-01-01T00:05:00Z', 'direct');
            file_put_contents($receiptPath, $bytes);
            assertSame('2026-01-01T00:05:00Z', ApprovalAcceptance::read($root, $path)['accepted_at']);
            $data = \ForumRewrite\Security\ApprovalReceiptText::decode($bytes);
            $data['context']['existing_key_count'] = 7;
            file_put_contents($receiptPath, \ForumRewrite\Security\ApprovalReceiptText::encode($data));
            $this->rejects(fn () => ApprovalAcceptance::read($root, $path));
            file_put_contents($receiptPath, $bytes);
            file_put_contents($root . '/' . $path, $contents . "Changed.\n");
            $this->rejects(fn () => ApprovalAcceptance::read($root, $path));
            file_put_contents($root . '/' . $path, $contents);
            $data = \ForumRewrite\Security\ApprovalReceiptText::decode($bytes);
            $data['accepted_at'] = '2025-01-01T00:00:00Z';
            file_put_contents($receiptPath, \ForumRewrite\Security\ApprovalReceiptText::encode($data));
            $this->rejects(fn () => ApprovalAcceptance::read($root, $path));
        });
    }

    public function testInvitationReplayUsesAcceptanceTimeAndPreservesLegacyUnknowns(): void
    {
        $this->fixture(function (string $root, array $context): void {
            $prefix = "Invitation-ID: invite-aaaaaaaaaaaaaaaa\nVerification-Hash: sha256:" . str_repeat('a', 64) . "\n";
            $issue = $this->post('issue', 'identity invitation internal', $context['actor_identity_id'], $prefix . "Invitation-Action: issue\nExpires-At: 2026-01-01T00:06:00Z\n");
            $grant = $this->post('redeem', 'identity invitation internal', $context['target_identity_id'], $prefix . "Invitation-Action: redeem\n");
            $rows = [];
            foreach (['issue' => $issue, 'redeem' => $grant] as $id => $contents) {
                $path = 'records/posts/' . $id . '.txt';
                file_put_contents($root . '/' . $path, $contents);
                $post = (new \ForumRewrite\Canonical\PostRecordParser())->parse($contents);
                $rows[] = ['post_id' => $id, 'created_at' => $post->createdAt, 'thread_id' => $post->threadId, 'parent_id' => $post->parentId,
                    'author_identity_id' => $post->authorIdentityId, 'body' => $post->body, 'board_tags_json' => json_encode($post->boardTags), 'source_path' => $path];
            }
            // Both dates are long expired today; the accepted grant must survive replay.
            $path = 'records/posts/redeem.txt';
            $receiptPath = $root . '/' . ApprovalAcceptance::path('redeem');
            file_put_contents($receiptPath, ApprovalAcceptance::encode($grant, $context, '2026-01-01T00:05:00Z', 'invitation'));
            assertSame(1, count(InvitationApprovalReplay::candidates($root, $rows)));
            file_put_contents($receiptPath, ApprovalAcceptance::encode($grant, $context, '2026-01-01T00:07:00Z', 'invitation'));
            assertSame([], InvitationApprovalReplay::candidates($root, $rows));
            unlink($receiptPath);
            assertSame(null, ApprovalAcceptance::read($root, $path));
            assertSame(1, count(InvitationApprovalReplay::candidates($root, $rows)));
        });
    }

    public function testTextPreservesSignedContextOrderAndRejectsMalformedEvidence(): void
    {
        $this->fixture(function (string $root, array $context): void {
            $context['actor_username'] = 'é/"alice';
            foreach ([$context, array_reverse($context, true)] as $ordered) {
                $contents = $this->post('grant', 'approval', $ordered['actor_identity_id'],
                    "Approve-Identity-ID: " . $ordered['target_identity_id'] . "\nApproval-Context-SHA256: " . hash('sha256', json_encode($ordered, JSON_THROW_ON_ERROR)) . "\n");
                $data = \ForumRewrite\Security\ApprovalReceiptText::decode(ApprovalAcceptance::encode($contents, $ordered, '2026-01-01T00:05:00Z', 'direct'));
                $text = \ForumRewrite\Security\ApprovalReceiptText::encode($data);
                $decoded = \ForumRewrite\Security\ApprovalReceiptText::decode($text);
                assertSame($data, ApprovalAcceptance::validate($decoded, $contents));
                assertSame($ordered, $decoded['context']);
                foreach ([$text . "Kind: direct\n", str_replace('Context-Allowed: true', 'Context-Allowed: 1', $text), $text . "Unknown: x\n"] as $bad) {
                    $this->rejects(fn () => \ForumRewrite\Security\ApprovalReceiptText::decode($bad));
                }
                $decoded['context'] = array_reverse($ordered, true);
                $this->rejects(fn () => ApprovalAcceptance::validate($decoded, $contents));
                $legacy = json_encode($data);
                assertSame($data, \ForumRewrite\Security\LegacyApprovalReceipt::decode($legacy));
                $this->rejects(fn () => \ForumRewrite\Security\LegacyApprovalReceipt::decode(str_replace('"version":1', '"version":1,"version":1', $legacy)));
            }
        });
    }

    public function testLegacyAndTextResolutionRejectsConflictingOrInvalidCompanions(): void
    {
        $this->fixture(function (string $root, array $context): void {
            $grant = $this->post('grant', 'approval', $context['actor_identity_id'], "Approve-Identity-ID: " . $context['target_identity_id'] . "\n");
            $path = 'records/posts/grant.txt'; file_put_contents($root . '/' . $path, $grant);
            $text = ApprovalAcceptance::encode($grant, $context, '2026-01-01T00:05:00Z', 'direct');
            $data = \ForumRewrite\Security\ApprovalReceiptText::decode($text);
            $old = $root . '/records/approval-acceptances/grant.json';
            $new = $root . '/' . ApprovalAcceptance::path('grant');
            file_put_contents($old, json_encode($data));
            assertSame($data, ApprovalAcceptance::read($root, $path));
            file_put_contents($new, $text);
            assertSame($data, ApprovalAcceptance::read($root, $path));
            $data['accepted_at'] = '2026-01-01T00:06:00Z'; file_put_contents($old, json_encode($data));
            $this->rejects(fn () => ApprovalAcceptance::read($root, $path));
            file_put_contents($old, '{}');
            $this->rejects(fn () => ApprovalAcceptance::read($root, $path));
        });
    }

    private function post(string $id, string $tags, string $author, string $body): string
    {
        return "Post-ID: {$id}\nCreated-At: 2026-01-01T00:00:00Z\nBoard-Tags: {$tags}\nThread-ID: bootstrap\nParent-ID: bootstrap\nAuthor-Identity-ID: {$author}\n\n{$body}";
    }

    private function rejects(callable $callback): void
    {
        try { $callback(); } catch (Throwable) { return; }
        throw new RuntimeException('Expected invalid acceptance rejection.');
    }

    private function fixture(callable $test): void
    {
        $root = sys_get_temp_dir() . '/acceptance-' . bin2hex(random_bytes(6));
        mkdir($root . '/records/posts', 0700, true);
        mkdir($root . '/records/approval-acceptances', 0700, true);
        $context = ['actor_identity_id' => 'openpgp:' . str_repeat('a', 40), 'target_identity_id' => 'openpgp:' . str_repeat('b', 40),
            'actor_username' => 'alice', 'target_username' => 'bob', 'target_profile_slug' => 'bob-key', 'target_thread_id' => 'bootstrap',
            'target_post_id' => 'bootstrap', 'existing_key_count' => 2, 'classification' => 'cross_account_key',
            'actor_is_operator' => false, 'cross_account_allowed' => true, 'allowed' => true];
        try { $test($root, $context); } finally {
            foreach (glob($root . '/records/*/*') ?: [] as $file) unlink($file);
            rmdir($root . '/records/posts'); rmdir($root . '/records/approval-acceptances'); rmdir($root . '/records'); rmdir($root);
        }
    }
}
