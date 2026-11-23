<?php

declare(strict_types=1);

final class ContactsTestServer
{
    private $process;
    private array $pipes = [];
    public readonly string $url;
    public readonly string $credential;

    public function __construct(string $directory, array $options = [])
    {
        $project = dirname(__DIR__, 2);
        $this->process = proc_open(
            [$project . '/v3', 'contacts', '--data-dir=' . $directory, ...$options],
            [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]],
            $this->pipes,
            $project,
        );
        stream_set_blocking($this->pipes[1], false);
        $deadline = microtime(true) + 5;
        $output = '';
        while (!str_contains($output, "\n") && !feof($this->pipes[1]) && microtime(true) < $deadline) {
            $output .= fread($this->pipes[1], 8192);
            usleep(10000);
        }
        if (!preg_match('~(http://127\.0\.0\.1:\d+)/#(\S+)~', $output, $match)) {
            $this->stop();
            throw new RuntimeException('Contacts did not print a launch URL: ' . trim($output));
        }
        [, $this->url, $this->credential] = $match;
    }

    public function request(string $path, ?string $body = null, array $headers = [], bool $authorize = true): array
    {
        if ($authorize) {
            $headers += ['Authorization' => 'Bearer ' . $this->credential];
        }
        if ($body !== null) {
            $headers += ['Origin' => $this->url, 'Content-Type' => 'application/json'];
        }
        $lines = [];
        foreach ($headers as $name => $value) {
            $lines[] = $name . ': ' . $value;
        }
        $response = file_get_contents($this->url . $path, false, stream_context_create([
            'http' => [
                'method' => $body === null ? 'GET' : 'POST',
                'header' => $lines,
                'content' => $body ?? '',
                'ignore_errors' => true,
                'timeout' => 5,
            ],
        ]));
        $status = explode(' ', $http_response_header[0] ?? '')[1] ?? null;
        if ($status === null) {
            throw new RuntimeException('Contacts did not return an HTTP status.');
        }
        return [(int) $status, (string) $response, $http_response_header];
    }

    public function stop(): void
    {
        if (!is_resource($this->process)) {
            return;
        }
        proc_terminate($this->process);
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        $deadline = microtime(true) + 3;
        while (proc_get_status($this->process)['running'] && microtime(true) < $deadline) {
            usleep(10000);
        }
        if (proc_get_status($this->process)['running']) {
            proc_terminate($this->process, 9);
        }
        proc_close($this->process);
    }
}
