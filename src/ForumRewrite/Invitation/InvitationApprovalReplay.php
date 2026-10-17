<?php

declare(strict_types=1);

namespace ForumRewrite\Invitation;

use ForumRewrite\Security\ApprovalAcceptance;
use RuntimeException;

final class InvitationApprovalReplay
{
    public static function candidates(string $root, array $posts): array
    {
        $issued = [];
        $candidates = [];
        foreach (InvitationReplayOrder::sortPosts($posts) as $post) {
            $tags = json_decode($post['board_tags_json'], true);
            if (!is_array($tags) || !in_array('invitation', $tags, true)) continue;
            if (preg_match('/^Invitation-ID: (invite-[a-z0-9]{16,64})$/m', $post['body'], $id) !== 1
                || preg_match('/^Invitation-Action: (issue|revoke|redeem)$/m', $post['body'], $action) !== 1
                || preg_match('/^Verification-Hash: (sha256:[a-f0-9]{64})$/m', $post['body'], $hash) !== 1) continue;
            $key = $id[1];
            if ($action[1] === 'issue' && $post['author_identity_id'] !== null
                && !isset($issued[$key]) && preg_match('/^Expires-At: (.+)$/m', $post['body'], $expires) === 1) {
                $issued[$key] = ['hash' => $hash[1], 'issuer' => $post['author_identity_id'], 'expires' => $expires[1], 'revoked' => false, 'redeemed' => false];
                continue;
            }
            if (!isset($issued[$key]) || !hash_equals($issued[$key]['hash'], $hash[1])) continue;
            if ($action[1] === 'revoke' && $post['author_identity_id'] === $issued[$key]['issuer'] && !$issued[$key]['redeemed']) {
                $issued[$key]['revoked'] = true;
                continue;
            }
            if ($action[1] !== 'redeem' || $issued[$key]['redeemed'] || $post['author_identity_id'] === null) continue;
            $receipt = isset($post['source_path']) ? ApprovalAcceptance::read($root, $post['source_path']) : null;
            if ($receipt !== null && ($receipt['kind'] !== 'invitation'
                || $receipt['context']['actor_identity_id'] !== $issued[$key]['issuer'])) {
                throw new RuntimeException('Invitation acceptance does not match issuer.');
            }
            // A receipt records acceptance before any later revocation; revocation is prospective.
            if ($receipt === null && $issued[$key]['revoked']) continue;
            // Legacy creation time is replay evidence only, never claimed as acceptance time.
            $acceptedAt = $receipt['accepted_at'] ?? $post['created_at'];
            if (strtotime($issued[$key]['expires']) === false || strtotime($acceptedAt) === false
                || strtotime($issued[$key]['expires']) <= strtotime($acceptedAt)) continue;
            $issued[$key]['redeemed'] = true;
            $candidates[] = ['target_identity_id' => $post['author_identity_id'], 'approver_identity_id' => $issued[$key]['issuer'],
                'thread_id' => $post['thread_id'], 'parent_id' => $post['parent_id'], 'post_id' => $post['post_id'],
                'source_path' => $post['source_path'] ?? null];
        }
        return $candidates;
    }
}
