<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

/** Optional signatures on either reaction family; cache keys include every verified byte. */
final class ReactionSignature
{
    private static array $cache = [];
    /** Shared so each signer key is imported into GPG once per process, not once per record. */
    private static ?OpenPgpSignatureVerifier $verifier = null;

    public static function status(string $root, string $path, string $contents, ?string $author, ?string $keyFallbackRoot = null): string
    {
        $signatures = [];
        foreach (['.asc', '.sig'] as $suffix) {
            if (file_exists($root . '/' . $path . $suffix)) {
                $signatures[] = \ForumRewrite\Canonical\RecordLimits::read($root . '/' . $path . $suffix);
            }
        }
        if ($signatures === []) return 'unsigned';
        if ($author === null || !preg_match('/^openpgp:([a-f0-9]{40})$/D', $author, $match)) return 'invalid';
        $fingerprint = strtoupper($match[1]);
        $keyPath = '/records/public-keys/openpgp-' . $fingerprint . '.asc';
        $key = '';
        foreach (array_filter([$root, $keyFallbackRoot]) as $keyRoot) {
            if (is_file($keyRoot . $keyPath)) {
                $key = \ForumRewrite\Canonical\RecordLimits::read($keyRoot . $keyPath);
                break;
            }
        }
        if ($key === '') return 'invalid';
        foreach ($signatures as $signature) {
            $cacheKey = hash('sha256', serialize([$contents, $signature, $key, $fingerprint]));
            if (!isset(self::$cache[$cacheKey])) {
                if (count(self::$cache) >= 1024) self::$cache = [];
                $saved = SignatureCheckCache::has($cacheKey);
                if (!$saved) {
                    try {
                        $saved = (self::$verifier ??= new OpenPgpSignatureVerifier(reuseKeyHomes: true))->verifyDetached($key, $contents, $signature, $fingerprint)['ok'];
                    } catch (\ForumRewrite\Support\OperationLimitException $error) {
                        throw new \ForumRewrite\Support\OperationLimitException('Verification for ' . $path . ': ' . $error->getMessage(), 0, $error);
                    }
                    if ($saved) SignatureCheckCache::remember($cacheKey);
                }
                self::$cache[$cacheKey] = $saved;
            }
            if (!self::$cache[$cacheKey]) return 'invalid';
        }
        return 'verified';
    }

    /**
     * After a writer has verified a signature itself, let status() reuse that check instead of
     * running gpg again on the same bytes. Remembers only when the inputs are exactly those
     * status() would verify: the stored `.asc` beside the record, and the stored public key with
     * the same armored bytes and fingerprint the writer used. Anything else remembers nothing.
     */
    public static function rememberVerified(string $root, string $path, string $contents, string $signature,
        string $author, string $verifiedKey, string $verifiedFingerprint): void
    {
        if (!preg_match('/^openpgp:([a-f0-9]{40})$/D', $author, $match)) return;
        $fingerprint = strtoupper($match[1]);
        if (strtoupper($verifiedFingerprint) !== $fingerprint) return;
        $keyFile = $root . '/records/public-keys/openpgp-' . $fingerprint . '.asc';
        $signatureFile = $root . '/' . $path . '.asc';
        if (!is_file($keyFile) || !is_file($signatureFile) || file_exists($root . '/' . $path . '.sig')) return;
        $key = \ForumRewrite\Canonical\RecordLimits::read($keyFile);
        if ($key !== $verifiedKey || \ForumRewrite\Canonical\RecordLimits::read($signatureFile) !== $signature) return;
        $cacheKey = hash('sha256', serialize([$contents, $signature, $key, $fingerprint]));
        if (count(self::$cache) >= 1024) self::$cache = [];
        self::$cache[$cacheKey] = true;
        SignatureCheckCache::remember($cacheKey);
    }
}
