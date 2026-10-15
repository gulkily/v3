<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class AgentResponseMetadata
{
    public const SCRIPTURE = 'scripture_oriented_analysis';
    public const EXPANSION = 'scripture_expansion';

    public static function decode(?string $json, ?string $parentId): ?array
    {
        if ($json === null || $json === '') { return null; }
        try { $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new CanonicalRecordParseException('Invalid Agent-Response JSON.'); }
        if (!is_array($data) || !in_array($data['type'] ?? '', [self::SCRIPTURE, self::EXPANSION], true)) {
            throw new CanonicalRecordParseException('Unsupported Agent-Response type.');
        }
        foreach (['source_post_id'] as $field) {
            if (!is_string($data[$field] ?? null) || !preg_match('/^[A-Za-z0-9._:-]+$/D', $data[$field])) {
                throw new CanonicalRecordParseException('Invalid Agent-Response ' . $field);
            }
        }
        foreach (['source_hash'] as $field) {
            if (!is_string($data[$field] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $data[$field])) {
                throw new CanonicalRecordParseException('Invalid Agent-Response ' . $field);
            }
        }
        if ($parentId === null) { throw new CanonicalRecordParseException('Agent responses must be replies.'); }
        if ($data['type'] === self::EXPANSION) {
            if (($data['expansion_of'] ?? null) !== $parentId
                || !is_string($data['initial_hash'] ?? null) || !preg_match('/^[a-f0-9]{64}$/D', $data['initial_hash'])) {
                throw new CanonicalRecordParseException('Expansion must identify its parent and version.');
            }
        } elseif ($data['source_post_id'] !== $parentId) {
            throw new CanonicalRecordParseException('Scripture response must identify its source parent.');
        }
        return $data;
    }

    public static function postHash(array $post): string
    {
        return hash('sha256', json_encode([(string) $post['post_id'], (string) ($post['subject'] ?? ''), (string) $post['body']], JSON_THROW_ON_ERROR));
    }

    public static function expansionId(string $initialReplyId): string
    {
        return 'reply-expansion-' . hash('sha256', $initialReplyId);
    }
}
