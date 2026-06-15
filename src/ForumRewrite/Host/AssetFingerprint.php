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
        if (!str_starts_with($path, '/assets/')) {
            return null;
        }

        if (preg_match('#^(/assets/.+)\.([a-f0-9]{12})(\.[A-Za-z0-9]+)$#', $path, $matches) !== 1) {
            return null;
        }

        $sourceRequestPath = $matches[1] . $matches[3];
        $sourcePath = $publicRoot . $sourceRequestPath;
        if (!is_file($sourcePath)) {
            return null;
        }

        $hash = self::assetHash($sourcePath);
        if ($hash === null || !hash_equals($hash, $matches[2])) {
            return null;
        }

        return $sourcePath;
    }

    public static function copyFingerprintedAssets(string $sourcePublicRoot, string $targetPublicRoot): void
    {
        $sourceAssetRoot = $sourcePublicRoot . '/assets';
        if (!is_dir($sourceAssetRoot)) {
            return;
        }

        $targetAssetRoot = $targetPublicRoot . '/assets';
        if (!is_dir($targetAssetRoot) && !mkdir($targetAssetRoot, 0777, true) && !is_dir($targetAssetRoot)) {
            return;
        }

        $entries = scandir($sourceAssetRoot);
        if ($entries === false) {
            return;
        }

        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
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
}
