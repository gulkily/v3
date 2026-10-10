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
