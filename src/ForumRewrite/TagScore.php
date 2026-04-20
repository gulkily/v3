<?php

declare(strict_types=1);

namespace ForumRewrite;

final class TagScore
{
    /**
     * @return array<string, int>
     */
    public static function scoredTags(): array
    {
        return [
            'like' => 1,
            'flag' => -100,
        ];
    }

    public static function isScoredTag(string $tag): bool
    {
        return array_key_exists($tag, self::scoredTags());
    }

    public static function scoreValueForTag(string $tag): int
    {
        return self::scoredTags()[$tag] ?? 0;
    }
}
