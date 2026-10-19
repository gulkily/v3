<?php

declare(strict_types=1);

final class FrontendTestServer
{
    public readonly string $directory;
    public readonly string $url;
    private $process;
    private array $pipes = [];

    public function __construct(string $router, ?int $port = null, array $environment = [])
    {
        $this->directory = sys_get_temp_dir() . '/v3-frontend-test-' . bin2hex(random_bytes(6));
        mkdir($this->directory, 0700);
        mkdir($this->directory . '/public');
        file_put_contents($this->directory . '/public/router.php', $router);
        if ($port === null) {
            $socket = stream_socket_server('tcp://127.0.0.1:0');
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $port = (int) substr($address, strrpos($address, ':') + 1);
        }
        $this->url = 'http://127.0.0.1:' . $port;
        $this->process = proc_open([PHP_BINARY, '-S', '127.0.0.1:' . $port, '-t', $this->directory . '/public', $this->directory . '/public/router.php'],
            [['pipe', 'r'], ['file', $this->directory . '/server.log', 'a'], ['file', $this->directory . '/server.log', 'a']], $this->pipes, null, $environment + getenv());
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, 0.02);
            if ($socket !== false) { fclose($socket); return; }
            usleep(10000);
        }
        $this->close();
        throw new RuntimeException('Test frontend failed to start.');
    }

    public function pid(): int
    {
        return proc_get_status($this->process)['pid'];
    }

    public function close(): void
    {
        if (is_resource($this->process)) {
            proc_terminate($this->process);
            foreach ($this->pipes as $pipe) { if (is_resource($pipe)) { fclose($pipe); } }
            proc_close($this->process);
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) { $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname()); }
        @rmdir($this->directory);
    }
}
