<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Support\FrontendProbe;
use ForumRewrite\Support\FrontendInstance;
require_once __DIR__ . '/Support/FrontendTestServer.php';

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

    public function testProofDistinguishesInstancesAndDoesNotInitializeState(): void
    {
        $root = sys_get_temp_dir() . '/v3-proof-missing-' . bin2hex(random_bytes(6));
        $autoload = var_export(dirname(__DIR__) . '/autoload.php', true);
        $router = '<?php require ' . $autoload . '; (new \\ForumRewrite\\Host\\FrontController('
            . implode(',', array_map(static fn ($path) => var_export($path, true), [$root, $root . '/repo', $root . '/db', $root . '/html']))
            . '))->handle($_SERVER["REQUEST_METHOD"], $_SERVER["REQUEST_URI"]);';
        $server = new FrontendTestServer($router);
        try {
            $identity = FrontendInstance::identity($root, $root . '/repo', $root . '/db');
            assertSame('responding', (new FrontendProbe())->probe($server->url, 1, $identity)['status']);
            assertSame('unavailable', (new FrontendProbe())->probe($server->url, 1,
                FrontendInstance::identity($root . '-other', $root . '/repo', $root . '/db'))['status']);
            assertSame('unavailable', (new FrontendProbe())->probe($server->url, 1,
                FrontendInstance::identity($root, $root . '/other-repo', $root . '/db'))['status']);
            $headers = get_headers($server->url . FrontendInstance::PATH, true);
            assertSame('no-store, private', $headers['Cache-Control']);
            assertSame(false, isset($headers['Set-Cookie']));
            assertSame(false, is_dir($root));
        } finally { $server->close(); }
    }

    public function testAuthorityQueryAndUnrelatedResponses(): void
    {
        $server = new FrontendTestServer('<?php if ($_SERVER["HTTP_HOST"] !== "localhost:" . $_SERVER["SERVER_PORT"] || $_SERVER["REQUEST_URI"] !== "/health?check=yes") { http_response_code(500); }');
        try {
            $url = str_replace('127.0.0.1', 'localhost', $server->url) . '/health?check=yes';
            assertSame('responding', (new FrontendProbe())->probe($url, 1)['status']);
            assertSame('unavailable', (new FrontendProbe())->probe($server->url, 1, str_repeat('a', 64))['status']);
        } finally { $server->close(); }
        $redirect = new FrontendTestServer('<?php header("Location: http://unrelated.invalid/");');
        try {
            assertSame('unavailable', (new FrontendProbe())->probe($redirect->url, 1, str_repeat('a', 64))['status']);
        } finally { $redirect->close(); }
    }

    public function testHttpsUsesConfiguredHostAndVerifiesCertificate(): void
    {
        $directory = sys_get_temp_dir() . '/v3-tls-' . bin2hex(random_bytes(6));
        mkdir($directory, 0700);
        file_put_contents($directory . '/openssl.cnf', "[req]\ndistinguished_name=dn\nx509_extensions=ext\n[dn]\n[ext]\nsubjectAltName=DNS:localhost\nbasicConstraints=critical,CA:TRUE\n");
        $options = ['config' => $directory . '/openssl.cnf', 'private_key_bits' => 2048];
        $key = openssl_pkey_new($options);
        $csr = openssl_csr_new(['commonName' => 'localhost'], $key, $options);
        $cert = openssl_csr_sign($csr, null, $key, 1, $options);
        openssl_pkey_export_to_file($key, $directory . '/key.pem');
        openssl_x509_export_to_file($cert, $directory . '/cert.pem');
        $port = $this->freePort();
        $python = <<<'PYTHON'
import http.server, ssl, sys
class Handler(http.server.BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200 if self.headers['Host'] == 'localhost:' + sys.argv[1] and self.path == '/health?check=yes' else 500)
        self.end_headers()
server = http.server.HTTPServer(('127.0.0.1', int(sys.argv[1])), Handler)
context = ssl.SSLContext(ssl.PROTOCOL_TLS_SERVER)
context.load_cert_chain(sys.argv[2] + '/cert.pem', sys.argv[2] + '/key.pem')
server.socket = context.wrap_socket(server.socket, server_side=True)
server.serve_forever()
PYTHON;
        $process = proc_open(['python3', '-c', $python, (string) $port, $directory],
            [['pipe', 'r'], ['file', $directory . '/server.log', 'a'], ['file', $directory . '/server.log', 'a']], $pipes);
        try {
            for ($i = 0; $i < 100; $i++) {
                $socket = @stream_socket_client('tcp://127.0.0.1:' . $port, $errno, $error, 0.02);
                if ($socket !== false) { fclose($socket); break; }
                usleep(10000);
            }
            $script = 'require ' . var_export(dirname(__DIR__) . '/autoload.php', true)
                . '; echo json_encode((new \\ForumRewrite\\Support\\FrontendProbe)->probe($argv[1], 1));';
            foreach (['localhost' => 'responding', '127.0.0.1' => 'not_running'] as $host => $expected) {
                $probe = proc_open([PHP_BINARY, '-d', 'openssl.cafile=' . $directory . '/cert.pem', '-r', $script,
                    'https://' . $host . ':' . $port . '/health?check=yes'], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $probePipes);
                fclose($probePipes[0]);
                $result = json_decode(stream_get_contents($probePipes[1]), true, 512, JSON_THROW_ON_ERROR);
                $error = stream_get_contents($probePipes[2]);
                fclose($probePipes[1]); fclose($probePipes[2]);
                assertSame(0, proc_close($probe));
                assertSame('', $error);
                assertSame($expected, $result['status']);
            }
        } finally {
            proc_terminate($process); fclose($pipes[0]); proc_close($process);
            foreach (glob($directory . '/*') as $file) { unlink($file); }
            rmdir($directory);
        }
    }

    private function freePort(): int
    {
        $server = stream_socket_server('tcp://127.0.0.1:0');
        $name = (string) stream_socket_get_name($server, false);
        fclose($server);

        return (int) substr($name, strrpos($name, ':') + 1);
    }
}
