<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class ApprovalSeedRecordParser
{
    public function __construct(
        private readonly GenericTextRecordParser $parser = new GenericTextRecordParser(),
    ) {
    }

    public function parse(string $contents): ApprovalSeedRecord
    {
        $record = $this->parser->parse($contents);
        $approvedIdentityId = $record->headers['Approved-Identity-ID'] ?? null;
        $seedReason = $record->headers['Seed-Reason'] ?? null;

        if ($approvedIdentityId === null || $seedReason === null) {
            throw new CanonicalRecordParseException('Approval seed record requires Approved-Identity-ID and Seed-Reason.');
        }

        if (!preg_match('/^openpgp:[a-f0-9]{40}$/', $approvedIdentityId)) {
            throw new CanonicalRecordParseException('Approved-Identity-ID must use the retained lowercase OpenPGP identity form.');
        }

        return new ApprovalSeedRecord($approvedIdentityId, $seedReason, $record->body);
    }
}
