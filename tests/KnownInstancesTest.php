<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Import\InstanceSourceResolver;
use ForumRewrite\Import\KnownInstances;

final class KnownInstancesTest
{
    public function testShippedListOffersZenMemes(): void
    {
        assertSame([['name' => 'zenmemes', 'title' => 'ZenMemes', 'url' => 'https://zenmemes.com']], KnownInstances::all());
        assertSame('https://zenmemes.com', (new InstanceSourceResolver())->resolve('zenmemes'));
        assertSame('https://zenmemes.com', (new InstanceSourceResolver())->resolve('ZenMemes'));
    }

    public function testAnInstanceNeverOffersItsOwnAddress(): void
    {
        $list = [
            ['name' => 'zenmemes', 'title' => 'ZenMemes', 'url' => 'https://zenmemes.com'],
            ['name' => 'qdb', 'title' => 'QDB', 'url' => 'https://qdb.us'],
            ['name' => 'local', 'title' => 'Local', 'url' => 'http://127.0.0.1:8000'],
        ];
        assertSame(['zenmemes', 'qdb', 'local'], array_column(KnownInstances::others('127.0.0.1:8123', $list), 'name'));
        assertSame(['zenmemes', 'qdb'], array_column(KnownInstances::others('127.0.0.1:8000', $list), 'name'));
        assertSame(['qdb', 'local'], array_column(KnownInstances::others('zenmemes.com', $list), 'name'));
        assertSame(['qdb', 'local'], array_column(KnownInstances::others('www.zenmemes.com:443', $list), 'name'));
        assertSame(['zenmemes', 'local'], array_column(KnownInstances::others('QDB.us', $list), 'name'));
    }

    public function testFileFormatAndValidation(): void
    {
        $path = sys_get_temp_dir() . '/known-instances-' . bin2hex(random_bytes(4)) . '.txt';
        file_put_contents($path, "Schema: known-instances-v1\n\n# comment\n\nName: one\nURL: https://one.example/\n\nName: two\nTitle: Two\nURL: http://two.example:8080\n");
        assertSame([
            ['name' => 'one', 'title' => 'one', 'url' => 'https://one.example'],
            ['name' => 'two', 'title' => 'Two', 'url' => 'http://two.example:8080'],
        ], KnownInstances::fromFile($path));
        file_put_contents($path, "Name: bad name\nURL: https://x.example\n");
        $failed = false;
        try { KnownInstances::fromFile($path); } catch (RuntimeException) { $failed = true; }
        assertTrue($failed);
        unlink($path);
        assertSame([], KnownInstances::fromFile($path));
    }

    public function testSourcesFileStillOverridesAKnownName(): void
    {
        $path = sys_get_temp_dir() . '/sources-' . bin2hex(random_bytes(4)) . '.json';
        file_put_contents($path, json_encode(['zenmemes' => 'https://mirror.example']));
        assertSame('https://mirror.example', (new InstanceSourceResolver())->resolve('zenmemes', $path));
        unlink($path);
    }
}
