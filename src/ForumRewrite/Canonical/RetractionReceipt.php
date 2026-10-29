<?php

declare(strict_types=1);
namespace ForumRewrite\Canonical;

use RuntimeException;

/** Readable local acceptance. Hash bindings are integrity checks, not imported authority. */
final class RetractionReceipt
{
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
        $bytes = RemovalReceipt::encode(['Format'=>'removal-acceptance-v1', 'Post-ID'=>$record['post_id'],
            'Operation'=>'delete', 'Authority'=>'signed-retraction', 'Accepted-At'=>$record['accepted_at'],
            'Actor'=>$record['actor'], 'Source-SHA256'=>$record['source_hash'] ?? '', 'Thread-ID'=>$post['thread_id'],
            'Parent-ID'=>$post['parent_id'] ?? '', 'Post-Created-At'=>$post['created_at'],
            'Author-Identity-ID'=>$post['author_identity_id'], 'Thread-Type'=>$post['thread_type'] ?? '',
            'Retraction-Class'=>$record['classification'], 'Retraction-Attribution'=>$record['attribution'],
            'Request-SHA256'=>hash('sha256', $record['signed_request']), 'Signature-SHA256'=>hash('sha256', $record['signature'])]);
        self::decode($bytes, $record['post_id'], $record['signed_request'], $record['signature']);
        return $bytes;
    }

    public static function decode(string $bytes, string $id, string $request, string $signature): array
    {
        $decoded = RemovalReceipt::decode($bytes); $h = $decoded['headers'];
        if ($h['Authority'] !== 'signed-retraction' || $h['Post-ID'] !== $id || $decoded['labels'] !== []) {
            throw new RuntimeException('Invalid signed retraction receipt.');
        }
        if ($signature === '' || !hash_equals($h['Request-SHA256'], hash('sha256', $request))
            || !hash_equals($h['Signature-SHA256'], hash('sha256', $signature))) throw new RuntimeException('Retraction evidence hash mismatch.');
        $signed = (new PostRetractionRecordParser())->parse($request);
        if ($signed->postId !== $id || $signed->createdAt !== $h['Accepted-At']
            || $signed->authorIdentityId !== $h['Author-Identity-ID'] || $signed->signerIdentityId() !== $h['Actor']
            || $signed->classification !== $h['Retraction-Class']) throw new RuntimeException('Retraction request mismatch.');
        return ['version' => 1, 'source_hash' => $h['Source-SHA256'] ?: null, 'post_id' => $id, 'accepted_at' => $h['Accepted-At'],
            'actor' => $h['Actor'], 'classification' => $h['Retraction-Class'], 'attribution' => $h['Retraction-Attribution'],
            'signed_request' => $request, 'signature' => $signature,
            'post' => ['post_id' => $id, 'thread_id' => $h['Thread-ID'], 'parent_id' => $h['Parent-ID'] ?: null,
                'created_at' => $h['Post-Created-At'], 'author_identity_id' => $h['Author-Identity-ID'], 'thread_type' => $h['Thread-Type'] ?: null]];
    }

    private static function id(string $id): void
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $id)) throw new RuntimeException('Invalid retraction identifier.');
    }
}
