<?php

declare(strict_types=1);

namespace ForumRewrite\Support;

use RuntimeException;

/**
 * The local start page's request guard and token. Local development convenience; never an
 * operator endpoint on deployed sites. The import itself is queued by InstanceImportRequests.
 */
final class LocalDevelopmentImport
{
    public static function enabled(array $server): bool
    {
        return PHP_SAPI === 'cli-server' && getenv('V3_DEV_LOG') === '1'
            && in_array($server['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)
            && preg_match('/^(?:localhost|127\.0\.0\.1|\[::1\])(?::[0-9]+)?$/iD', (string) ($server['HTTP_HOST'] ?? '')) === 1
            && empty($server['HTTP_FORWARDED']) && empty($server['HTTP_X_FORWARDED_FOR'])
            && empty($server['HTTP_X_FORWARDED_HOST']) && empty($server['HTTP_X_FORWARDED_PROTO']);
    }

    public static function isEntry(string $uri): bool
    {
        $path = parse_url($uri, PHP_URL_PATH);
        return $path === '/local/' || ($path === '/' && (string) parse_url($uri, PHP_URL_QUERY) === '');
    }

    public function __construct(private readonly string $repositoryRoot) {}

    public function token(): string
    {
        $path = $this->repositoryRoot . '/.git/local-development-import-token';
        $handle = fopen($path, 'c+');
        if ($handle === false) throw new RuntimeException('Cannot open local import token. Check repository permissions and reload.');
        try {
            if (!flock($handle, LOCK_EX)) throw new RuntimeException('Cannot lock local import token. Reload to retry.');
            chmod($path, 0600);
            $token = stream_get_contents($handle);
            if (!is_string($token) || preg_match('/^[a-f0-9]{64}$/D', $token) !== 1) {
                $token = bin2hex(random_bytes(32));
                rewind($handle);
                ftruncate($handle, 0);
                if (fwrite($handle, $token) !== 64) throw new RuntimeException('Cannot save local import token. Check repository permissions and reload.');
            }
            return $token;
        } finally { fclose($handle); }
    }

    public function authorize(array $server, mixed $token): bool
    {
        $origin = $server['HTTP_ORIGIN'] ?? null;
        $expected = 'http://' . ($server['HTTP_HOST'] ?? '');
        return self::enabled($server) && is_string($token) && hash_equals($this->token(), $token)
            && ($origin === null || $origin === $expected)
            && !in_array($server['HTTP_SEC_FETCH_SITE'] ?? '', ['cross-site'], true);
    }
}
