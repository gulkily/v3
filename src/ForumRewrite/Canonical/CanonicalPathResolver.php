<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class CanonicalPathResolver
{
    public static function post(string $postId): string
    {
        return 'records/posts/' . $postId . '.txt';
    }

    public static function identity(string $lowercaseFingerprint): string
    {
        return 'records/identity/identity-openpgp-' . $lowercaseFingerprint . '.txt';
    }

    public static function publicKey(string $uppercaseFingerprint): string
    {
        return 'records/public-keys/openpgp-' . $uppercaseFingerprint . '.asc';
    }

    public static function instancePublic(): string
    {
        return 'records/instance/public.txt';
    }
}
