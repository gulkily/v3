<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

/**
 * Reports whether the `gpg` command the server uses for signature checks is
 * on PATH. Without it every signature check fails, so status warns early.
 * See docs/reference/openpgp.md ("Server side").
 */
final class GpgAvailability
{
    /** @return list<string> */
    public static function warnings(?string $path = null): array
    {
        $path ??= (string) getenv('PATH');
        foreach (explode(PATH_SEPARATOR, $path) as $directory) {
            if ($directory !== '' && is_file($directory . '/gpg') && is_executable($directory . '/gpg')) {
                return [];
            }
        }

        return ['gpg was not found on PATH; signature checks and key inspection will fail. Install GnuPG (the gnupg package).'];
    }
}
