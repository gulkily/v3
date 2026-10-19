<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Support\FrontendProbe;

final class FrontendProbeTest
{
    public function testReportsNotRunningForClosedPort(): void
    {
        $port = $this->freePort();

        $result = (new FrontendProbe())->probe('http://127.0.0.1:' . $port, 1.0);

        assertSame('not_running', $result['status']);
        assertSame(null, $result['latency_ms']);
    }

    public function testReportsTimedOutForListenerThatNeverResponds(): void
    {
        $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        assertSame(true, $server !== false);
        try {
            $port = (int) substr((string) stream_socket_get_name($server, false), strrpos((string) stream_socket_get_name($server, false), ':') + 1);
            $start = hrtime(true);

            $result = (new FrontendProbe())->probe('http://127.0.0.1:' . $port, 0.5);
            $elapsed = (hrtime(true) - $start) / 1e9;

            assertSame('timed_out', $result['status']);
            assertStringContains('busy or hung', $result['detail']);
            assertSame(true, $elapsed < 2.0);
        } finally {
            fclose($server);
        }
    }

    public function testReportsRespondingWithLatencyForRunningServer(): void
    {
        $directory = sys_get_temp_dir() . '/forum-frontend-probe-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        file_put_contents($directory . '/router.php', "<?php echo 'ok';\n");
        $port = $this->freePort();
        $process = proc_open(
            [PHP_BINARY, '-S', '127.0.0.1:' . $port, $directory . '/router.php'],
            [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
            $pipes,
        );
        try {
            $result = ['status' => 'not_running'];
            for ($attempt = 0; $attempt < 20 && $result['status'] !== 'responding'; $attempt++) {
                usleep(100000);
                $result = (new FrontendProbe())->probe('http://127.0.0.1:' . $port, 1.0);
            }

            assertSame('responding', $result['status']);
            assertSame(true, is_int($result['latency_ms']));
        } finally {
            proc_terminate($process);
            proc_close($process);
            @unlink($directory . '/router.php');
            @rmdir($directory);
        }
    }

    public function testRejectsInvalidUrl(): void
    {
        assertSame('unavailable', (new FrontendProbe())->probe('nonsense', 1.0)['status']);
    }

    private function freePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $name = (string) stream_socket_get_name($server, false);
        fclose($server);

        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
