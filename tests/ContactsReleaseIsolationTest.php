<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ContactsTestServer.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Application;
use ForumRewrite\Host\StaticArtifactBuilder;

final class ContactsReleaseIsolationTest
{
    public function testStoppedSnapshotRestoresContactsAndEditsInAnotherDirectory(): void
    {
        $workspace = new ImportTestWorkspace();
        $store = $workspace->root . '/contacts';
        $marker = 'contact-' . bin2hex(random_bytes(8));
        $this->seedBook($store, $marker);

        $backup = $workspace->root . '/backup';
        mkdir($backup, 0700);
        assertTrue(copy($store . '/contacts.txt', $backup . '/contacts.txt'));
        chmod($backup . '/contacts.txt', 0600);
        $restore = $workspace->root . '/restore';
        mkdir($restore, 0700);
        assertTrue(copy($backup . '/contacts.txt', $restore . '/contacts.txt'));
        chmod($restore . '/contacts.txt', 0600);
        $server = new ContactsTestServer($restore);
        try {
            [$contact] = $this->readBook($server)['contacts'];
            assertSame($marker, $contact['first_name']);
            assertSame('Notes for ' . $marker, $contact['note']);
            assertSame('2026-11-15', $contact['last_contacted']);
        } finally {
            $server->stop();
        }
    }

    public function testPublicForumAndReleaseArtifactsExcludeThePrivateBook(): void
    {
        $workspace = new ImportTestWorkspace();
        $checkout = dirname(__DIR__);
        $repository = $workspace->repository('forum', fixture: true);
        $workspace->git($repository);
        $database = $workspace->root . '/index.sqlite3';
        $artifactRoot = $workspace->root . '/static';
        $store = $workspace->root . '/contacts';
        $marker = 'contact-' . bin2hex(random_bytes(8));
        $this->seedBook($store, $marker);
        [$gitCode, $gitOutput] = $workspace->command(['git', '-C', $repository, 'grep', '-F', $marker, 'HEAD']);
        assertSame(1, $gitCode);
        assertSame('', $gitOutput);

        $priorCookies = $_COOKIE;
        $_COOKIE = [];
        try {
            $app = new Application($checkout, $repository, $database);
            assertStringNotContains($marker, $this->render($app, '/'));
            assertStringNotContains($marker, $this->render($app, '/contacts/'));
            assertSame(404, http_response_code());
            $sqlite = $this->render($app, '/downloads/read_model.sqlite3');
            assertStringContains('SQLite format 3', substr($sqlite, 0, 32));
            assertStringNotContains($marker, $sqlite);

            $tar = gzdecode($this->render($app, '/downloads/repository.tar.gz'));
            assertTrue(is_string($tar) && str_contains($tar, '/records/'));
            $this->assertPrivateBookAbsent($tar, $marker);
            $images = $this->render($app, '/downloads/images.tar');
            assertStringContains('manifest.json', $images);
            $this->assertPrivateBookAbsent($images, $marker);
            if (trim((string) shell_exec('command -v zip')) !== '') {
                $this->inspectZip($workspace->root . '/repository.zip', $this->render($app, '/downloads/repository.zip'), 'records/', $marker);
            }
            if (class_exists(ZipArchive::class)) {
                $this->inspectZip($workspace->root . '/images.zip', $this->render($app, '/downloads/images.zip'), 'manifest.json', $marker);
            }

            (new StaticArtifactBuilder($checkout, $repository, $database, $artifactRoot))->build();
            assertTrue(is_file($artifactRoot . '/offline/snapshot.sqlite3'));
            foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($artifactRoot, FilesystemIterator::SKIP_DOTS)) as $file) {
                if ($file->isFile()) {
                    assertSame(false, $file->getBasename() === 'contacts.txt');
                    $this->assertPrivateBookAbsent((string) file_get_contents($file->getPathname()), $marker);
                }
            }
        } finally {
            $_COOKIE = $priorCookies;
        }
    }

    private function seedBook(string $directory, string $marker): void
    {
        $server = new ContactsTestServer($directory);
        try {
            [$status, $preview] = $server->upload('Connections.csv', "First Name,Last Name\n{$marker},Fixture\n");
            assertSame(200, $status);
            assertSame(200, $server->request('/api/confirm', json_encode(['id' => $preview['id']]))[0]);
            $book = $this->readBook($server);
            assertSame($marker, $book['contacts'][0]['first_name']);
            assertSame(200, $server->request('/api/edit', json_encode([
                'id' => $book['contacts'][0]['id'], 'revision' => $book['revision'],
                'note' => 'Notes for ' . $marker, 'last_contacted' => '2026-11-15',
            ]))[0]);
        } finally {
            $server->stop();
        }
    }

    private function readBook(ContactsTestServer $server): array
    {
        [$status, $json] = $server->request('/api/contacts');
        assertSame(200, $status);
        return json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    }

    private function render(Application $app, string $path): string
    {
        http_response_code(200);
        ob_start();
        try {
            $app->handle('GET', $path);
            return (string) ob_get_contents();
        } finally {
            ob_end_clean();
        }
    }

    private function inspectZip(string $path, string $bytes, string $expectedMember, string $marker): void
    {
        assertSame('PK', substr($bytes, 0, 2));
        file_put_contents($path, $bytes);
        $archive = new PharData($path);
        $sawExpected = false;
        foreach (new RecursiveIteratorIterator($archive) as $file) {
            $sawExpected = $sawExpected || str_contains($file->getPathname(), $expectedMember);
            $this->assertPrivateBookAbsent($file->getPathname() . "\n" . $file->getContent(), $marker);
        }
        assertTrue($sawExpected);
    }

    private function assertPrivateBookAbsent(string $contents, string $marker): void
    {
        assertStringNotContains($marker, $contents);
        assertStringNotContains('Contacts-Version: 1', $contents);
    }
}
