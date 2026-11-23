<?php

declare(strict_types=1);

require_once __DIR__ . '/Support/ImportTestWorkspace.php';
require_once __DIR__ . '/Support/ContactsTestServer.php';

final class ContactsLaunchTest
{
    public function testHelpAndInvalidOptionsDoNotCreateStorage(): void
    {
        $workspace = new ImportTestWorkspace();
        $command = [dirname(__DIR__) . '/v3', 'contacts', '--data-dir=' . $workspace->root . '/contacts'];
        foreach (['-h', '--help'] as $option) {
            [$code, $output] = $workspace->command([...$command, $option]);
            assertSame(0, $code);
            assertStringContains('./v3 contacts', $output);
        }
        [$code, $output] = $workspace->command([...$command, '--listen-all']);
        assertTrue($code !== 0);
        assertStringContains('./v3 contacts', $output);
        assertSame(false, file_exists($workspace->root . '/contacts'));
    }

    public function testPrivateListenerRecoversFromPortCollisionAndStops(): void
    {
        $workspace = new ImportTestWorkspace();
        $holder = stream_socket_server('tcp://127.0.0.1:0');
        $occupied = (int) parse_url('http://' . stream_socket_get_name($holder, false), PHP_URL_PORT);
        $server = new ContactsTestServer($workspace->root . '/contacts', ['--port=' . $occupied]);
        try {
            $port = parse_url($server->url, PHP_URL_PORT);
            assertTrue($port !== $occupied);
            [$status, $body] = $server->request('/');
            assertSame(200, $status);
            assertStringContains('Contacts', $body);
            assertSame(0700, fileperms($workspace->root . '/contacts') & 0777);
        } finally {
            $server->stop();
            fclose($holder);
        }
        $socket = stream_socket_server('tcp://127.0.0.1:' . $port);
        assertTrue(is_resource($socket), 'shutdown releases the bound port');
        fclose($socket);
    }

    public function testPrivateRequestsRequireLaunchCredentialAndLocalOrigin(): void
    {
        $workspace = new ImportTestWorkspace();
        $store = $workspace->root . '/contacts';
        $server = new ContactsTestServer($store);
        $old = $server->credential;
        try {
            assertSame(401, $server->request('/api/contacts', authorize: false)[0]);
            assertSame(403, $server->request('/api/contacts', headers: ['Host' => 'different.invalid'])[0]);
            assertSame(403, $server->request('/api/contacts', headers: ['Forwarded' => 'for=127.0.0.1'])[0]);
            [$status, , $headers] = $server->request('/api/contacts');
            assertSame(200, $status);
            assertTrue(in_array('Cache-Control: no-store', $headers, true));
        } finally {
            $server->stop();
        }
        $server = new ContactsTestServer($store);
        try {
            assertSame(401, $server->request('/api/contacts', headers: ['Authorization' => 'Bearer ' . $old])[0]);
        } finally {
            $server->stop();
        }
    }

    public function testRefusesCheckoutAndSymlinkStorage(): void
    {
        $workspace = new ImportTestWorkspace();
        $checkout = dirname(__DIR__);
        symlink($workspace->root, $workspace->root . '/alias');
        mkdir($workspace->root . '/shared', 0755);
        chmod($workspace->root . '/shared', 0755);
        foreach ([$checkout . '/public/contacts-test', $workspace->root . '/alias/new', $workspace->root . '/shared'] as $path) {
            [$code, $output] = $workspace->command([$checkout . '/v3', 'contacts', '--data-dir=' . $path]);
            assertTrue($code !== 0);
            assertStringContains('Contacts data directory', $output);
        }
        assertSame(false, file_exists($checkout . '/public/contacts-test'));
        assertSame(false, file_exists($workspace->root . '/new'));
        assertSame(0755, fileperms($workspace->root . '/shared') & 0777);
    }

    public function testLaunchCleansAbandonedUploadsWithoutTouchingOtherFiles(): void
    {
        $workspace = new ImportTestWorkspace();
        $directory = $workspace->root . '/contacts';
        mkdir($directory, 0700);
        $abandoned = $directory . '/uploads-' . bin2hex(random_bytes(16));
        mkdir($abandoned, 0700);
        touch($abandoned . '/.lease');
        $workspace->put($abandoned . '/phpfixture', 'Abandoned upload');
        $workspace->put($directory . '/my-private-backup.txt', 'Keep this file');
        $server = new ContactsTestServer($directory);
        try {
            assertSame(false, is_dir($abandoned));
            assertSame('Keep this file', file_get_contents($directory . '/my-private-backup.txt'));
        } finally {
            $server->stop();
        }
        assertSame([], glob($directory . '/uploads-*'));
    }
}
