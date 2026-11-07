<?php

declare(strict_types=1);

namespace ForumRewrite\Http;

/** A state-changing request that a browser sent from this site's own pages. */
final class SameOriginRequest
{
    public static function check(): bool
    {
        $site = $_SERVER['HTTP_SEC_FETCH_SITE'] ?? null;
        $origin = $_SERVER['HTTP_ORIGIN'] ?? null;
        if ($site === null && $origin === null) {
            return false;
        }
        if ($site !== null && $site !== 'same-origin') {
            return false;
        }
        if ($origin !== null) {
            $scheme = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' ? 'https://' : 'http://';
            if ($origin !== $scheme . ($_SERVER['HTTP_HOST'] ?? '')) {
                return false;
            }
        }

        return true;
    }
}
