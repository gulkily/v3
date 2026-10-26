<?php

declare(strict_types=1);
namespace ForumRewrite\Canonical;

use RuntimeException;

/** Local canonical acceptance, not a cleanup ledger. Imports must validate before storing. */
final class AcceptedRetraction
{
    public static function path(string $id): string
    {
        return RetractionReceipt::path($id);
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

    public static function requestPath(string $id): string
    {
        return RetractionReceipt::requestPath($id);
    }

    public static function files(array $record): array
    {
        $request = self::requestPath($record['post_id']);
        return [$request => $record['signed_request'], $request . '.asc' => $record['signature'],
            self::path($record['post_id']) => self::encode($record)];
    }

    public static function read(string $root, string $id): ?array
    {
        $path = $root . '/' . self::path($id);
        if (!file_exists($path) && !is_link($path)) return null;
        $request = $root . '/' . self::requestPath($id);
        return self::decode(self::readFile($path), $id, self::readFile($request), self::readFile($request . '.asc'));
    }

    private static function readFile(string $path): string
    {
        if (is_link($path) || is_link(dirname($path)) || !is_file($path) || filesize($path) > 1048576) {
            throw new RuntimeException('Missing or unsafe retraction evidence: ' . basename($path));
        }
        $bytes = file_get_contents($path);
        if ($bytes === false) throw new RuntimeException('Cannot read retraction evidence.');
        return $bytes;
    }

    public static function decode(string $bytes, string $id, string $request, string $signature): array
    {
        return RetractionReceipt::decode($bytes, $id, $request, $signature);
    }

    public static function all(string $root): array
    {
        $records = [];
        foreach (glob($root . '/records/post-retraction-acceptances/post-retraction-*.txt') ?: [] as $path) {
            $id = substr(basename($path, '.txt'), strlen('post-retraction-'));
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
        return RetractionReceipt::encode($record);
    }
}
