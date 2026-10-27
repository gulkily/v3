<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Host\AssetFingerprint;

/**
 * Stage 3 (#6): AssetFingerprint memoizes the per-file hash within a request.
 *
 * Direct call-counting is not possible (assetHash is private), so memoization is
 * proven behaviorally: once a path is hashed, changing the file's *contents* on
 * disk does not change the fingerprint within the same process (the cached hash
 * is reused); after resetHashCache(), the new contents produce a new fingerprint.
 * This exactly characterizes "each asset hashed at most once per request" while
 * guaranteeing byte-identical URLs for unchanged files.
 */
final class AssetFingerprintTest
{
    public function testFingerprintIsMemoizedPerRequestAndRefreshesAfterReset(): void
    {
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-asset-fp-' . bin2hex(random_bytes(6));
        mkdir($publicRoot . '/assets', 0777, true);
        $assetFile = $publicRoot . '/assets/site.css';
        file_put_contents($assetFile, "a{}\n");

        try {
            AssetFingerprint::resetHashCache();

            $first = AssetFingerprint::fingerprintedPath($publicRoot, '/assets/site.css');
            // Fingerprinted form: /assets/site.<12hex>.css
            assertSame(1, preg_match('#^/assets/site\.[a-f0-9]{12}\.css$#', $first), 'expected a fingerprinted path, got ' . $first);

            // Repeat references in the same request return the identical URL.
            assertSame($first, AssetFingerprint::fingerprintedPath($publicRoot, '/assets/site.css'));
            assertSame($first, AssetFingerprint::fingerprintedPath($publicRoot, '/assets/site.css'));

            // Change the file's contents WITHOUT resetting: the memo must still
            // serve the original hash (proving the file was not re-hashed).
            file_put_contents($assetFile, "a{color:red}\n/* changed */\n");
            assertSame($first, AssetFingerprint::fingerprintedPath($publicRoot, '/assets/site.css'));

            // After reset, the new contents yield a different fingerprint.
            AssetFingerprint::resetHashCache();
            $second = AssetFingerprint::fingerprintedPath($publicRoot, '/assets/site.css');
            assertSame(1, preg_match('#^/assets/site\.[a-f0-9]{12}\.css$#', $second));
            assertSame(true, $first !== $second, 'fingerprint should change after contents change + cache reset');
        } finally {
            AssetFingerprint::resetHashCache();
            @unlink($publicRoot . '/assets/site.css');
            @rmdir($publicRoot . '/assets');
            @rmdir($publicRoot);
        }
    }

    public function testNonAssetAndMissingPathsAreUnchangedAndUncached(): void
    {
        $publicRoot = sys_get_temp_dir() . '/forum-rewrite-asset-fp-' . bin2hex(random_bytes(6));
        mkdir($publicRoot . '/assets', 0777, true);

        try {
            AssetFingerprint::resetHashCache();
            // Non-/assets path is returned unchanged.
            assertSame('/favicon.ico', AssetFingerprint::fingerprintedPath($publicRoot, '/favicon.ico'));
            // Missing asset falls through to the original request path.
            assertSame('/assets/missing.css', AssetFingerprint::fingerprintedPath($publicRoot, '/assets/missing.css'));
        } finally {
            AssetFingerprint::resetHashCache();
            @rmdir($publicRoot . '/assets');
            @rmdir($publicRoot);
        }
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                ($message !== '' ? $message . ': ' : '')
                . 'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
                . '.'
            );
        }
    }
}
