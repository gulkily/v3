<?php

declare(strict_types=1);

namespace ForumRewrite\Tour;

/** A route the tour may snapshot: a plain public page path, no query or traversal. */
final class TourRoute
{
    private const REFUSED_PREFIXES = ['/api/', '/internal/', '/tools/', '/assets/'];

    public static function valid(string $route): bool
    {
        if (preg_match('~^/[A-Za-z0-9_./-]*$~D', $route) !== 1 || str_contains($route, '..') || str_contains($route, '//')) {
            return false;
        }
        foreach (self::REFUSED_PREFIXES as $prefix) {
            if (str_starts_with($route, $prefix)) return false;
        }
        return true;
    }
}
