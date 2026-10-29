<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

final class AgentResponseMetadata
{
    public const SCRIPTURE = 'scripture_oriented_analysis';
    public const EXPANSION = 'scripture_expansion';

    /** Machine JSON decoder; independent of legacy canonical storage. */
    public static function decode(?string $json, ?string $parentId): ?array
    {
        if ($json === null || $json === '') { return null; }
        try { $data = json_decode($json, true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable) { throw new CanonicalRecordParseException('Invalid Agent-Response JSON.'); }
        return self::validate($data, $parentId);
    }

    public static function validate(mixed $data, ?string $parentId): array
    {
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

    private const HEADERS = [
        'Agent-Response-Type' => 'type',
        'Agent-Response-Source-Post-ID' => 'source_post_id',
        'Agent-Response-Source-SHA256' => 'source_hash',
        'Agent-Response-Expansion-Of' => 'expansion_of',
        'Agent-Response-Initial-SHA256' => 'initial_hash',
    ];

    public static function fromHeaders(array $headers, ?string $parentId): ?array
    {
        $named = [];
        foreach ($headers as $name => $value) {
            if (!str_starts_with($name, 'Agent-Response-')) continue;
            if (!isset(self::HEADERS[$name])) throw new CanonicalRecordParseException('Unknown agent response header: ' . $name);
            $named[$name] = $value;
        }
        if (array_key_exists('Agent-Response', $headers)) {
            if ($named !== []) throw new CanonicalRecordParseException('Mixed legacy and named agent response metadata.');
            if (!class_exists(LegacyAgentResponseMetadata::class)) throw new CanonicalRecordParseException('Legacy agent response reader unavailable; retain a compatible reader for this signed record.');
            return LegacyAgentResponseMetadata::decode($headers['Agent-Response'], $parentId);
        }
        if ($named === []) return null;
        $data = [];
        foreach (self::HEADERS as $header => $key) if (array_key_exists($header, $named)) $data[$key] = $named[$header];
        self::assertNamedFields($data);
        return self::validate($data, $parentId);
    }

    public static function toHeaders(array $data): string
    {
        self::assertNamedFields($data);
        self::validate($data, $data['type'] === self::EXPANSION ? $data['expansion_of'] : $data['source_post_id']);
        $text = '';
        foreach (self::HEADERS as $header => $key) {
            if (array_key_exists($key, $data)) {
                if (!is_string($data[$key]) || preg_match('/[\r\n\x00]/', $data[$key])) throw new CanonicalRecordParseException('Invalid agent response header value.');
                $text .= $header . ': ' . $data[$key] . "\n";
            }
        }
        return $text;
    }

    private static function assertNamedFields(array $data): void
    {
        $expected = ['type', 'source_post_id', 'source_hash'];
        if (($data['type'] ?? '') === self::EXPANSION) $expected = [...$expected, 'expansion_of', 'initial_hash'];
        $keys = array_keys($data); sort($keys); sort($expected);
        if ($keys !== $expected) throw new CanonicalRecordParseException('Incomplete or unexpected named agent response metadata.');
    }

    public static function postHash(array $post): string
    {
        return hash('sha256', json_encode([(string) $post['post_id'], (string) ($post['subject'] ?? ''), (string) $post['body']], JSON_THROW_ON_ERROR));
    }

    public static function validExpansion(array $expansion, ?array $initial, ?array $source): bool
    {
        if ($initial === null || $source === null) { return false; }
        foreach ([$expansion, $initial, $source] as $post) {
            if (!empty($post['is_hidden']) || ($post['retracted_at'] ?? $post['source_retracted_at'] ?? null) !== null) { return false; }
        }
        if (($expansion['author_label'] ?? '') !== 'reply-agent' || ($initial['author_label'] ?? '') !== 'reply-agent'
            || empty($initial['author_identity_id']) || $initial['author_identity_id'] !== ($expansion['author_identity_id'] ?? null)) { return false; }
        try {
            $meta = self::decode($expansion['agent_response_json'] ?? null, $expansion['parent_id'] ?? null);
            return ($meta['type'] ?? '') === self::EXPANSION && $meta['expansion_of'] === $initial['post_id']
                && $initial['parent_id'] === $source['post_id'] && $meta['source_post_id'] === $source['post_id']
                && $initial['thread_id'] === $source['thread_id'] && $expansion['thread_id'] === $source['thread_id']
                && $meta['initial_hash'] === self::postHash($initial) && $meta['source_hash'] === self::postHash($source);
        } catch (CanonicalRecordParseException) { return false; }
    }

    public static function expansionId(string $initialReplyId): string
    {
        return 'reply-expansion-' . hash('sha256', $initialReplyId);
    }
}
