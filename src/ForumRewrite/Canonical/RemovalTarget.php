<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * Names what a retraction request or removal receipt removes.
 *
 * Current files say `Target-ID` (receipts also `Target-Created-At`). Files written before the rename
 * say `Post-ID` / `Post-Created-At` and stay valid as stored. A file uses one form, never both.
 * The target's record family is read from the ID: see self::isReactionId().
 */
final class RemovalTarget
{
    public const ID = 'Target-ID';
    public const CREATED_AT = 'Target-Created-At';
    public const LEGACY_ID = 'Post-ID';
    public const LEGACY_CREATED_AT = 'Post-Created-At';

    /** Record-ID prefixes that belong to reactions; a post may not use them. */
    public const REACTION_PREFIXES = ['post-reaction-', 'thread-label-'];

    /**
     * @param array<string, string> $headers
     * @return array{id: string, created_at: ?string, legacy: bool}
     */
    public static function fromHeaders(array $headers): array
    {
        $current = isset($headers[self::ID]) || isset($headers[self::CREATED_AT]);
        $legacy = isset($headers[self::LEGACY_ID]) || isset($headers[self::LEGACY_CREATED_AT]);
        if ($current && $legacy) {
            throw new CanonicalRecordParseException('Retraction target must use Target-ID or the older Post-ID, not both.');
        }
        $id = $headers[$legacy ? self::LEGACY_ID : self::ID] ?? '';
        if ($id === '') {
            throw new CanonicalRecordParseException('Missing required retraction target header: Target-ID');
        }
        return ['id' => $id, 'created_at' => $headers[$legacy ? self::LEGACY_CREATED_AT : self::CREATED_AT] ?? null, 'legacy' => $legacy];
    }

    public static function isReactionId(string $id): bool
    {
        foreach (self::REACTION_PREFIXES as $prefix) {
            if (str_starts_with($id, $prefix)) return true;
        }
        return false;
    }
}
