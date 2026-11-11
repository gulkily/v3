<?php

declare(strict_types=1);

namespace ForumRewrite\Canonical;

/**
 * When the server received a signed record whose Created-At was far from the
 * server clock. The time lives beside the record (`<record>.txt.received`)
 * because the signed bytes cannot change; sorting uses it in place of
 * Created-At. Records received in time have no receipt.
 */
final class RecordReceipt
{
    public const SUFFIX = '.received';

    public static function isSkewed(string $createdAt, int $now, int $window): bool
    {
        $created = strtotime($createdAt);
        return $created === false || abs($now - $created) > $window;
    }

    public static function contents(int $now): string
    {
        return 'Received-At: ' . gmdate('Y-m-d\TH:i:s\Z', $now) . "\n";
    }

    /** The receipt already on disk for this record, or a new one for `$now`. */
    public static function forWrite(string $repositoryRoot, string $recordPath, int $now): string
    {
        $existing = @file_get_contents($repositoryRoot . '/' . $recordPath . self::SUFFIX);
        return is_string($existing) && self::parse($existing) !== null ? $existing : self::contents($now);
    }

    public static function effectiveCreatedAt(string $repositoryRoot, string $recordPath, string $createdAt): string
    {
        $path = $repositoryRoot . '/' . $recordPath . self::SUFFIX;
        if (!is_file($path)) {
            return $createdAt;
        }
        return self::parse((string) file_get_contents($path)) ?? $createdAt;
    }

    public static function parse(string $contents): ?string
    {
        return preg_match('/\AReceived-At: (\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z)\n\z/', $contents, $matches) === 1
            ? $matches[1]
            : null;
    }
}
