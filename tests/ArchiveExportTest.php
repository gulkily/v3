<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Images\ExportIdentity;
use ForumRewrite\Images\ImageBackup;

final class ArchiveExportTest
{
    public function testIdentityIncludesUntrackedBytesPermissionsAndImages(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source');
        $w->put($root . '/records/posts/one.txt', $w->post('one'));
        $w->git($root);
        $revision = (new ImageBackup($root, $w->root . '/index.sqlite3'))->revision();
        $capture = fn (array $images = []) => ExportIdentity::capture($root, $revision, $images);
        $original = $capture();
        assertSame($original, $capture());
        file_put_contents($root . '/note.txt', 'one');
        $first = $capture();
        assertTrue($first !== $original);
        $mtime = filemtime($root . '/note.txt');
        file_put_contents($root . '/note.txt', 'two'); touch($root . '/note.txt', $mtime);
        assertTrue($capture() !== $first);
        unlink($root . '/note.txt');
        assertSame($original, $capture());
        assertTrue($capture(['image' => ['sha256' => 'changed']]) !== $original);
        chmod($root . '/records/posts/one.txt', 0600); clearstatcache();
        assertTrue($capture() !== $original);
    }
    public function testWarmArchivesReuseAndCorruptionRebuildsWithoutDeletingReaders(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source');
        $w->put($root . '/records/posts/one.txt', $w->post('one')); $w->git($root);
        $backup = new ImageBackup($root, $w->root . '/index.sqlite3');
        foreach (['tar.gz', 'zip', 'images'] as $kind) {
            $first = $backup->export($kind);
            $second = $backup->export($kind);
            assertSame(false, $first['reused']); assertSame(true, $second['reused']);
            assertSame($first['path'], $second['path']);
            assertSame(stream_get_contents($first['stream']), stream_get_contents($second['stream']));
            fclose($first['stream']); fclose($second['stream']);
        }
        $old = $backup->export('tar.gz');
        $bytes = file_get_contents($old['path']);
        file_put_contents(dirname($old['path']) . '/metadata', 'broken');
        $rebuilt = $backup->export('tar.gz');
        assertSame(false, $rebuilt['reused']);
        assertSame($bytes, stream_get_contents($old['stream']));
        assertTrue(is_file($rebuilt['path']));
        fclose($old['stream']); fclose($rebuilt['stream']);
        $w->put($root . '/note.txt', 'new bytes without a commit');
        $changed = $backup->export('tar.gz');
        assertSame(false, $changed['reused']);
        assertTrue($changed['path'] !== $rebuilt['path']);
        fclose($changed['stream']);
    }

}
