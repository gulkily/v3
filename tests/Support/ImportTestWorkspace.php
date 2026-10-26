<?php

declare(strict_types=1);

final class ImportTestWorkspace
{
    public readonly string $root;

    public function __construct()
    {
        $this->root = sys_get_temp_dir() . '/forum-import-test-' . bin2hex(random_bytes(6));
        mkdir($this->root, 0700, true);
    }

    public function repository(string $name, bool $fixture = false): string
    {
        $path = $this->root . '/' . $name;
        mkdir($path . '/records', 0700, true);
        if ($fixture) {
            $this->command(['cp', '-R', __DIR__ . '/../fixtures/parity_minimal_v1/records/.', $path . '/records']);
        }
        return $path;
    }

    public function put(string $path, string $contents): void
    {
        if (!is_dir(dirname($path))) {
            mkdir(dirname($path), 0700, true);
        }
        file_put_contents($path, $contents);
    }

    /** Existing cryptographically signed fixture, with its public identity and key. */
    public function signedLabel(string $root, array $suffixes = ['.asc']): string
    {
        $fixtures = __DIR__ . '/../fixtures/openpgp_signature/';
        $fingerprint = '066236E516A3B74EC51B3B423A26034600DE67FD';
        $id = strtolower($fingerprint);
        $key = file_get_contents($fixtures . 'reaction-public-key.asc');
        $this->put($root . '/records/public-keys/openpgp-' . $fingerprint . '.asc', $key);
        $this->put($root . '/records/identity/identity-openpgp-' . $id . '.txt',
            "Post-ID: identity-openpgp-{$id}\nBoard-Tags: identity\nSubject: identity bootstrap\nIdentity-ID: openpgp:{$id}\nSigner-Fingerprint: {$fingerprint}\nBootstrap-By-Post: root-001\nBootstrap-By-Thread: root-001\n\n" . $key);
        $path = 'records/thread-labels/thread-label-20260415153000-ab12cd34.txt';
        $this->put($root . '/' . $path, file_get_contents($fixtures . 'signed-label.txt'));
        foreach ($suffixes as $suffix) {
            $this->put($root . '/' . $path . $suffix, file_get_contents($fixtures . 'signed-label.txt.asc'));
        }
        return $path;
    }

    public function post(string $id, string $extra = '', string $body = 'Imported body'): string
    {
        return "Post-ID: {$id}\nCreated-At: 2026-04-10T12:00:00Z\nBoard-Tags: general\n{$extra}Subject: Imported {$id}\n\n{$body}\n";
    }

    public function command(array $command): array
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        fclose($pipes[0]);
        $out = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return [proc_close($process), $out . $err];
    }

    public function git(string $repository): void
    {
        foreach ([['init', '-q'], ['config', 'user.email', 'import-test@example.invalid'], ['config', 'user.name', 'Import Test'], ['add', '.'], ['commit', '-qm', 'Seed', '--allow-empty']] as $args) {
            [$code, $output] = $this->command(['git', '-C', $repository, ...$args]);
            if ($code !== 0) {
                throw new RuntimeException($output);
            }
        }
    }

    public function __destruct()
    {
        $this->command(['rm', '-rf', '--', $this->root]);
    }
}
