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
}
