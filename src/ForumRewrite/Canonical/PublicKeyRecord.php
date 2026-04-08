<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class PublicKeyRecord
{
    public function __construct(
        public readonly string $fingerprint,
        public readonly string $armoredKey,
    ) {
    }
}
