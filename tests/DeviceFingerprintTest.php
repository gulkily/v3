<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Session\DeviceFingerprint;

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting that values are identical. Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

final class DeviceFingerprintTest
{
    public function testCommonBrowsersGetCoarseValues(): void
    {
        $cases = [
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1' => 'safari/ios/mobile',
            'Mozilla/5.0 (iPhone; CPU iPhone OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/131.0 Mobile/15E148 Safari/604.1' => 'chrome/ios/mobile',
            'Mozilla/5.0 (iPad; CPU OS 18_6 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Mobile/15E148 Safari/604.1' => 'safari/ios/tablet',
            // iPadOS Safari's default desktop mode looks like a Mac; it still matches itself.
            'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/26.0 Safari/605.1.15' => 'safari/macos/desktop',
            'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Mobile Safari/537.36' => 'chrome/android/mobile',
            'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/131.0 Safari/537.36 Edg/131.0' => 'edge/windows/desktop',
            'Mozilla/5.0 (X11; Linux x86_64; rv:133.0) Gecko/20100101 Firefox/133.0' => 'firefox/linux/desktop',
            'curl/8.5.0' => 'other/other/desktop',
        ];
        foreach ($cases as $userAgent => $expected) {
            assertSame($expected, DeviceFingerprint::fromUserAgent($userAgent));
        }
        assertSame('', DeviceFingerprint::fromUserAgent('  '));
    }

    public function testOnlyKnownEqualValuesMatch(): void
    {
        assertSame(true, DeviceFingerprint::matches('safari/ios/mobile', 'safari/ios/mobile'));
        assertSame(false, DeviceFingerprint::matches('safari/ios/mobile', 'chrome/android/mobile'));
        assertSame(false, DeviceFingerprint::matches('', 'safari/ios/mobile'));
        assertSame(false, DeviceFingerprint::matches('', ''));
    }
}
