<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/** An operator's signed change to the site tagsets: set them to $text, or reset to the shipped file. */
final class TagsetsChangeRecord
{
    public const SET = 'set';
    public const RESET = 'reset';

    public function __construct(
        public readonly string $recordId,
        public readonly string $createdAt,
        public readonly string $operation,
        public readonly string $operatorIdentityId,
        public readonly string $text,
    ) {
    }
}
