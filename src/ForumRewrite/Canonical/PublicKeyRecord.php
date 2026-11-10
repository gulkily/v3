<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * Parsed fields mirror the record's headers one for one, including ones no reader uses yet; they are kept on purpose so the parser stays a complete view of the canonical format.
 */
final class PublicKeyRecord
{
    public function __construct(
        public readonly string $fingerprint,
        public readonly string $armoredKey,
    ) {
    }
}
