<?php

declare(strict_types=1);
namespace ForumRewrite\Llm;

use ForumRewrite\Canonical\RetractedPosts;

/** Current accepted targets for deferred maintenance, never a publication gate. */
final class ExchangeRetractionState
{
    public static function read(string $root): array
    {
        return array_fill_keys(array_keys(RetractedPosts::effectivePostIds($root)), true);
    }
}
