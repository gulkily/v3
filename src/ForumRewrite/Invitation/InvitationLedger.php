<?php

declare(strict_types=1);

namespace ForumRewrite\Invitation;

use ForumRewrite\Canonical\InvitationRecord;
use RuntimeException;

final class InvitationLedger
{
    /** @var array<string, array{issued:InvitationRecord,revoked:?InvitationRecord,redeemed:?InvitationRecord,recipients:array<string,InvitationRecord>}> */
    private array $entries = [];

    /** @param iterable<InvitationRecord> $records */
    public function __construct(iterable $records, array $receipts = [])
    {
        foreach ($records as $record) {
            $this->apply($record, $receipts[$record->post->postId] ?? null);
        }
    }

    /** @return array{issued:InvitationRecord,revoked:?InvitationRecord,redeemed:?InvitationRecord,recipients:array<string,InvitationRecord>}|null */
    public function find(string $invitationId): ?array
    {
        return $this->entries[$invitationId] ?? null;
    }

    /** @return array{issued:InvitationRecord,revoked:?InvitationRecord,redeemed:?InvitationRecord,recipients:array<string,InvitationRecord>}|null */
    public function findByVerificationHash(string $verificationHash): ?array
    {
        foreach ($this->entries as $entry) {
            if (hash_equals($entry['issued']->verificationHash, $verificationHash)) {
                return $entry;
            }
        }

        return null;
    }

    public function apply(InvitationRecord $record, ?array $receipt = null): void
    {
        $entry = $this->entries[$record->invitationId] ?? null;
        if ($record->action === 'issue') {
            if ($entry !== null) {
                throw new RuntimeException('Invitation has already been issued.');
            }
            $this->entries[$record->invitationId] = ['issued' => $record, 'revoked' => null, 'redeemed' => null, 'recipients' => []];
            return;
        }
        if ($entry === null) {
            throw new RuntimeException('Invitation action has no issued invitation.');
        }
        if (!hash_equals($entry['issued']->verificationHash, $record->verificationHash)) {
            throw new RuntimeException('Invitation action verification hash does not match issuance.');
        }
        if ($record->action === 'revoke') {
            if ($record->post->authorIdentityId !== $entry['issued']->post->authorIdentityId) {
                throw new RuntimeException('Only the invitation issuer may revoke an invitation.');
            }
            if ($entry['revoked'] !== null) {
                throw new RuntimeException('Invitation is no longer revocable.');
            }
            $this->entries[$record->invitationId]['revoked'] = $record;
            return;
        }
        $recipient = $record->post->authorIdentityId;
        if ($receipt !== null && ($receipt['kind'] !== 'invitation'
            || $receipt['context']['actor_identity_id'] !== $entry['issued']->post->authorIdentityId
            || $receipt['context']['target_identity_id'] !== $recipient)) {
            throw new RuntimeException('Invitation acceptance does not match issuer or recipient.');
        }
        if ($entry['issued']->maxRedemptions > 1 && $receipt === null) {
            throw new RuntimeException('Multi-use invitation acceptance receipt is missing.');
        }
        if (isset($entry['recipients'][$recipient])) return;
        if (count($entry['recipients']) >= $entry['issued']->maxRedemptions) {
            throw new RuntimeException('Invitation is no longer redeemable.');
        }
        // Verified repository acceptance survives later revocation and authored-time ordering.
        if ($receipt === null && $entry['revoked'] !== null) {
            throw new RuntimeException('Invitation is no longer redeemable.');
        }
        $acceptedAt = $receipt['accepted_at'] ?? $record->post->createdAt;
        if (strtotime($acceptedAt) >= strtotime($entry['issued']->expiresAt)) {
            throw new RuntimeException('Invitation has expired.');
        }
        $this->entries[$record->invitationId]['recipients'][$recipient] = $record;
        $this->entries[$record->invitationId]['redeemed'] ??= $record;
    }
}
