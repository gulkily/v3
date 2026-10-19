<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use RuntimeException;

final class FrontendTargetResolver
{
    public function __construct(private readonly string $projectRoot, private readonly string $repositoryRoot, private readonly string $databasePath = '')
    {
    }

    /** @return array<string,mixed> */
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
        $servers = DevServerRegistry::active($this->projectRoot, $this->repositoryRoot, $this->database());
        if (count($servers) > 1) {
            return ['url' => '', 'source' => 'development discovery', 'status' => 'ambiguous', 'detail' => 'multiple development servers match this instance'];
        }
        return $servers[0] ?? ['url' => 'http://127.0.0.1:8000', 'source' => 'default'];
    }

    public function check(array $target): array
    {
        if (isset($target['status'])) { return $target + ['latency_ms' => null]; }
        $deadline = hrtime(true) / 1e9 + 3.0;
        $probe = new FrontendProbe();
        if (isset($target['identity'])) {
            $proof = $probe->probe($target['url'], max(0, $deadline - hrtime(true) / 1e9), $target['identity']);
            if ($proof['status'] !== 'responding') { return $proof + $target; }
        }
        return $probe->probe($target['url'], max(0, $deadline - hrtime(true) / 1e9)) + $target;
    }

    private function database(): string
    {
        return $this->databasePath !== '' ? $this->databasePath : $this->projectRoot . '/state/cache/post_index.sqlite3';
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
