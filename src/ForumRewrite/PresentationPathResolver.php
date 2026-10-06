<?php

declare(strict_types=1);

namespace ForumRewrite;

use InvalidArgumentException;

final class PresentationPathResolver
{
    /**
     * @param array{browserNamespace?: mixed} $profile
     */
    public static function staticHtmlRoot(string $projectRoot, array $profile): string
    {
        $browserNamespace = $profile['browserNamespace'] ?? null;
        if (!is_string($browserNamespace) || $browserNamespace === '') {
            throw new InvalidArgumentException('Profile requires a browser namespace for presentation paths.');
        }

        $suffix = $browserNamespace === 'zenmemes' ? '' : '_' . $browserNamespace;

        return $projectRoot . '/state/static_html' . $suffix;
    }
}
