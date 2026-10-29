<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

use ForumRewrite\Canonical\PostRecordParser;
use RuntimeException;

/** Repository-owned acceptance evidence. Repository imports remain trusted administration. */
final class ApprovalAcceptance
{
    public static function path(string $postId): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $postId) !== 1) {
            throw new RuntimeException('Invalid acceptance post ID.');
        }
        return 'records/approval-acceptances/' . $postId . '.txt';
    }

    public static function encode(string $contents, array $context, string $acceptedAt, string $kind): string
    {
        $post = (new PostRecordParser())->parse($contents);
        return ApprovalReceiptText::encode(['version' => 1, 'post_id' => $post->postId,
            'record_sha256' => hash('sha256', $contents), 'accepted_at' => $acceptedAt,
            'kind' => $kind, 'context' => $context]);
    }

    /** Preferred text first. Call read() before using paths as accepted evidence. */
    public static function existingPaths(string $root, string $postId): array
    {
        $text = self::path($postId);
        return array_values(array_filter([$text, substr($text, 0, -4) . '.json'],
            fn ($path) => file_exists($root . '/' . $path) || is_link($root . '/' . $path)));
    }

    public static function read(string $root, string $postPath): ?array
    {
        $id = basename($postPath, '.txt');
        $paths = self::existingPaths($root, $id);
        if ($paths === []) return null;
        $contents = file_get_contents($root . '/' . $postPath);
        if ($contents === false) throw new RuntimeException('Acceptance grant is missing.');
        $result = null;
        foreach ($paths as $path) {
            $file = $root . '/' . $path;
            if (!is_file($file) || is_link($file) || filesize($file) > 16384) {
                throw new RuntimeException('Invalid approval acceptance file.');
            }
            $bytes = file_get_contents($file);
            if ($bytes === false) throw new RuntimeException('Unable to read approval acceptance.');
            if (str_ends_with($path, '.txt')) {
                $data = ApprovalReceiptText::decode($bytes);
            } else {
                if (!class_exists(LegacyApprovalReceipt::class)) throw new RuntimeException('Legacy approval reader unavailable; convert with a compatible release.');
                $data = LegacyApprovalReceipt::decode($bytes);
            }
            self::validate($data, $contents);
            if ($data['post_id'] !== $id) throw new RuntimeException('Approval receipt filename mismatch.');
            // Order matters for signed review; never silently choose competing evidence.
            if ($result !== null && ApprovalReceiptText::encode($result) !== ApprovalReceiptText::encode($data)) {
                throw new RuntimeException('Conflicting approval acceptance receipts; resolve before continuing.');
            }
            $result = $data;
        }
        return $result;
    }

    public static function grantPath(string $root, string $id): string
    {
        self::path($id);
        $flat = \ForumRewrite\Canonical\CanonicalPathResolver::post($id);
        if (is_file($root . '/' . $flat)) return $flat;
        $post = (new \ForumRewrite\Canonical\CanonicalRecordRepository($root))->loadPost($flat);
        return \ForumRewrite\Canonical\CanonicalPathResolver::datedPost($id, $post->createdAt);
    }

    public static function validate(array $data, string $contents): array
    {
        ApprovalReceiptText::assertShape($data);
        $post = (new PostRecordParser())->parse($contents);
        $context = $data['context'] ?? null;
        if (($data['version'] ?? null) !== 1 || ($data['post_id'] ?? null) !== $post->postId
            || ($data['record_sha256'] ?? null) !== hash('sha256', $contents)
            || !in_array($data['kind'] ?? '', ['direct', 'invitation'], true) || !is_array($context)
            || !is_string($data['accepted_at'] ?? null)
            || !is_int($context['existing_key_count'] ?? null) || $context['existing_key_count'] < 0
            || !is_bool($context['actor_is_operator'] ?? null) || !is_bool($context['cross_account_allowed'] ?? null)
            || ($context['allowed'] ?? null) !== true) {
            throw new RuntimeException('Invalid or mismatched approval acceptance.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $data['accepted_at'], new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d\TH:i:s\Z') !== $data['accepted_at'] || $data['accepted_at'] < $post->createdAt) {
            throw new RuntimeException('Invalid approval acceptance time.');
        }
        foreach (['actor_identity_id', 'target_identity_id'] as $key) {
            if (preg_match('/^openpgp:[a-f0-9]{40}$/D', $context[$key] ?? '') !== 1) throw new RuntimeException('Invalid acceptance identity.');
        }
        foreach (['actor_username', 'target_username', 'target_profile_slug', 'target_thread_id', 'target_post_id'] as $key) {
            if (!is_string($context[$key] ?? null) || $context[$key] === '') throw new RuntimeException('Incomplete acceptance context.');
        }
        $classification = $context['existing_key_count'] === 0 ? 'new_account'
            : ($context['actor_username'] === $context['target_username'] ? 'own_account_key' : 'cross_account_key');
        if (($context['classification'] ?? '') !== $classification
            || (!$context['actor_is_operator'] && !$context['cross_account_allowed'] && $classification === 'cross_account_key')
            || $post->threadId !== $context['target_thread_id'] || $post->parentId !== $context['target_post_id']) {
            throw new RuntimeException('Inconsistent acceptance context.');
        }
        if ($data['kind'] === 'direct') {
            if ($post->authorIdentityId !== $context['actor_identity_id']
                || !in_array('approval', $post->boardTags, true)
                || !preg_match('/^Approve-Identity-ID: ' . preg_quote($context['target_identity_id'], '/') . '$/m', $post->body)) {
                throw new RuntimeException('Acceptance does not match direct grant.');
            }
            if (preg_match('/^Approval-Context-SHA256: ([a-f0-9]{64})$/m', $post->body, $digest)
                && $digest[1] !== hash('sha256', json_encode($context, JSON_THROW_ON_ERROR))) {
                throw new RuntimeException('Acceptance does not match signed review.');
            }
        } elseif ($post->authorIdentityId !== $context['target_identity_id']
            || !in_array('invitation', $post->boardTags, true) || !str_contains($post->body, "Invitation-Action: redeem\n")) {
            throw new RuntimeException('Acceptance does not match invitation grant.');
        }
        return $data;
    }
}
