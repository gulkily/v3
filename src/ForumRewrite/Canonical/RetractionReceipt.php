<?php

declare(strict_types=1);
namespace ForumRewrite\Canonical;

use RuntimeException;

/** Readable local acceptance. Hash bindings are integrity checks, not imported authority. */
final class RetractionReceipt
{
    private const FIELDS = [
        'Version', 'Record-ID', 'Post-ID', 'Accepted-At', 'Actor-Identity-ID',
        'Retraction-Class', 'Retraction-Attribution', 'Source-SHA256',
        'Request-SHA256', 'Signature-SHA256', 'Thread-ID', 'Parent-ID',
        'Post-Created-At', 'Author-Identity-ID', 'Thread-Type',
    ];

    public static function path(string $id): string
    {
        self::id($id);
        return 'records/post-retraction-acceptances/post-retraction-' . $id . '.txt';
    }

    public static function requestPath(string $id): string
    {
        self::id($id);
        return CanonicalPathResolver::postRetraction('post-retraction-' . $id);
    }

    public static function encode(array $record): string
    {
        if (array_diff(array_keys($record), ['version', 'source_hash', 'post_id', 'accepted_at', 'actor', 'classification', 'attribution', 'signed_request', 'signature', 'post'])) {
            throw new RuntimeException('Unexpected retraction fields.');
        }
        $post = $record['post'];
        if (array_diff(array_keys($post), ['post_id', 'thread_id', 'parent_id', 'created_at', 'author_identity_id', 'thread_type'])) {
            throw new RuntimeException('Unexpected retraction metadata.');
        }
        if ($post['post_id'] !== $record['post_id'] || $record['version'] !== 1) throw new RuntimeException('Invalid retraction record.');
        $values = ['1', 'post-retraction-' . $record['post_id'], $record['post_id'], $record['accepted_at'],
            $record['actor'], $record['classification'], $record['attribution'], $record['source_hash'] ?? '',
            hash('sha256', $record['signed_request']), hash('sha256', $record['signature']),
            $post['thread_id'], $post['parent_id'] ?? '', $post['created_at'], $post['author_identity_id'], $post['thread_type'] ?? ''];
        $bytes = '';
        foreach (array_combine(self::FIELDS, $values) as $key => $value) {
            if (!is_string($value) || strpbrk($value, "\r\n") !== false) throw new RuntimeException('Invalid receipt value.');
            $bytes .= $key . ': ' . $value . "\n";
        }
        $bytes .= "\n";
        self::decode($bytes, $record['post_id'], $record['signed_request'], $record['signature']);
        return $bytes;
    }

    public static function decode(string $bytes, string $id, string $request, string $signature): array
    {
        $text = (new GenericTextRecordParser())->parse($bytes);
        $h = $text->headers;
        if ($text->body !== '' || array_diff(array_keys($h), self::FIELDS) || array_diff(self::FIELDS, array_keys($h))) throw new RuntimeException('Invalid receipt headers or body.');
        self::id($id); self::id($h['Thread-ID']);
        if ($h['Parent-ID'] !== '') self::id($h['Parent-ID']);
        if ($h['Version'] !== '1' || $h['Record-ID'] !== 'post-retraction-' . $id || $h['Post-ID'] !== $id
            || !in_array($h['Retraction-Attribution'], ['author', 'operator'], true)
            || !preg_match('/^openpgp:[a-f0-9]{40}$/D', $h['Author-Identity-ID'])
            || ($h['Source-SHA256'] !== '' && !preg_match('/^[a-f0-9]{64}$/D', $h['Source-SHA256']))
            || ($h['Thread-Type'] !== '' && !preg_match('/^[a-z][a-z0-9-]*$/D', $h['Thread-Type']))) throw new RuntimeException('Invalid receipt metadata.');
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $h['Post-Created-At'], new \DateTimeZone('UTC'));
        if (!$date || $date->format('Y-m-d\TH:i:s\Z') !== $h['Post-Created-At']) throw new RuntimeException('Invalid post timestamp.');
        if ($signature === '' || !hash_equals($h['Request-SHA256'], hash('sha256', $request))
            || !hash_equals($h['Signature-SHA256'], hash('sha256', $signature))) throw new RuntimeException('Retraction evidence hash mismatch.');
        $signed = (new PostRetractionRecordParser())->parse($request);
        if ($signed->postId !== $id || $signed->createdAt !== $h['Accepted-At']
            || $signed->authorIdentityId !== $h['Author-Identity-ID'] || $signed->signerIdentityId() !== $h['Actor-Identity-ID']
            || $signed->classification !== $h['Retraction-Class']) throw new RuntimeException('Retraction request mismatch.');
        return ['version' => 1, 'source_hash' => $h['Source-SHA256'] ?: null, 'post_id' => $id, 'accepted_at' => $h['Accepted-At'],
            'actor' => $h['Actor-Identity-ID'], 'classification' => $h['Retraction-Class'], 'attribution' => $h['Retraction-Attribution'],
            'signed_request' => $request, 'signature' => $signature,
            'post' => ['post_id' => $id, 'thread_id' => $h['Thread-ID'], 'parent_id' => $h['Parent-ID'] ?: null,
                'created_at' => $h['Post-Created-At'], 'author_identity_id' => $h['Author-Identity-ID'], 'thread_type' => $h['Thread-Type'] ?: null]];
    }

    private static function id(string $id): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $id)) throw new RuntimeException('Invalid retraction identifier.');
    }
}
