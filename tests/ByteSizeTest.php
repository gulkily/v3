<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Support\ByteSize;

final class ByteSizeTest
{
    public function testFormatEdgeValues(): void
    {
        assertSame('0 B', ByteSize::format(0));
        assertSame('1023 B', ByteSize::format(1023));
        assertSame('1.0 KiB', ByteSize::format(1024));
        assertSame('1.5 KiB', ByteSize::format(1536));
        assertSame('1024.0 KiB', ByteSize::format(1024 * 1024 - 1));
        assertSame('1.0 MiB', ByteSize::format(1024 * 1024));
        assertSame('12.4 MiB', ByteSize::format((int) (12.4 * 1024 * 1024)));
        assertSame('3.0 GiB', ByteSize::format(3 * 1024 ** 3));
        assertSame('2048.0 TiB', ByteSize::format(2048 * 1024 ** 4));
    }

    public function testProbesMeasureCommittedFilesAndGitStore(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source');
        $w->put($root . '/records/posts/one.txt', str_repeat('a', 100));
        $w->put($root . '/records/posts/two.txt', str_repeat('b', 250));
        $w->put($root . '/outside.txt', str_repeat('c', 999));
        $w->git($root);
        // Uncommitted content is not counted: the tree size reflects HEAD.
        $w->put($root . '/records/posts/three.txt', str_repeat('d', 5000));

        assertSame(350, ByteSize::trackedTree($root, 'records'));
        assertSame(100, ByteSize::file($root . '/records/posts/one.txt'));
        $store = ByteSize::gitStore($root);
        assertTrue(is_int($store) && $store > 0, 'git store size should be positive after a commit');
        assertSame(0, $store % 1024, 'count-objects reports whole KiB');
    }

    public function testProbesReturnNullWhenUnavailable(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source');
        $w->put($root . '/records/posts/one.txt', 'x');

        assertSame(null, ByteSize::trackedTree($root, 'records'), 'no .git means no size');
        assertSame(null, ByteSize::gitStore($root));
        assertSame(null, ByteSize::file($root . '/missing.sqlite3'));
        assertSame(null, ByteSize::file($root . '/records'), 'directories are not files');
    }
}
