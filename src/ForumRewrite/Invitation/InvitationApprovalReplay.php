<?php

declare(strict_types=1);

namespace ForumRewrite\Invitation;

use ForumRewrite\Canonical\InvitationRecordParser;
use ForumRewrite\Security\ApprovalAcceptance;
use RuntimeException;

final class InvitationApprovalReplay
{
    public static function candidates(string $root, array $posts): array
    {
        $ledger = new InvitationLedger([]);
        $parser = new InvitationRecordParser();
        $candidates = [];
        foreach (InvitationReplayOrder::sortPosts($posts) as $post) {
            $tags = json_decode($post['board_tags_json'], true);
            if (!is_array($tags) || !in_array('invitation', $tags, true)) continue;
            $contents = "Post-ID: {$post['post_id']}\nCreated-At: {$post['created_at']}\n"
                . 'Board-Tags: ' . implode(' ', $tags) . "\nThread-ID: {$post['thread_id']}\n"
                . "Parent-ID: {$post['parent_id']}\nAuthor-Identity-ID: {$post['author_identity_id']}\n\n{$post['body']}";
            $receipt = isset($post['source_path']) ? ApprovalAcceptance::read($root, $post['source_path']) : null;
            try {
                $record = $parser->parse($contents);
                $before = $ledger->find($record->invitationId);
                $ledger->apply($record, $receipt);
            } catch (RuntimeException $error) {
                // Retain historical invalid-record filtering; contradictory accepted evidence fails closed.
                if (($receipt !== null && $error->getMessage() !== 'Invitation has expired.')
                    || $error->getMessage() === 'Multi-use invitation acceptance receipt is missing.') throw $error;
                continue;
            }
            if ($record->action !== 'redeem' || isset($before['recipients'][$post['author_identity_id']])) continue;
            $entry = $ledger->find($record->invitationId);
            $candidates[] = ['target_identity_id' => $post['author_identity_id'],
                'approver_identity_id' => $entry['issued']->post->authorIdentityId,
                'thread_id' => $post['thread_id'], 'parent_id' => $post['parent_id'], 'post_id' => $post['post_id'],
                'source_path' => $post['source_path'] ?? null];
        }
        return $candidates;
    }
}
