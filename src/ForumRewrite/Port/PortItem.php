<?php

declare(strict_types=1);

namespace ForumRewrite\Port;

/** One story or comment in a port file. */
final class PortItem
{
    public function __construct(
        public readonly string $id,
        public readonly ?string $parent,
        public readonly string $author,
        public readonly string $createdAt,
        public readonly string $title,
        public readonly string $link,
        public readonly string $text,
    ) {
    }
}
