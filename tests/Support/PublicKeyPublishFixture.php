<?php
declare(strict_types=1);

use ForumRewrite\Security\GpgHomeCleaner;
use ForumRewrite\Security\OpenPgpKeyInspector;

final class PublicKeyPublishFixture
{
    public string $directory;
    public string $root;
    public string $publicKey;
    public string $secretKey;
    public string $fingerprint;
    public string $identityPath;
    public string $keyPath;
    public string $url;
    private mixed $server;

    public function __construct()
    {
        $this->directory = sys_get_temp_dir() . '/public-key-publish-' . bin2hex(random_bytes(6));
        $this->root = $this->directory . '/repository';
        foreach (['identity', 'public-keys'] as $family) mkdir($this->root . '/records/' . $family, 0700, true);
        mkdir($this->directory . '/gpg', 0700);
        $this->gpg(['--pinentry-mode', 'loopback', '--passphrase', '', '--quick-generate-key', 'publisher-fixture', 'ed25519', 'sign', '0']);
        $this->publicKey = $this->gpg(['--armor', '--export']);
        $this->secretKey = $this->gpg(['--pinentry-mode', 'loopback', '--passphrase', '', '--armor', '--export-secret-keys']);
        $this->fingerprint = (new OpenPgpKeyInspector())->inspect($this->publicKey)['fingerprint'];
        $this->keyPath = $this->root . '/records/public-keys/openpgp-' . $this->fingerprint . '.asc';
        $this->identityPath = $this->root . '/records/identity/identity-openpgp-' . strtolower($this->fingerprint) . '.txt';
        $this->source();
        $this->response(404, "profile not found\n");
        file_put_contents($this->directory . '/project', dirname(__DIR__, 2));
        copy(__DIR__ . '/public_key_publish_router.php', $this->directory . '/router.php');
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) throw new RuntimeException($error);
        $address = stream_socket_get_name($socket, false); fclose($socket);
        $this->url = 'http://' . $address;
        $this->server = proc_open([PHP_BINARY, '-S', $address, $this->directory . '/router.php'],
            [0 => ['file', '/dev/null', 'r'], 1 => ['file', $this->directory . '/server.log', 'a'], 2 => ['file', $this->directory . '/server.log', 'a']], $pipes);
        for ($i = 0; $i < 100; $i++) {
            $probe = @stream_socket_client('tcp://' . $address, $errno, $error, 0.1);
            if ($probe !== false) { fclose($probe); return; }
            usleep(10000);
        }
        throw new RuntimeException('Fixture server did not start.');
    }

    public function gpg(array $arguments): string
    {
        $process = proc_open(array_merge(['gpg', '--batch', '--no-tty', '--homedir', $this->directory . '/gpg'], $arguments),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]); fclose($pipes[2]);
        if (proc_close($process) !== 0) throw new RuntimeException('Fixture GPG failed: ' . $err);
        return $out;
    }

    public function source(?string $armor = null, string $name = 'publisher-fixture'): void
    {
        $armor ??= $this->publicKey;
        file_put_contents($this->keyPath, $armor);
        file_put_contents($this->identityPath, 'Post-ID: identity-openpgp-' . strtolower($this->fingerprint) . "\nBoard-Tags: identity\nSubject: identity bootstrap\nUsername: $name\nIdentity-ID: openpgp:" . strtolower($this->fingerprint)
            . "\nSigner-Fingerprint: {$this->fingerprint}\nBootstrap-By-Post: fixture\nBootstrap-By-Thread: fixture\n\n" . $armor);
    }

    public function response(int $status, string $body, string $type = 'text/plain'): void
    {
        file_put_contents($this->directory . '/response', serialize([$status, $body, $type]));
    }

    public function profile(string $name = 'publisher-fixture', string $retired = 'no'): string
    {
        return 'Profile-Slug: openpgp-' . strtolower($this->fingerprint) . "\nIdentity-ID: openpgp:" . strtolower($this->fingerprint) . "\nUsername: $name\nApproved: no\nRetired: $retired\n";
    }

    public function command(array $arguments = [], ?string $destination = null, ?string $repositoryRoot = null): array
    {
        $process = proc_open(array_merge(['bash', dirname(__DIR__, 2) . '/v3', 'publish-keys', $destination ?? $this->url, '--repository-root=' . ($repositoryRoot ?? $this->root)], $arguments),
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, dirname(__DIR__, 2));
        $out = stream_get_contents($pipes[1]); $err = stream_get_contents($pipes[2]); fclose($pipes[1]); fclose($pipes[2]);
        return [proc_close($process), $out, $err];
    }

    public function requests(): string { return is_file($this->directory . '/requests') ? file_get_contents($this->directory . '/requests') : ''; }

    public function close(): void
    {
        if (is_resource($this->server)) { proc_terminate($this->server); proc_close($this->server); }
        GpgHomeCleaner::dispose($this->directory . '/gpg');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($iterator as $file) { if ($file->isLink() || !$file->isDir()) unlink($file->getPathname()); else rmdir($file->getPathname()); }
        rmdir($this->directory);
    }
}
