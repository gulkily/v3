<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Support\DevServerRegistry;

final class DevServerStartTest
{
    public function testFallbackServesRequestsRegistersActualPortAndStopsWithoutPcntl(): void
    {
        foreach ([false, true] as $disablePcntl) {
            [$port, $sockets] = $this->reserveRange();
            $workspace = new ImportTestWorkspace();
            $log = $workspace->root . '/server log.txt';
            for ($index = 3; $index < 10; $index++) { fclose($sockets[$index]); unset($sockets[$index]); }
            $server = $this->start($workspace, $port, ['--try-ports', '--no-worker', '--verbose', '--log-file=' . $log], $disablePcntl);
            try {
                $this->awaitOutput($server, 'listening on');
                $actual = $port + 3;
                assertStringContains('http://127.0.0.1:' . $actual . '/', $server->output);
                assertSame(1, substr_count($server->output, 'listening on'));
                $response = file_get_contents('http://127.0.0.1:' . $actual . '/assets/site.css');
                assertTrue(is_string($response) && strlen($response) > 100, 'fallback listener serves this checkout');
                $this->awaitOutput($server, '/assets/site.css');
                assertStringContains(':' . $actual . '  200', $server->output);
                assertStringContains('/assets/site.css', (string) file_get_contents($log));
                $entries = DevServerRegistry::active(dirname(__DIR__), $workspace->root . '/repository', $workspace->root . '/index.sqlite3');
                assertSame(1, count($entries));
                assertSame('http://127.0.0.1:' . $actual, $entries[0]['url']);
                $this->stop($server);
                assertSame(0, $server->exitCode);
                assertStringContains(':' . $actual . ' stopped', $server->output);
                assertSame([], DevServerRegistry::active(dirname(__DIR__), $workspace->root . '/repository', $workspace->root . '/index.sqlite3'));
                $socket = stream_socket_server('tcp://127.0.0.1:' . $actual);
                assertTrue(is_resource($socket), 'shutdown releases the actual port');
                fclose($socket);
            } finally {
                $this->stop($server);
                foreach ($sockets as $socket) { fclose($socket); }
            }
        }
    }

    public function testExhaustionFailsWithoutStartingWorkerAndCanRetryAfterRelease(): void
    {
        [$port, $sockets] = $this->reserveRange();
        $workspace = new ImportTestWorkspace();
        $log = $workspace->root . '/failure.log';
        $server = $this->start($workspace, $port, ['--try-ports', '--log-file=' . $log]);
        try {
            $this->awaitExit($server);
            assertSame(1, $server->exitCode);
            assertStringContains('No available port on 127.0.0.1:' . $port . '-' . ($port + 9), $server->output);
            assertStringContains('Try ./v3 start ' . ($port + 10), $server->output);
            assertStringNotContains('listening on', $server->output);
            assertStringNotContains('task worker', $server->output);
            assertStringContains('No available port', (string) file_get_contents($log));
            assertTrue(!is_file($workspace->root . '/tasks.sqlite3'), 'failed startup does not run queued tasks');
            assertSame([], DevServerRegistry::active(dirname(__DIR__), $workspace->root . '/repository', $workspace->root . '/index.sqlite3'));
            fclose($sockets[0]); unset($sockets[0]);
            $server = $this->start($workspace, $port, ['--try-ports', '--no-worker']);
            $this->awaitOutput($server, 'listening on');
            assertStringContains('http://127.0.0.1:' . $port . '/', $server->output);
        } finally {
            $this->stop($server);
            foreach ($sockets as $socket) { fclose($socket); }
        }
    }

    public function testExplicitPortCollisionReturnsFailureThroughTheLauncher(): void
    {
        [$port, $sockets] = $this->reserveRange();
        try {
            foreach ([(string) $port, ':' . $port, '127.0.0.1:' . $port] as $address) {
                $workspace = new ImportTestWorkspace();
                $server = $this->start($workspace, $port, ['--no-worker'], false, $address);
                try {
                    $this->awaitExit($server);
                    assertSame(1, $server->exitCode);
                    assertStringContains('Address already in use', $server->output);
                    assertStringNotContains('listening on', $server->output);
                    assertStringNotContains('No available port', $server->output);
                } finally { $this->stop($server); }
            }
        } finally { foreach ($sockets as $socket) { fclose($socket); } }
    }

    public function testOtherBindErrorsDoNotAdvancePorts(): void
    {
        $workspace = new ImportTestWorkspace();
        $server = $this->start($workspace, 12345, ['--try-ports', '--no-worker'], false, null, '192.0.2.1');
        try {
            $this->awaitExit($server);
            assertSame(1, $server->exitCode);
            assertStringContains('Failed to listen on 192.0.2.1:12345', $server->output);
            assertStringNotContains('No available port', $server->output);
            assertStringNotContains('listening on', $server->output);
        } finally { $this->stop($server); }
    }

    /** Reserve an ephemeral contiguous range without interfering with other tests. */
    private function reserveRange(): array
    {
        for ($attempt = 0; $attempt < 100; $attempt++) {
            $sockets = [stream_socket_server('tcp://127.0.0.1:0')];
            $port = (int) substr(strrchr(stream_socket_get_name($sockets[0], false), ':'), 1);
            if ($port < 65526) {
                for ($offset = 1; $offset < 10; $offset++) {
                    $socket = @stream_socket_server('tcp://127.0.0.1:' . ($port + $offset));
                    if ($socket === false) { break; }
                    $sockets[] = $socket;
                }
                if (count($sockets) === 10) { return [$port, $sockets]; }
            }
            foreach ($sockets as $socket) { fclose($socket); }
        }
        throw new RuntimeException('Could not reserve a test port range.');
    }

    private function start(ImportTestWorkspace $workspace, int $port, array $options, bool $disablePcntl = false, ?string $launcherAddress = null, string $host = '127.0.0.1'): object
    {
        $root = dirname(__DIR__);
        $command = $launcherAddress !== null ? [$root . '/v3', 'start', $launcherAddress, ...$options]
            : [PHP_BINARY, ...($disablePcntl ? ['-d', 'disable_functions=pcntl_async_signals,pcntl_signal'] : []),
                $root . '/scripts/dev_server.php', '--address=' . $host . ':' . $port, ...$options];
        $env = getenv();
        $env['V3_DEV_CONTROL'] = '1';
        $env['FORUM_REPOSITORY_ROOT'] = $workspace->root . '/repository';
        $env['FORUM_DATABASE_PATH'] = $workspace->root . '/index.sqlite3';
        $env['FORUM_TASK_QUEUE_DATABASE_PATH'] = $workspace->root . '/tasks.sqlite3';
        unset($env['PHP_CLI_SERVER_WORKERS']);
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['redirect', 1]], $pipes, $root, $env);
        assertTrue(is_resource($process));
        stream_set_blocking($pipes[1], false);
        return (object) ['process' => $process, 'pipes' => $pipes, 'output' => '', 'exitCode' => null, 'launcher' => $launcherAddress !== null];
    }

    private function awaitOutput(object $server, string $text): void
    {
        $deadline = microtime(true) + 10;
        do {
            $server->output .= stream_get_contents($server->pipes[1]);
            if (str_contains($server->output, $text)) { return; }
            if (!proc_get_status($server->process)['running']) { break; }
            usleep(10000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('Missing ' . $text . ': ' . $server->output);
    }

    private function awaitExit(object $server): void
    {
        $deadline = microtime(true) + 10;
        do {
            $server->output .= stream_get_contents($server->pipes[1]);
            $status = proc_get_status($server->process);
            if (!$status['running']) {
                $server->exitCode = $status['exitcode'];
                $server->output .= stream_get_contents($server->pipes[1]);
                foreach ($server->pipes as $pipe) { fclose($pipe); }
                proc_close($server->process);
                $server->process = null;
                return;
            }
            usleep(10000);
        } while (microtime(true) < $deadline);
        throw new RuntimeException('Server did not exit: ' . $server->output);
    }

    private function stop(object $server): void
    {
        if (!is_resource($server->process)) { return; }
        if ($server->launcher) { proc_terminate($server->process, 15); }
        else { @fwrite($server->pipes[0], "stop\n"); }
        $this->awaitExit($server);
    }
}
