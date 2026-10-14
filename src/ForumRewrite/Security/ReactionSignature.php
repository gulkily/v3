<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

/** Optional signatures on either reaction family; cache keys include every verified byte. */
final class ReactionSignature
{
    private static array $cache = [];

    public static function status(string $root, string $path, string $contents, ?string $author, ?string $keyFallbackRoot = null): string
    {
        $signatures = [];
        foreach (['.asc', '.sig'] as $suffix) {
            if (file_exists($root . '/' . $path . $suffix)) {
                $signatures[] = (string) file_get_contents($root . '/' . $path . $suffix);
            }
        }
        if ($signatures === []) return 'unsigned';
        if ($author === null || !preg_match('/^openpgp:([a-f0-9]{40})$/D', $author, $match)) return 'invalid';
        $fingerprint = strtoupper($match[1]);
        $keyPath = '/records/public-keys/openpgp-' . $fingerprint . '.asc';
        $key = '';
        foreach (array_filter([$root, $keyFallbackRoot]) as $keyRoot) {
            if (is_file($keyRoot . $keyPath)) {
                $key = (string) file_get_contents($keyRoot . $keyPath);
                break;
            }
        }
        if ($key === '') return 'invalid';
        foreach ($signatures as $signature) {
            $cacheKey = hash('sha256', serialize([$contents, $signature, $key, $fingerprint]));
            if (!isset(self::$cache[$cacheKey])) {
                if (count(self::$cache) >= 1024) self::$cache = [];
                self::$cache[$cacheKey] = (new OpenPgpSignatureVerifier())->verifyDetached($key, $contents, $signature, $fingerprint)['ok'];
            }
            if (!self::$cache[$cacheKey]) return 'invalid';
        }
        return 'verified';
    }
}
