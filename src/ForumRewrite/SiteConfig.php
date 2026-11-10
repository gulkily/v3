<?php

declare(strict_types=1);

namespace ForumRewrite;

final class SiteConfig
{
    public static function siteName(): string
    {
        return SiteProfileRegistry::active()['name'];
    }

    /**
     * The profile's human-readable display label. Use this for anything a
     * reader sees (page headers, titles); keep siteName() for identifier
     * and filename usages, which rely on the plain lowercase slug.
     */
    public static function displayName(): string
    {
        return SiteProfileRegistry::active()['displayName'];
    }
}
