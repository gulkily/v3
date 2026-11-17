<?php

declare(strict_types=1);

namespace ForumRewrite\Import;

use RuntimeException;

/**
 * Public instances that ship with the code as import choices (`resources/known_instances.txt`).
 * A site never offers its own address: `others()` drops entries whose host matches the
 * address the page was requested on.
 */
final class KnownInstances
{
    /** @var list<array{name: string, title: string, url: string}>|null */
    private static ?array $cached = null;

    /** @return list<array{name: string, title: string, url: string}> */
    public static function all(): array
    {
        return self::$cached ??= self::fromFile(dirname(__DIR__, 3) . '/resources/known_instances.txt');
    }

    /** @return list<array{name: string, title: string, url: string}> */
    public static function fromFile(string $path): array
    {
        $text = is_file($path) ? (string) file_get_contents($path) : '';
        $instances = [];
        foreach (preg_split('/\n\s*\n/', str_replace("\r", '', $text)) ?: [] as $block) {
            $fields = [];
            foreach (explode("\n", $block) as $line) {
                if (preg_match('/^([A-Za-z]+):\s*(.*)$/', $line, $match) === 1) {
                    $fields[$match[1]] = trim($match[2]);
                }
            }
            if (!isset($fields['Name'], $fields['URL'])) {
                continue;
            }
            if (preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/D', $fields['Name']) !== 1 || preg_match('#^https?://#', $fields['URL']) !== 1) {
                throw new RuntimeException('Invalid known instance: ' . $fields['Name']);
            }
            $instances[] = ['name' => $fields['Name'], 'title' => $fields['Title'] ?? $fields['Name'], 'url' => rtrim($fields['URL'], '/')];
        }

        return $instances;
    }

    /**
     * Known instances other than the one at $host (the request's Host, with or without a port).
     *
     * @param list<array{name: string, title: string, url: string}>|null $instances
     * @return list<array{name: string, title: string, url: string}>
     */
    public static function others(string $host, ?array $instances = null): array
    {
        $own = self::normalizeHost($host);

        return array_values(array_filter($instances ?? self::all(), static fn (array $instance): bool => self::normalizeHost((string) parse_url($instance['url'], PHP_URL_HOST) . (($port = parse_url($instance['url'], PHP_URL_PORT)) ? ':' . $port : '')) !== $own));
    }

    public static function url(string $name): ?string
    {
        foreach (self::all() as $instance) {
            if (strcasecmp($instance['name'], $name) === 0) {
                return $instance['url'];
            }
        }

        return null;
    }

    private static function normalizeHost(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('/:(80|443)$/', '', $host) ?? $host;

        return preg_replace('/^www\./', '', $host) ?? $host;
    }
}
