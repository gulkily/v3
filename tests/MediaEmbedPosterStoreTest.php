<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\View\MediaEmbedPosterStore;

final class MediaEmbedPosterStoreTest
{
    private const JPEG = "\xFF\xD8\xFF\xE0fake-jpeg-body";

    public function testAPosterIsFetchedOnceFromYoutubesImageHostAndKept(): void
    {
        $directory = $this->directory();
        try {
            $requested = [];
            $store = new MediaEmbedPosterStore($directory, function (string $url) use (&$requested): string {
                $requested[] = $url;
                return self::JPEG;
            });
            assertSame(null, $store->path('M7lc1UVf-VE'));
            assertSame(true, $store->warm('M7lc1UVf-VE'));
            assertSame(true, $store->warm('M7lc1UVf-VE'));
            assertSame(['https://i.ytimg.com/vi/M7lc1UVf-VE/hqdefault.jpg'], $requested);
            assertSame(self::JPEG, file_get_contents((string) $store->path('M7lc1UVf-VE')));
        } finally {
            $this->cleanup($directory);
        }
    }

    public function testAFailedOrNonJpegFetchKeepsNothingAndWaitsAnHour(): void
    {
        $directory = $this->directory();
        try {
            $calls = 0;
            $store = new MediaEmbedPosterStore($directory, function () use (&$calls): string {
                $calls++;
                return '<html>not an image</html>';
            });
            assertSame(false, $store->warm('M7lc1UVf-VE'));
            assertSame(false, $store->warm('M7lc1UVf-VE'));
            assertSame(1, $calls);
            assertSame(null, $store->path('M7lc1UVf-VE'));
            touch($directory . '/M7lc1UVf-VE.failed', time() - 3601);
            assertSame(false, $store->warm('M7lc1UVf-VE'));
            assertSame(2, $calls);
        } finally {
            $this->cleanup($directory);
        }
    }

    public function testOnlyRealVideoIdsReachTheDisk(): void
    {
        $directory = $this->directory();
        try {
            $store = new MediaEmbedPosterStore($directory, static fn (): string => self::JPEG);
            foreach (['../../etc/pas', 'short', 'M7lc1UVf-VE/', ''] as $id) {
                assertSame(false, $store->warm($id));
                assertSame(null, $store->path($id));
            }
            assertSame([], glob($directory . '/*') ?: []);
        } finally {
            $this->cleanup($directory);
        }
    }

    private function directory(): string
    {
        return sys_get_temp_dir() . '/media-embed-posters-' . bin2hex(random_bytes(6));
    }

    private function cleanup(string $directory): void
    {
        foreach (glob($directory . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($directory);
    }
}
