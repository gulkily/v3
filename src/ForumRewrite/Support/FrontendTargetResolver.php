<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use RuntimeException;

final class FrontendTargetResolver
{
    public function __construct(private readonly string $projectRoot, private readonly string $repositoryRoot)
    {
    }

    /** @return array{url:string,source:string} */
    public function resolve(?string $explicitUrl = null): array
    {
        $config = PrivateConfig::load($this->projectRoot);
        $configured = $config['FORUM_STATUS_URL'] ?? '';
        if ($explicitUrl !== null) {
            return ['url' => self::validateUrl($explicitUrl), 'source' => '--url'];
        }
        if ($configured !== '') {
            if (!is_string($configured)) {
                throw new RuntimeException('FORUM_STATUS_URL must be an http(s) URL.');
            }
            return ['url' => self::validateUrl($configured), 'source' => 'FORUM_STATUS_URL'];
        }
        return ['url' => 'http://127.0.0.1:8000', 'source' => 'default'];
    }

    public static function validateUrl(string $url): string
    {
        $parts = parse_url($url);
        if (!is_array($parts) || !in_array($parts['scheme'] ?? '', ['http', 'https'], true)
            || ($parts['host'] ?? '') === '' || isset($parts['user']) || isset($parts['pass'])
            || isset($parts['fragment']) || preg_match('/[\x00-\x20\x7f]/', $url)) {
            throw new RuntimeException('Frontend target must be an http(s) URL without credentials, fragments, or whitespace.');
        }
        return $url;
    }
}
