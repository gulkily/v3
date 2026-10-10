<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/** Portable legacy dates, bound to exact post bytes; never changes signed text. */
final class LegacyPostTimestamp
{
    public static function path(string $postId): string
    {
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._-]*$/D', $postId) !== 1) {
            throw new CanonicalRecordParseException('Invalid legacy timestamp post ID.');
        }
        return 'records/post-timestamps/' . $postId . '.json';
    }

    public static function read(string $file): array
    {
        if (!is_file($file) || is_link($file) || filesize($file) > 4096) {
            throw new CanonicalRecordParseException('Legacy timestamp metadata is missing or exceeds 4 KiB.');
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data) || count($data) !== 3 || !is_string($data['post_id'] ?? null)
            || !is_string($data['record_sha256'] ?? null) || !is_string($data['created_at'] ?? null)
            || preg_match('/^[a-f0-9]{64}$/D', $data['record_sha256']) !== 1
            || basename(self::path($data['post_id'])) !== basename($file)) {
            throw new CanonicalRecordParseException('Invalid legacy timestamp metadata.');
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s\Z', $data['created_at'], new \DateTimeZone('UTC'));
        if ($date === false || $date->format('Y-m-d\TH:i:s\Z') !== $data['created_at']) {
            throw new CanonicalRecordParseException('Invalid legacy creation timestamp.');
        }
        return $data;
    }

    public static function resolve(string $root, string $postPath, string $contents): ?string
    {
        $id = basename($postPath, '.txt');
        $file = $root . '/' . self::path($id);
        if (!file_exists($file) && !is_link($file)) {
            return null;
        }
        $data = self::read($file);
        if ($data['record_sha256'] !== hash('sha256', $contents)) {
            throw new CanonicalRecordParseException('Legacy timestamp metadata does not match post bytes.');
        }
        return $data['created_at'];
    }

    public static function encode(string $id, string $contents, string $createdAt): string
    {
        return json_encode(['post_id' => $id, 'record_sha256' => hash('sha256', $contents), 'created_at' => $createdAt], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR) . "\n";
    }
}
