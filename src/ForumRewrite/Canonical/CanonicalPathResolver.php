<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class CanonicalPathResolver
{
    public static function post(string $postId): string
    {
        return 'records/posts/' . $postId . '.txt';
    }

    public static function datedPost(string $postId, string $createdAt): string
    {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})T/', $createdAt, $matches) !== 1) {
            return self::post($postId);
        }

        return 'records/posts/' . $matches[1] . '/' . $matches[2] . '/' . $matches[3] . '/' . $postId . '.txt';
    }

    /**
     * @return list<string>
     */
    public static function postCandidates(string $postId): array
    {
        $paths = [];
        if (preg_match('/-(\d{4})(\d{2})(\d{2})\d{6}-/', $postId, $matches) === 1) {
            $paths[] = 'records/posts/' . $matches[1] . '/' . $matches[2] . '/' . $matches[3] . '/' . $postId . '.txt';
        }

        $paths[] = self::post($postId);

        return array_values(array_unique($paths));
    }

    public static function identity(string $lowercaseFingerprint): string
    {
        return 'records/identity/identity-openpgp-' . $lowercaseFingerprint . '.txt';
    }

    public static function publicKey(string $uppercaseFingerprint): string
    {
        return 'records/public-keys/openpgp-' . $uppercaseFingerprint . '.asc';
    }

    public static function approvalSeed(string $lowercaseFingerprint): string
    {
        return 'records/approval-seeds/openpgp-' . $lowercaseFingerprint . '.txt';
    }

    public static function threadLabel(string $recordId): string
    {
        return 'records/thread-labels/' . $recordId . '.txt';
    }

    public static function postReaction(string $recordId): string
    {
        return 'records/post-reactions/' . $recordId . '.txt';
    }

    public static function instancePublic(): string
    {
        return 'records/instance/public.txt';
    }

    public static function featureFlags(): string
    {
        return 'records/instance/feature-flags.txt';
    }
}
