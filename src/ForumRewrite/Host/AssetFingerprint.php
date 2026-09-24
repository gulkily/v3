<?php

declare(strict_types=1);

namespace ForumRewrite\Host;

final class AssetFingerprint
{
    private const HASH_LENGTH = 12;

    public static function fingerprintedPath(string $publicRoot, string $path): string
    {
        if (!str_starts_with($path, '/assets/')) {
            return $path;
        }

        $assetPath = $publicRoot . $path;
        if (!is_file($assetPath)) {
            return $path;
        }

        $hash = self::assetHash($assetPath);
        if ($hash === null) {
            return $path;
        }

        $extensionOffset = strrpos($path, '.');
        if ($extensionOffset === false || $extensionOffset <= strlen('/assets/')) {
            return $path;
        }

        return substr($path, 0, $extensionOffset)
            . '.'
            . $hash
            . substr($path, $extensionOffset);
    }

    public static function sourcePathForFingerprint(string $publicRoot, string $path): ?string
    {
        $components = self::fingerprintComponents($path);
        if ($components === null || count($components['hashes']) !== 1) {
            return null;
        }

        $sourceRequestPath = $components['sourceRequestPath'];
        $sourcePath = $publicRoot . $sourceRequestPath;
        if (!is_file($sourcePath)) {
            return null;
        }

        $hash = self::assetHash($sourcePath);
        if ($hash === null || !hash_equals($hash, $components['hashes'][0])) {
            return null;
        }

        return $sourcePath;
    }

    public static function replacementPathForFingerprint(string $publicRoot, string $path): ?string
    {
        $components = self::fingerprintComponents($path);
        if ($components === null) {
            return null;
        }

        $sourceRequestPath = $components['sourceRequestPath'];
        $sourcePath = $publicRoot . $sourceRequestPath;
        if (!is_file($sourcePath)) {
            return null;
        }

        $currentPath = self::fingerprintedPath($publicRoot, $sourceRequestPath);
        if ($currentPath === $path) {
            return null;
        }

        return $currentPath;
    }

    /**
     * @return array{sourceRequestPath: string, hashes: list<string>}|null
     */
    private static function fingerprintComponents(string $path): ?array
    {
        if (!str_starts_with($path, '/assets/')) {
            return null;
        }

        if (preg_match('#^(/assets/.+?)(\.[a-f0-9]{12}(?:\.[a-f0-9]{12})*)(\.[A-Za-z0-9]+)$#', $path, $matches) !== 1) {
            return null;
        }

        return [
            'sourceRequestPath' => $matches[1] . $matches[3],
            'hashes' => explode('.', ltrim($matches[2], '.')),
        ];
    }

    public static function copyFingerprintedAssets(string $sourcePublicRoot, string $targetPublicRoot): void
    {
        $sourceAssetRoot = $sourcePublicRoot . '/assets';
        $entries = is_dir($sourceAssetRoot) ? scandir($sourceAssetRoot) : false;
        if ($entries === false) {
            return;
        }

        self::copyAssets($sourcePublicRoot, $targetPublicRoot, array_map(
            static fn (string $entry): string => '/assets/' . $entry,
            array_values(array_filter($entries, static fn (string $entry): bool => $entry !== '.' && $entry !== '..')),
        ));
    }

    /** @param list<string> $assetPaths */
    public static function copyReferencedFingerprintedAssets(string $sourcePublicRoot, string $targetPublicRoot, array $assetPaths): void
    {
        self::copyAssets($sourcePublicRoot, $targetPublicRoot, $assetPaths);
    }

    /** @param list<string> $assetPaths */
    private static function copyAssets(string $sourcePublicRoot, string $targetPublicRoot, array $assetPaths): void
    {
        $sourceAssetRoot = $sourcePublicRoot . '/assets';
        if (!is_dir($sourceAssetRoot)) {
            return;
        }

        $targetAssetRoot = $targetPublicRoot . '/assets';
        if (!is_dir($targetAssetRoot) && !mkdir($targetAssetRoot, 0777, true) && !is_dir($targetAssetRoot)) {
            return;
        }

        foreach (array_unique($assetPaths) as $assetPath) {
            if (!str_starts_with($assetPath, '/assets/') || str_contains(substr($assetPath, 8), '/')) {
                continue;
            }
            $entry = substr($assetPath, strlen('/assets/'));
            if (self::isFingerprintedAssetFilename($entry)) {
                continue;
            }

            $sourcePath = $sourceAssetRoot . '/' . $entry;
            if (!is_file($sourcePath)) {
                continue;
            }

            $fingerprintedPath = self::fingerprintedPath($sourcePublicRoot, '/assets/' . $entry);
            if ($fingerprintedPath === '/assets/' . $entry) {
                continue;
            }

            $targetPath = $targetPublicRoot . $fingerprintedPath;
            if (!is_file($targetPath) && !copy($sourcePath, $targetPath)) {
                continue;
            }
        }
    }

    private static function assetHash(string $path): ?string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            return null;
        }

        return substr($hash, 0, self::HASH_LENGTH);
    }

    private static function isFingerprintedAssetFilename(string $filename): bool
    {
        return preg_match('/\.[a-f0-9]{12}\.[A-Za-z0-9]+$/', $filename) === 1;
    }
}
