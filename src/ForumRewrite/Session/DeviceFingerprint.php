<?php

declare(strict_types=1);

namespace ForumRewrite\Session;

/**
 * A coarse description of the browser a session signed in from: browser family,
 * OS family and device class, read from the User-Agent the server already receives.
 *
 * It is a tie-breaker for key bootstrap, not an identifier. Many browsers share one
 * value. It is kept only with the session in private state and is never written to
 * the repository, exports or receipts. A page script collects nothing extra.
 *
 * iPadOS Safari reports itself as a Mac by default, so an iPad and a Mac running
 * Safari share a value. That is fine: a device always matches itself.
 */
final class DeviceFingerprint
{
    /** Coarse value such as "safari/ios/mobile", or '' when the User-Agent says nothing usable. */
    public static function fromUserAgent(string $userAgent): string
    {
        $ua = trim($userAgent);
        if ($ua === '') {
            return '';
        }

        return self::browser($ua) . '/' . self::os($ua) . '/' . self::device($ua);
    }

    /** True only when both values are known and equal. A session without a value never matches. */
    public static function matches(string $stored, string $current): bool
    {
        return $stored !== '' && $current !== '' && hash_equals($stored, $current);
    }

    private static function browser(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'Edg/') || str_contains($ua, 'EdgiOS/') || str_contains($ua, 'EdgA/') => 'edge',
            str_contains($ua, 'OPR/') || str_contains($ua, 'OPiOS/') => 'opera',
            str_contains($ua, 'SamsungBrowser/') => 'samsung',
            str_contains($ua, 'Firefox/') || str_contains($ua, 'FxiOS/') => 'firefox',
            str_contains($ua, 'Chrome/') || str_contains($ua, 'CriOS/') || str_contains($ua, 'Chromium/') => 'chrome',
            str_contains($ua, 'Safari/') || str_contains($ua, 'AppleWebKit/') => 'safari',
            default => 'other',
        };
    }

    private static function os(string $ua): string
    {
        return match (true) {
            preg_match('/iPhone|iPad|iPod/', $ua) === 1 => 'ios',
            str_contains($ua, 'Android') => 'android',
            str_contains($ua, 'CrOS') => 'chromeos',
            str_contains($ua, 'Windows') => 'windows',
            str_contains($ua, 'Macintosh') || str_contains($ua, 'Mac OS X') => 'macos',
            str_contains($ua, 'Linux') || str_contains($ua, 'X11') => 'linux',
            default => 'other',
        };
    }

    private static function device(string $ua): string
    {
        return match (true) {
            str_contains($ua, 'iPad') || (str_contains($ua, 'Android') && !str_contains($ua, 'Mobile')) => 'tablet',
            preg_match('/iPhone|iPod|Mobile/', $ua) === 1 => 'mobile',
            default => 'desktop',
        };
    }
}
