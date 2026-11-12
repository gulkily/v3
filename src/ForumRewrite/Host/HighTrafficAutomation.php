<?php

declare(strict_types=1);
namespace ForumRewrite\Host;

use ForumRewrite\Support\PublicationLock;

/**
 * Operator settings for turning high-traffic mode on and off automatically, kept as a
 * small Key: value file beside the mode state. Two independent switches:
 *   Turn-On:  the request counter may turn the mode on when traffic spikes.
 *   Turn-Off: the per-minute cron job may turn off an automatic episode after a quiet period.
 * Precedence, highest first: safety withdrawal, the operator's use of the mode switch, automation.
 */
final class HighTrafficAutomation
{
    public const FILE = 'automation.txt';
    private const KEYS = ['Turn-On', 'Turn-Off', 'Manual-Off-Hold', 'Changed-At', 'Reason'];
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
    public static function turnOffEnabled(HighTrafficCache $cache): bool { return (self::read($cache)['Turn-Off'] ?? 'no') === 'yes'; }
    /** @param array<string,string> $changes */
    public static function write(HighTrafficCache $cache, array $changes): array
    {
        $cache->initialize();
        $values = array_merge(self::read($cache), $changes);
        $text = '';
        foreach (self::KEYS as $key) if (isset($values[$key]) && $values[$key] !== '') $text .= $key . ': ' . str_replace(["\r", "\n"], ' ', $values[$key]) . "\n";
        $cache->atomic($cache->root . '/' . self::FILE, $text);
        return self::read($cache);
    }
    /** Operator change of either switch; null leaves a switch as it is. */
    public static function set(HighTrafficCache $cache, ?bool $turnOn, ?bool $turnOff, string $reason): array
    {
        return PublicationLock::withLock($cache->repositoryRoot, fn (): array => $cache->locked(function () use ($cache, $turnOn, $turnOff, $reason): array {
            $changes = ['Changed-At' => gmdate('c'), 'Reason' => substr($reason, 0, 200)];
            if ($turnOn !== null) $changes['Turn-On'] = $turnOn ? 'yes' : 'no';
            if ($turnOff !== null) $changes['Turn-Off'] = $turnOff ? 'yes' : 'no';
            return self::write($cache, $changes);
        }));
    }
    /**
     * Why automation may not turn the mode on now, or null when it may.
     * Any off state automation did not write itself is treated as an operator off.
     */
    public static function turnOnBlocked(HighTrafficMode $mode): ?string
    {
        $status = $mode->status();
        if (($status['effective_mode'] ?? '') === 'high-traffic') return 'already on';
        if (self::isWithdrawal($status)) return 'withdrawn for review; an operator must turn the mode on';
        if ((self::read($mode->cache)['Manual-Off-Hold'] ?? 'no') === 'yes') return 'turned off by an operator; waiting for traffic to fall below the threshold';
        return null;
    }
    /** @param array<string,mixed> $state */
    public static function isWithdrawal(array $state): bool
    {
        if (($state['source'] ?? '') === 'withdrawal') return true;
        // Legacy withdrawals carry only their reason.
        if (str_starts_with((string)($state['reason'] ?? ''), 'Content/access change')) return true;
        // Removals detach serving without writing state: still marked enabled, but not effective.
        return ($state['enabled'] ?? false) === true && ($state['effective_mode'] ?? 'normal') !== 'high-traffic';
    }
    /** Source of the latest mode change: automatic, operator or withdrawal. */
    public static function source(array $status): string
    {
        if (self::isWithdrawal($status)) return 'withdrawal';
        return ($status['source'] ?? '') === 'automatic' ? 'automatic' : 'operator';
    }
}
