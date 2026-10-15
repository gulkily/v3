<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\ReadModel\ApprovalHistory;
use ForumRewrite\ReadModel\ReadModelSchema;
use ForumRewrite\Security\ApprovalAcceptance;

final class ApprovalHistoryTest
{
    public function testReceiptFactsSurviveCurrentAccountChangesAndPagination(): void
    {
        $workspace = new ImportTestWorkspace();
        $root = $workspace->repository('history');
        $pdo = new PDO('sqlite::memory:');
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        foreach (ReadModelSchema::statements() as $sql) $pdo->exec($sql);
        $insert = $pdo->prepare('INSERT INTO profiles (identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint, bootstrap_post_id, bootstrap_thread_id, public_key, is_approved, approved_by_identity_id, approved_by_label) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?)');
        $actor = 'openpgp:' . str_repeat('a', 40);
        for ($i = 1; $i <= 103; $i++) {
            $id = 'openpgp:' . str_pad(dechex($i), 40, '0', STR_PAD_LEFT);
            $insert->execute([$id, 'key-' . $i, 'bob', 'bob', 'bob', substr($id, 8), 'boot-' . $i, 'boot-' . $i, '', $actor, 'renamed-actor']);
        }
        $target = 'openpgp:' . str_pad('1', 40, '0', STR_PAD_LEFT);
        $body = 'Approve-Identity-ID: ' . $target . "\n";
        $contents = "Post-ID: grant\nCreated-At: 2026-01-01T00:00:00Z\nBoard-Tags: identity approval\nThread-ID: boot-1\nParent-ID: boot-1\nAuthor-Identity-ID: {$actor}\n\n{$body}";
        $workspace->put($root . '/records/posts/grant.txt', $contents);
        $context = ['actor_identity_id' => $actor, 'target_identity_id' => $target, 'actor_username' => 'alice', 'target_username' => 'bob',
            'target_profile_slug' => 'key-1', 'target_thread_id' => 'boot-1', 'target_post_id' => 'boot-1', 'existing_key_count' => 0,
            'classification' => 'new_account', 'actor_is_operator' => false, 'cross_account_allowed' => true, 'allowed' => true];
        $workspace->put($root . '/' . ApprovalAcceptance::path('grant'), ApprovalAcceptance::encode($contents, $context, '2026-01-01T00:05:00Z', 'direct'));
        $post = $pdo->prepare('INSERT INTO posts (post_id, created_at, thread_id, parent_id, body, board_tags_json, author_identity_id, sequence_number) VALUES (?, ?, ?, ?, ?, ?, ?, 1)');
        $post->execute(['grant', '2026-01-01T00:00:00Z', 'boot-1', 'boot-1', $body, '["identity","approval"]', $actor]);
        $history = new ApprovalHistory($pdo, $root);
        $page = $history->forUsername('bob', 2);
        assertSame(103, $page['total']);
        assertSame(2, count($page['rows']));
        assertSame(2, $page['next_offset']);
        assertSame('new_account', $page['rows'][0]['classification']);
        assertSame(0, $page['rows'][0]['existing_key_count']);
        assertSame('alice', $page['rows'][0]['approver_label']);
        assertSame('unknown', $page['rows'][1]['classification']);
        assertSame(null, $page['rows'][1]['accepted_at']);
        assertSame(100, count($history->forUsername('bob', 10000)['rows']));
        assertSame(3, count($history->forUsername('bob', 50, 100)['rows']));
        assertSame(null, $history->forUsername('bob', 50, 100)['next_offset']);
        assertSame(50, $history->forUsername('bob', 50, 100)['previous_offset']);
        $pdo->exec("UPDATE profiles SET username_token='renamed' WHERE identity_id='" . $target . "'");
        assertSame('new_account', $history->forUsername('renamed')['rows'][0]['classification']);
        assertSame(true, $history->forUsername('renamed')['rows'][0]['cross_account_allowed']);
    }
}
