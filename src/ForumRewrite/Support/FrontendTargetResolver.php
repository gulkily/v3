<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use RuntimeException;

final class FrontendTargetResolver
{
    public function __construct(private readonly string $projectRoot, private readonly string $repositoryRoot, private readonly string $databasePath = '',
        private readonly array $localCandidates = ['http://127.0.0.1:8000', 'http://127.0.0.1:80'])
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
        return $servers[0] ?? ['url' => '', 'source' => 'local discovery', 'discover' => true];
    }

    public function check(array $target): array
    {
        if (isset($target['status'])) { return $target + ['latency_ms' => null]; }
        $deadline = hrtime(true) / 1e9 + 3.0;
        $probe = new FrontendProbe();
        if (isset($target['discover'])) { return $this->discover($probe, $deadline); }
        if (isset($target['identity'])) {
            $proof = $probe->probe($target['url'], max(0, $deadline - hrtime(true) / 1e9), $target['identity']);
            if ($proof['status'] !== 'responding') { return $proof + $target; }
        }
        return $probe->probe($target['url'], max(0, $deadline - hrtime(true) / 1e9)) + $target;
    }

    private function discover(FrontendProbe $probe, float $deadline): array
    {
        $identity = FrontendInstance::identity($this->projectRoot, $this->repositoryRoot, $this->database());
        $matches = [];
        $incomplete = false;
        foreach ($this->localCandidates as $url) {
            $remaining = $deadline - hrtime(true) / 1e9;
            if ($remaining <= 0) { $incomplete = true; break; }
            $proof = $probe->probe($url, min(1.0, $remaining), $identity);
            if ($proof['status'] === 'responding') { $matches[] = $url; }
            if ($proof['status'] === 'timed_out') { $incomplete = true; }
        }
        $base = ['url' => '', 'source' => 'local discovery', 'latency_ms' => null];
        if (count($matches) > 1) {
            return ['status' => 'ambiguous', 'detail' => 'multiple local endpoints match this instance'] + $base;
        }
        if ($incomplete || count($matches) !== 1) {
            return ['status' => 'unresolved', 'detail' => $incomplete
                ? 'frontend target unresolved (discovery timed out)'
                : 'frontend target unresolved (no verified local instance)'] + $base;
        }
        return $probe->probe($matches[0], max(0, $deadline - hrtime(true) / 1e9))
            + ['url' => $matches[0], 'source' => 'verified local endpoint'];
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
