<?php

declare(strict_types=1);
namespace ForumRewrite\Canonical;

use RuntimeException;

/** Local canonical acceptance, not a cleanup ledger. Imports must validate before storing. */
final class AcceptedRetraction
{
    public static function path(string $id): string
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $id)) throw new RuntimeException('Invalid retraction target.');
        return 'records/post-retractions/post-retraction-' . $id . '.json';
    }

    public static function prepare(PostRecord $post, PostRetractionRecord $request, string $signedRequest, string $signature, string $attribution, ?string $sourceHash = null): array
    {
        if (!RetractedPosts::applies($post, $request)) throw new RuntimeException('Retraction does not authorize this target.');
        return ['version' => 1, 'source_hash' => $sourceHash, 'post_id' => $post->postId, 'accepted_at' => $request->createdAt,
            'actor' => $request->signerIdentityId(), 'classification' => $request->classification,
            'attribution' => $attribution, 'signed_request' => $signedRequest, 'signature' => $signature,
            'post' => ['post_id' => $post->postId, 'thread_id' => $post->threadId ?? $post->postId,
                'parent_id' => $post->parentId, 'created_at' => $post->createdAt,
                'author_identity_id' => $post->authorIdentityId, 'thread_type' => $post->threadType]];
    }

    public static function read(string $root, string $id): ?array
    {
        $path = $root . '/' . self::path($id);
        if (!file_exists($path)) return null;
        if (is_link($path) || !is_file($path) || filesize($path) > 1048576) throw new RuntimeException('Unsafe retraction record.');
        return self::decode((string) file_get_contents($path), $id);
    }

    public static function decode(string $bytes, string $id): array
    {
        $record = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
        $post = $record['post'] ?? [];
        if (($record['version'] ?? null) !== 1 || ($record['post_id'] ?? null) !== $id
            || ($post['post_id'] ?? null) !== $id || !is_string($post['thread_id'] ?? null)
            || !is_string($post['created_at'] ?? null) || !is_string($record['signed_request'] ?? null)
            || !is_string($record['signature'] ?? null)
            || !in_array($record['attribution'] ?? null, ['author', 'operator'], true)) throw new RuntimeException('Invalid retraction record.');
        self::path($post['thread_id']);
        if (($post['parent_id'] ?? null) !== null) self::path($post['parent_id']);
        $request = (new PostRetractionRecordParser())->parse($record['signed_request']);
        if ($request->postId !== $id || $request->authorIdentityId !== ($post['author_identity_id'] ?? null)
            || $request->createdAt !== ($record['accepted_at'] ?? null)
            || $request->signerIdentityId() !== ($record['actor'] ?? null)
            || $request->classification !== ($record['classification'] ?? null)) throw new RuntimeException('Retraction request mismatch.');
        if (array_diff(array_keys($record), ['version', 'source_hash', 'post_id', 'accepted_at', 'actor', 'classification', 'attribution', 'signed_request', 'signature', 'post']) !== []
            || (isset($record['source_hash']) && !preg_match('/^[a-f0-9]{64}$/D', $record['source_hash']))) throw new RuntimeException('Unexpected retraction fields.');
        // Deliberately whitelist structural fields: no prose, titles or cleanup status.
        if (array_diff(array_keys($post), ['post_id', 'thread_id', 'parent_id', 'created_at', 'author_identity_id', 'thread_type']) !== []) throw new RuntimeException('Unexpected retraction metadata.');
        return $record;
    }

    public static function all(string $root): array
    {
        $records = [];
        foreach (glob($root . '/records/post-retractions/post-retraction-*.json') ?: [] as $path) {
            $id = substr(basename($path, '.json'), strlen('post-retraction-'));
            try { $records[$id] = self::read($root, $id); }
            catch (\Throwable) { /* Operator migration/inspection reports malformed records; readers remain available. */ }
        }
        return $records;
    }

    /** Adapter to the existing structural-placeholder shape; no additional persisted record. */
    public static function removals(string $root): array
    {
        $records = [];
        foreach (self::all($root) as $id => $record) {
            $records[$id] = $record + ['operation' => 'delete', 'labels' => [], 'source_id' => $id];
        }
        return $records;
    }

    public static function encode(array $record): string
    {
        $bytes = json_encode($record, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n";
        self::decode($bytes, $record['post_id']);
        return $bytes;
    }
}
