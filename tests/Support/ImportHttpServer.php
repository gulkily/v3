<?php

declare(strict_types=1);

final class ImportHttpServer
{
    public readonly string $url;
    private $process;

    public function __construct(string $router, string $log, array $environment = [])
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if ($socket === false) { throw new RuntimeException($error); }
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->url = 'http://' . $address;
        $this->process = proc_open([PHP_BINARY, '-S', $address, $router], [0 => ['file', '/dev/null', 'r'], 1 => ['file', $log, 'a'], 2 => ['file', $log, 'a']], $pipes, dirname($router), array_merge(getenv(), $environment));
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $connection = @stream_socket_client('tcp://' . $address, $errno, $error, 0.05);
            if ($connection !== false) { fclose($connection); return; }
            usleep(20000);
        }
        throw new RuntimeException('Import test HTTP server failed to start.');
    }

    public function __destruct()
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            proc_close($this->process);
        }
    }
}
