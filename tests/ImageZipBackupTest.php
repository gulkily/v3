<?php

declare(strict_types=1);
require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';
require_once __DIR__ . '/Support/ImportHttpServer.php';

use ForumRewrite\Images\ImageBackup;
use ForumRewrite\Images\ImageReference;
use ForumRewrite\Images\ImageRestore;
use ForumRewrite\Images\ImageStore;

final class ImageZipBackupTest
{
    public function testZipAndLegacyTarHttpDownloadsHeadersRecoveryAndAccess(): void
    {
        $w = new ImportTestWorkspace();
        $root = $w->repository('source', true); $w->git($root);
        $imageRoot = $w->root . '/images'; mkdir($imageRoot);
        $database = $w->root . '/cache/index.sqlite3';
        $environment = ['FORUM_REPOSITORY_ROOT' => $root, 'FORUM_DATABASE_PATH' => $database,
            'FORUM_STATIC_HTML_ROOT' => $w->root . '/static', 'FORUM_IMAGE_ROOT' => $imageRoot,
            'FORUM_TASK_QUEUE_DATABASE_PATH' => $w->root . '/queue.sqlite3', 'FORUM_APPROVED_MEMBERS_ONLY' => 'false'];
        $server = new ImportHttpServer(realpath(__DIR__ . '/../public/router.php'), $w->root . '/server.log', $environment);
        $head = (new ImageBackup($root, $database))->revision();
        $pixel = base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAIAAACQd1PeAAAACXBIWXMAAA7EAAAOxAGVKw4bAAAADElEQVQImWNgYGAAAAAEAAGjChXjAAAAAElFTkSuQmCC');
        $w->put($w->root . '/pixel.png', $pixel);
        $ref = ImageStore::inspect($w->root . '/pixel.png', '');
        $store = new ImageStore($root, $imageRoot);
        foreach ([false, true] as $populated) {
            if ($populated) { $store->put($w->root . '/pixel.png', $ref); }
            foreach (['zip' => 'application/zip', 'tar' => 'application/x-tar'] as $format => $type) {
                foreach (['', '?revision=' . $head] as $query) {
                    [$status, $headers, $body] = $this->request($server->url . '/downloads/images.' . $format . $query);
                    assertSame(200, $status);
                    assertStringContains('Content-Type: ' . $type, $headers);
                    assertStringContains('-images-' . $head . '.' . $format, $headers);
                    assertStringContains('no-store', $headers);
                    if ($format === 'zip') { assertSame('PK', substr($body, 0, 2)); }
                    else { assertStringContains('manifest.json', substr($body, 0, 100)); }
                    $archive = $w->root . '/images.' . $format; file_put_contents($archive, $body);
                    if ($format === 'zip') {
                        assertSame(0, $w->command(['unzip', '-t', $archive])[0]);
                        $extracted = $w->root . '/extracted-' . (int) $populated . '-' . strlen($query);
                        assertSame(0, $w->command(['unzip', '-q', $archive, '-d', $extracted])[0]);
                        assertTrue(is_file($extracted . '/manifest.json'));
                        assertTrue(is_dir($extracted . '/objects'));
                        if ($populated) { assertSame($pixel, file_get_contents($extracted . '/objects/' . ImageReference::filename($ref))); }
                    }
                    $restored = $w->root . '/restored-' . $format . '-' . (int) $populated . '-' . strlen($query);
                    $result = (new ImageRestore())->run($archive, $root, $database, $restored);
                    assertSame($populated ? 1 : 0, $result['objects']);
                    if ($populated) { assertSame($pixel, file_get_contents($restored . '/' . ImageReference::filename($ref))); }
                }
            }
        }
        foreach (['zip', 'tar'] as $format) {
            assertSame(405, $this->request($server->url . '/downloads/images.' . $format, 'POST')[0]);
            [$status, , $body] = $this->request($server->url . '/downloads/images.' . $format . '?revision=' . str_repeat('0', 40));
            assertSame(409, $status);
            assertStringContains('Refresh Backup and download both artifacts again.', $body);
        }
        assertSame(404, $this->request($server->url . '/downloads/images.rar')[0]);
        file_put_contents($store->resolve($ref), 'damaged');
        assertSame(500, $this->request($server->url . '/downloads/images.zip')[0]);
        file_put_contents($imageRoot . '/' . ImageReference::filename($ref), $pixel);
        assertSame(200, $this->request($server->url . '/downloads/images.zip')[0]);
        unset($server);
        $environment['FORUM_APPROVED_MEMBERS_ONLY'] = 'true';
        $server = new ImportHttpServer(realpath(__DIR__ . '/../public/router.php'), $w->root . '/restricted.log', $environment);
        foreach (['zip', 'tar'] as $format) {
            [$status, , $body] = $this->request($server->url . '/downloads/images.' . $format);
            assertTrue($status !== 200);
            assertSame(false, str_starts_with($body, 'PK') || str_starts_with($body, 'manifest.json'));
        }
        assertSame($head, (new ImageBackup($root, $database))->revision());
        assertSame('', trim($w->command(['git', '-C', $root, 'status', '--porcelain'])[1]));
    }

    public function testMissingZipSupportAndUnknownFormatsFailActionably(): void
    {
        $w = new ImportTestWorkspace(); $root = $w->repository('source'); $w->git($root);
        $code = 'require $argv[1]."/autoload.php"; try { (new ForumRewrite\\Images\\ImageBackup($argv[2],$argv[3]))->export("images"); } catch (Throwable $e) { fwrite(STDERR,$e->getMessage()); exit(1); }';
        [$status, $output] = $w->command(['php', '-n', '-r', $code, dirname(__DIR__), $root, $w->root . '/index.sqlite3']);
        assertSame(1, $status); assertStringContains('PHP ZIP support is unavailable.', $output);
        $backup = new ImageBackup($root, $w->root . '/index.sqlite3');
        foreach ([['unknown', null, 'zip'], ['images', null, 'rar']] as $arguments) {
            try { $backup->export(...$arguments); } catch (RuntimeException $error) {
                assertStringContains('Unsupported', $error->getMessage()); continue;
            }
            throw new RuntimeException('Expected unsupported format rejection.');
        }
    }

    private function request(string $url, string $method = 'GET'): array
    {
        $body = file_get_contents($url, false, stream_context_create(['http' => ['method' => $method, 'ignore_errors' => true, 'follow_location' => 0]]));
        preg_match('#^HTTP/\S+ (\d+)#', $http_response_header[0], $match);
        return [(int) $match[1], implode("\n", $http_response_header), $body];
    }
}
