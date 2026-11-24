<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/WebPushCryptoTest.php';

use ForumRewrite\Push\P256Key;
use ForumRewrite\Push\PushEndpointPolicy;
use ForumRewrite\Push\PushSender;
use ForumRewrite\Push\PushSendResult;
use ForumRewrite\Push\PushSubscription;
use ForumRewrite\Push\PushVapidKeys;
use ForumRewrite\Push\WebPushCrypto;

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting that values are identical. Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

/** Sending one message: the request a push service receives, and how its answer is read. */
final class PushSenderTest
{
    private const ENDPOINT = 'https://web.push.apple.com/QGuQyavXutnMH3f6';

    public function testTheRequestCarriesTokenHeadersAndABodyTheBrowserCanRead(): void
    {
        [$keys, $root] = $this->keys();
        $browserKey = P256Key::generate();
        $auth = random_bytes(16);
        $seen = [];
        $sender = new PushSender($keys, 'https://zenmemes.com', function (string $url, array $headers, string $body) use (&$seen): array {
            $seen = [$url, $headers, $body];

            return [201, ''];
        }, clock: static fn (): int => 1_800_000_000);

        $result = $sender->send(new PushSubscription(self::ENDPOINT, P256Key::rawPublicKey($browserKey), $auth), ['title' => 'ZenMemes', 'body' => 'Test'], 'high');

        assertSame([PushSendResult::SENT, 201, ''], [$result->outcome, $result->status, $result->reason]);
        assertSame(self::ENDPOINT, $seen[0]);
        assertSame(['aes128gcm', 'application/octet-stream', '86400', 'high'], [$seen[1]['Content-Encoding'], $seen[1]['Content-Type'], $seen[1]['TTL'], $seen[1]['Urgency']]);
        assertSame(1, preg_match('/^vapid t=([^,]+), k=(\S+)$/', $seen[1]['Authorization'], $match));
        assertSame(WebPushCrypto::base64UrlEncode($keys->publicKeyRaw()), $match[2]);
        assertSame(true, WebPushCrypto::verifyVapidToken($match[1], $keys->publicKeyRaw()));
        assertSame(
            '{"aud":"https://web.push.apple.com","exp":1800043200,"sub":"https://zenmemes.com"}',
            WebPushCrypto::base64UrlDecode(explode('.', $match[1])[1]),
        );
        assertSame('{"title":"ZenMemes","body":"Test"}', WebPushCryptoTest::decryptAsBrowser($seen[2], $browserKey, $auth));
        $this->remove($root);
    }

    public function testAnswersMapToSentGoneOrRefusedWithAShortReason(): void
    {
        [$keys, $root] = $this->keys();
        foreach ([
            [201, '', PushSendResult::SENT, ''],
            [410, '{"reason":"Unregistered"}', PushSendResult::GONE, 'Unregistered'],
            [404, 'Not Found', PushSendResult::GONE, 'Not Found'],
            [403, '{"reason":"BadJwtToken"}', PushSendResult::REFUSED, 'BadJwtToken'],
            [0, 'connection failed', PushSendResult::REFUSED, 'connection failed'],
        ] as [$status, $body, $outcome, $reason]) {
            $sender = new PushSender($keys, 'https://zenmemes.com', static fn (): array => [$status, $body]);
            $result = $sender->send($this->subscription(self::ENDPOINT), ['title' => 'x']);
            assertSame([$status, $outcome, $reason], [$status, $result->outcome, $result->reason]);
        }
        $this->remove($root);
    }

    public function testOnlyHttpsEndpointsOnKnownPushHostsAreCalled(): void
    {
        [$keys, $root] = $this->keys();
        $calls = 0;
        $sender = new PushSender($keys, 'https://zenmemes.com', function () use (&$calls): array {
            $calls++;

            return [201, ''];
        });
        foreach ([
            'http://web.push.apple.com/abc',
            'https://evil.example/abc',
            'https://push.apple.com.evil.example/abc',
            'https://user:pass@fcm.googleapis.com/fcm/send/abc',
            'https://127.0.0.1/abc',
        ] as $endpoint) {
            assertSame([$endpoint, PushSendResult::REFUSED, 'unknown push service'], [$endpoint, ...$this->outcome($sender->send($this->subscription($endpoint), ['title' => 'x']))]);
        }
        assertSame(0, $calls);
        $policy = new PushEndpointPolicy();
        foreach (['https://fcm.googleapis.com/fcm/send/a', 'https://updates.push.services.mozilla.com/wpush/v2/a', 'https://wns2-par02p.notify.windows.com/w/?token=a', self::ENDPOINT] as $endpoint) {
            assertSame([$endpoint, true], [$endpoint, $policy->allows($endpoint)]);
        }
        assertSame('https://fcm.googleapis.com', PushEndpointPolicy::audience('https://fcm.googleapis.com/fcm/send/a'));
        $this->remove($root);
    }

    public function testTheKeyIsMadeOnceAndKeptOwnerOnly(): void
    {
        [$keys, $root] = $this->keys();
        $public = $keys->publicKeyRaw();
        $path = $root . '/state/private/push_vapid.pem';

        assertSame(true, is_file($path));
        assertSame('0600', substr(sprintf('%o', fileperms($path)), -4));
        assertSame($public, (new PushVapidKeys($path))->publicKeyRaw());
        assertSame(65, strlen($public));
        $this->remove($root);
    }

    public function testTheStreamTransportPostsAndReadsTheStatus(): void
    {
        $root = sys_get_temp_dir() . '/v3-push-transport-' . bin2hex(random_bytes(6));
        mkdir($root, 0700, true);
        file_put_contents($root . '/router.php', '<?php http_response_code(410); echo json_encode(["reason" => "Unregistered", "bytes" => strlen(file_get_contents("php://input")), "encoding" => $_SERVER["HTTP_CONTENT_ENCODING"] ?? ""]);');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $process = proc_open([PHP_BINARY, '-S', $address, $root . '/router.php'], [['pipe', 'r'], ['file', '/dev/null', 'w'], ['file', '/dev/null', 'w']], $pipes);
        try {
            $answer = [0, ''];
            for ($attempt = 0; $attempt < 50 && $answer[0] === 0; $attempt++) {
                usleep(50_000);
                $answer = PushSender::streamTransport('http://' . $address . '/push', ['Content-Encoding' => 'aes128gcm'], str_repeat('x', 100), 2.0);
            }
            assertSame(410, $answer[0]);
            assertSame(['reason' => 'Unregistered', 'bytes' => 100, 'encoding' => 'aes128gcm'], json_decode($answer[1], true));
        } finally {
            proc_terminate($process);
            fclose($pipes[0]);
            proc_close($process);
            $this->remove($root);
        }
    }

    /** @return array{0:string,1:string} */
    private function outcome(PushSendResult $result): array
    {
        return [$result->outcome, $result->reason];
    }

    private function subscription(string $endpoint): PushSubscription
    {
        return new PushSubscription($endpoint, P256Key::rawPublicKey(P256Key::generate()), random_bytes(16));
    }

    /** @return array{0:PushVapidKeys,1:string} */
    private function keys(): array
    {
        $root = sys_get_temp_dir() . '/v3-push-' . bin2hex(random_bytes(6));

        return [new PushVapidKeys($root . '/state/private/push_vapid.pem'), $root];
    }

    private function remove(string $root): void
    {
        if (!is_dir($root)) {
            return;
        }
        $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($root);
    }
}
