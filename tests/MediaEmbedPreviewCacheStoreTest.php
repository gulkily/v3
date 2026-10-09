<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\View\MediaEmbedPreviewCacheStore;

final class MediaEmbedPreviewCacheStoreTest
{
    public function testMissingKeyReturnsNull(): void
    {
        $store = new MediaEmbedPreviewCacheStore(new PDO('sqlite::memory:'));

        assertSame(null, $store->get('instagram', 'Cabc123XYZ'));
    }

    public function testPutThenGetRoundTripsSuccessfulFetch(): void
    {
        $store = new MediaEmbedPreviewCacheStore(new PDO('sqlite::memory:'));

        $store->put('instagram', 'Cabc123XYZ', 'A great post', 'https://example.com/thumb.jpg');
        $row = $store->get('instagram', 'Cabc123XYZ');

        assertSame('A great post', $row['title']);
        assertSame('https://example.com/thumb.jpg', $row['thumbnailUrl']);
        assertSame(true, $row['fetchedAt'] !== '');
    }

    public function testFailedFetchIsRecordedWithNullFieldsSoItIsDistinguishableFromNeverAttempted(): void
    {
        $store = new MediaEmbedPreviewCacheStore(new PDO('sqlite::memory:'));

        $store->put('instagram', 'Cabc123XYZ', null, null);
        $row = $store->get('instagram', 'Cabc123XYZ');

        assertSame(null, $row['title']);
        assertSame(null, $row['thumbnailUrl']);
        assertSame(true, $row['fetchedAt'] !== '');
    }

    public function testPutOverwritesAnExistingRowForTheSameKey(): void
    {
        $store = new MediaEmbedPreviewCacheStore(new PDO('sqlite::memory:'));

        $store->put('instagram', 'Cabc123XYZ', null, null);
        $store->put('instagram', 'Cabc123XYZ', 'Now it worked', 'https://example.com/thumb.jpg');
        $row = $store->get('instagram', 'Cabc123XYZ');

        assertSame('Now it worked', $row['title']);
    }

    public function testDifferentProvidersOrIdsDoNotCollide(): void
    {
        $store = new MediaEmbedPreviewCacheStore(new PDO('sqlite::memory:'));

        $store->put('instagram', 'Cabc123XYZ', 'Instagram title', 'https://example.com/a.jpg');
        $store->put('instagram', 'Cdef456ZZZ', 'Other instagram title', 'https://example.com/b.jpg');

        assertSame('Instagram title', $store->get('instagram', 'Cabc123XYZ')['title']);
        assertSame('Other instagram title', $store->get('instagram', 'Cdef456ZZZ')['title']);
    }
}

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(
                'Failed asserting that values are identical. Expected '
                . var_export($expected, true)
                . ' but got '
                . var_export($actual, true)
            );
        }
    }
}
