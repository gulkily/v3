<?php

declare(strict_types=1);

namespace ForumRewrite\Security;

/**
 * Stops the background agents gpg starts for a temporary home, then deletes it.
 */
final class GpgHomeCleaner
{
    public static function dispose(string $home): void
    {
        if (!is_dir($home)) {
            return;
        }

        // Agent sockets are keyed to the home path, so stop them before it is removed.
        @exec('timeout 10 gpgconf --homedir ' . escapeshellarg($home) . ' --kill all >/dev/null 2>&1');

        self::deleteTree($home);
    }

    private static function deleteTree(string $path): void
    {
        $items = @scandir($path);
        foreach ($items === false ? [] : $items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $child = $path . '/' . $item;
            if (is_dir($child) && !is_link($child)) {
                self::deleteTree($child);
                continue;
            }

            @unlink($child);
        }

        @rmdir($path);
    }
}
