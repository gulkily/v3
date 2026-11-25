<?php

declare(strict_types=1);

namespace ForumRewrite\Push;

/**
 * The push services this server sends to. An endpoint comes from the browser, so the server
 * only calls https addresses on these hosts, never an address a member chose.
 */
final class PushEndpointPolicy
{
    /** Host suffixes of the Apple, Google, Mozilla and Microsoft push services. */
    public const PUSH_HOSTS = ['push.apple.com', 'fcm.googleapis.com', 'push.services.mozilla.com', 'notify.windows.com'];

    /** @param list<string> $hosts */
    public function __construct(private readonly array $hosts = self::PUSH_HOSTS, private readonly bool $httpsOnly = true)
    {
    }

    public function allows(string $endpoint): bool
    {
        if (strlen($endpoint) > 2048 || preg_match('/[\s\x00-\x1f]/', $endpoint) === 1) {
            return false;
        }
        $parts = parse_url($endpoint);
        if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }
        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'https' && ($this->httpsOnly || $scheme !== 'http')) {
            return false;
        }
        $host = strtolower($parts['host']);
        foreach ($this->hosts as $allowed) {
            if ($host === $allowed || str_ends_with($host, '.' . $allowed)) {
                return true;
            }
        }

        return false;
    }

    /** The VAPID audience: the endpoint's origin. */
    public static function audience(string $endpoint): string
    {
        $parts = parse_url($endpoint);
        $origin = strtolower((string) ($parts['scheme'] ?? '')) . '://' . strtolower((string) ($parts['host'] ?? ''));

        return isset($parts['port']) ? $origin . ':' . $parts['port'] : $origin;
    }
}
