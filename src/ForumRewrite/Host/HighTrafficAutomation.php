<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

/**
 * Operator settings for turning high-traffic mode on and off automatically,
 * kept as a small Key: value file beside the mode state.
 */
final class HighTrafficAutomation
{
    public const FILE = 'automation.txt';
    /** @return array<string,string> */
    public static function read(HighTrafficCache $cache): array
    {
        $text = @file_get_contents($cache->root . '/' . self::FILE);
        if (!is_string($text)) return [];
        $values = [];
        foreach (explode("\n", $text) as $line) {
            $colon = strpos($line, ':');
            if ($colon === false) continue;
            $values[trim(substr($line, 0, $colon))] = trim(substr($line, $colon + 1));
        }
        return $values;
    }
    public static function turnOnEnabled(HighTrafficCache $cache): bool { return (self::read($cache)['Turn-On'] ?? 'no') === 'yes'; }
}
