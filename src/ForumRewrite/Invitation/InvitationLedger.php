<?php

declare(strict_types=1);

namespace ForumRewrite\Invitation;

use ForumRewrite\Canonical\InvitationRecord;
use RuntimeException;

final class InvitationLedger
{
    /** @var array<string, array{issued:InvitationRecord,revoked:?InvitationRecord,redeemed:?InvitationRecord}> */
    private array $entries = [];

    /** @param iterable<InvitationRecord> $records */
    public function __construct(iterable $records)
    {
        foreach ($records as $record) {
            $this->apply($record);
        }
    }

    /** @return array{issued:InvitationRecord,revoked:?InvitationRecord,redeemed:?InvitationRecord}|null */
    public function find(string $invitationId): ?array
    {
        return $this->entries[$invitationId] ?? null;
    }

    public function apply(InvitationRecord $record): void
    {
        $entry = $this->entries[$record->invitationId] ?? null;
        if ($record->action === 'issue') {
            if ($entry !== null) {
                throw new RuntimeException('Invitation has already been issued.');
            }
            $this->entries[$record->invitationId] = ['issued' => $record, 'revoked' => null, 'redeemed' => null];
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
            if ($entry['revoked'] !== null || $entry['redeemed'] !== null) {
                throw new RuntimeException('Invitation is no longer revocable.');
            }
            $this->entries[$record->invitationId]['revoked'] = $record;
            return;
        }
        if ($entry['revoked'] !== null || $entry['redeemed'] !== null) {
            throw new RuntimeException('Invitation is no longer redeemable.');
        }
        $this->entries[$record->invitationId]['redeemed'] = $record;
    }
}
