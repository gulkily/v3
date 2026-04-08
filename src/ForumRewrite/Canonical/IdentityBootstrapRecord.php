<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class IdentityBootstrapRecord
{
    public const REQUIRED_HEADERS = [
        'Post-ID',
        'Board-Tags',
        'Subject',
        'Identity-ID',
        'Signer-Fingerprint',
        'Bootstrap-By-Post',
        'Bootstrap-By-Thread',
    ];

    public function __construct(
        public readonly string $postId,
        public readonly string $username,
        public readonly string $identityId,
        public readonly string $signerFingerprint,
        public readonly string $bootstrapByPost,
        public readonly string $bootstrapByThread,
        public readonly string $armoredPublicKey,
    ) {
    }

    public function identitySlug(): string
    {
        return str_replace(':', '-', $this->identityId);
    }
}
