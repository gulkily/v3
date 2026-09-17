<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class InvitationRecord
{
    public function __construct(
        public readonly PostRecord $post,
        public readonly string $invitationId,
        public readonly string $action,
        public readonly string $verificationHash,
        public readonly ?string $expiresAt,
        public readonly ?string $destination,
    ) {
    }
}
