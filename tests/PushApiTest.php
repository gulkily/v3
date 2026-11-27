<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ImportTestWorkspace.php';

use ForumRewrite\Http\PushApiController;
use ForumRewrite\Http\RouteServices;
use ForumRewrite\Images\ImageBackup;
use ForumRewrite\Offline\PublicOfflineSnapshotBuilder;
use ForumRewrite\Push\P256Key;
use ForumRewrite\Push\PushSender;
use ForumRewrite\Push\PushStorageConfig;
use ForumRewrite\Push\PushSubscription;
use ForumRewrite\Push\PushSubscriptionStore;
use ForumRewrite\Push\PushVapidKeys;
use ForumRewrite\Push\WebPushCrypto;
use ForumRewrite\Session\ViewerSessionCookie;
use ForumRewrite\Session\ViewerSessions;
use ForumRewrite\Session\ViewerSessionStore;
use ForumRewrite\Support\FeatureFlags\FeatureFlagEvaluator;
use ForumRewrite\View\TemplateRenderer;

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException('Failed asserting that values are identical. Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

/** Preferences → Notifications routes: who may use them, what they store, and when a device is dropped. */
final class PushApiTest
{
    private const ENDPOINT = 'https://web.push.apple.com/QGuQyavXutnMH3f6';
    private const MARKER = 'push-endpoint-marker-5c21';

    private int $now = 1_800_000_000;
    private ViewerSessionStore $sessions;
    private PushSubscriptionStore $store;
    private string $root;
    /** @var list<array{0:string,1:array<string,string>,2:string}> */
    private array $sent = [];
    /** @var array{0:int,1:string} */
    private array $answer = [201, ''];

    public function __construct()
    {
        $this->fresh();
    }

    /** The runner may reuse one instance for every test, so each test starts from here. */
    private function fresh(): void
    {
        $this->sent = [];
        $this->answer = [201, ''];
        $this->sessions = new ViewerSessionStore(new PDO('sqlite::memory:'), fn (): int => $this->now);
        $this->store = new PushSubscriptionStore(new PDO('sqlite::memory:'));
        $this->root = sys_get_temp_dir() . '/v3-push-api-' . bin2hex(random_bytes(6));
    }

    public function testStoreAddsReplacesCapsAndDeletes(): void
    {
        $store = new PushSubscriptionStore(new PDO('sqlite::memory:'));
        $store->save($this->subscription(self::ENDPOINT), 'Ilyag', 'openpgp:aaa', 'session-1', 100);
        assertSame('ilyag', $store->find(self::ENDPOINT)['username']);

        // A second member on the same device takes the endpoint over.
        $store->save($this->subscription(self::ENDPOINT), 'guest2', 'openpgp:bbb', 'session-2', 101);
        assertSame(['guest2', 'openpgp:bbb', 'session-2'], array_values(array_intersect_key($store->find(self::ENDPOINT), array_flip(['username', 'identity_id', 'session_key']))));
        assertSame(0, $store->countForUsername('ilyag'));

        for ($index = 0; $index < PushSubscriptionStore::MAX_DEVICES_PER_USERNAME + 2; $index++) {
            $store->save($this->subscription(self::ENDPOINT . '-' . $index), 'ilyag', 'openpgp:aaa', 'session-1', 200 + $index);
        }
        assertSame(PushSubscriptionStore::MAX_DEVICES_PER_USERNAME, $store->countForUsername('ilyag'));
        assertSame(null, $store->find(self::ENDPOINT . '-0'));
        assertSame(true, $store->find(self::ENDPOINT . '-11') !== null);

        assertSame(PushSubscriptionStore::MAX_DEVICES_PER_USERNAME, $store->deleteForSession('session-1'));
        assertSame(true, $store->delete(self::ENDPOINT));
        assertSame(false, $store->delete(self::ENDPOINT));
    }

    public function testAMemberTurnsOnSendsATestAndTurnsOff(): void
    {
        $this->fresh();
        $token = $this->sessions->signIn('openpgp:aaa')->token;
        $browserKey = P256Key::generate();
        $auth = random_bytes(16);
        $keys = ['p256dh' => WebPushCrypto::base64UrlEncode(P256Key::rawPublicKey($browserKey)), 'auth' => WebPushCrypto::base64UrlEncode($auth)];

        [$status, $payload] = $this->call('key', $token, [], 'GET');
        assertSame([200, 87], [$status, strlen((string) $payload['public_key'])]);
        [$status, $payload] = $this->call('subscribe', $token, ['endpoint' => self::ENDPOINT, 'keys' => $keys]);
        assertSame([200, 'ok'], [$status, $payload['status']]);
        assertSame('ilyag', $this->store->find(self::ENDPOINT)['username']);

        [$status, $payload] = $this->call('test', $token, ['endpoint' => self::ENDPOINT]);
        assertSame([200, 'sent', 201], [$status, $payload['outcome'], $payload['http_status']]);
        assertSame(self::ENDPOINT, $this->sent[0][0]);
        assertSame(PushApiController::TEST_PAYLOAD, json_decode(WebPushCryptoTest::decryptAsBrowser($this->sent[0][2], $browserKey, $auth), true));

        [$status] = $this->call('unsubscribe', $token, ['endpoint' => self::ENDPOINT]);
        assertSame(200, $status);
        assertSame(null, $this->store->find(self::ENDPOINT));
        [, $payload] = $this->call('test', $token, ['endpoint' => self::ENDPOINT]);
        assertSame(['gone', 'not turned on', 1], [$payload['outcome'], $payload['reason'], count($this->sent)]);
        $this->cleanup();
    }

    public function testGuardsRefuseSignedOutCrossSiteBadInputAndUnknownServices(): void
    {
        $this->fresh();
        $token = $this->sessions->signIn('openpgp:aaa')->token;
        $keys = ['p256dh' => WebPushCrypto::base64UrlEncode(P256Key::rawPublicKey(P256Key::generate())), 'auth' => WebPushCrypto::base64UrlEncode(random_bytes(16))];

        assertSame(401, $this->call('subscribe', '', ['endpoint' => self::ENDPOINT, 'keys' => $keys], profile: null)[0]);
        assertSame(401, $this->call('key', '', [], 'GET', profile: null)[0]);
        assertSame(405, $this->call('subscribe', $token, [], 'GET')[0]);
        assertSame(403, $this->call('subscribe', $token, ['endpoint' => self::ENDPOINT, 'keys' => $keys], header: '')[0]);
        assertSame(403, $this->call('subscribe', $token, ['endpoint' => self::ENDPOINT, 'keys' => $keys], fetchSite: 'cross-site')[0]);
        assertSame(400, $this->call('subscribe', $token, ['endpoint' => self::ENDPOINT, 'keys' => ['p256dh' => 'AAAA', 'auth' => 'AAAA']])[0]);
        assertSame(422, $this->call('subscribe', $token, ['endpoint' => 'https://evil.example/x', 'keys' => $keys])[0]);
        assertSame(null, $this->store->find(self::ENDPOINT));
        $this->cleanup();
    }

    /** push_turn_on_diagnostics: a browser reports a failed Turn on; operators read it as text. */
    public function testAMemberReportsAFailedTurnOnAndOperatorsReadIt(): void
    {
        $this->fresh();
        $token = $this->sessions->signIn('openpgp:aaa')->token;
        $report = "Action: Turn on notifications\r\nStep: push subscribe\r\nError: Timeout: No answer after 15 s\r\n";

        assertSame(401, $this->call('report', '', ['report' => $report], profile: null)[0]);
        assertSame(403, $this->call('report', $token, ['report' => $report], header: '')[0]);
        assertSame(405, $this->call('report', $token, [], 'GET')[0]);
        assertSame(400, $this->call('report', $token, ['report' => '  '])[0]);
        assertSame(413, $this->call('report', $token, ['report' => str_repeat('x', PushSubscriptionStore::MAX_REPORT_BYTES + 1)])[0]);
        assertSame([], $this->store->reports());

        assertSame(200, $this->call('report', $token, ['report' => $report])[0]);
        $stored = $this->store->reports();
        assertSame(['ilyag', $this->now, str_replace("\r\n", "\n", $report)], [$stored[0]['username'], $stored[0]['received_at'], $stored[0]['body']]);

        for ($index = 1; $index < PushSubscriptionStore::REPORTS_PER_HOUR; $index++) {
            assertSame(200, $this->call('report', $token, ['report' => 'Step: ' . $index])[0]);
        }
        assertSame(429, $this->call('report', $token, ['report' => 'Step: one too many'])[0]);
        $this->now += 3601;
        assertSame(200, $this->call('report', $token, ['report' => 'Step: next hour'])[0]);

        [$status, , $text] = $this->call('reports-page', $token, ['operator' => false], 'GET');
        assertSame([403, false], [$status, str_contains($text, 'push subscribe')]);
        [$status, , $text] = $this->call('reports-page', $token, ['operator' => true], 'GET');
        assertSame(200, $status);
        assertSame(true, str_starts_with($text, "Report: 11\nReceived: " . gmdate('Y-m-d\TH:i:s\Z', $this->now) . "\nUsername: ilyag\nStep: next hour\n"));
        assertSame(true, str_contains($text, "Username: ilyag\nAction: Turn on notifications\nStep: push subscribe\n"));
        $this->cleanup();
    }

    public function testOnlyTheNewestReportsAreKept(): void
    {
        $store = new PushSubscriptionStore(new PDO('sqlite::memory:'));
        for ($index = 0; $index < PushSubscriptionStore::REPORTS_KEPT + 3; $index++) {
            assertSame(true, $store->addReport('member' . $index, 'Step: ' . $index, 100));
        }
        $reports = $store->reports(PushSubscriptionStore::REPORTS_KEPT + 10);
        assertSame([PushSubscriptionStore::REPORTS_KEPT, 'Step: ' . (PushSubscriptionStore::REPORTS_KEPT + 2), 'Step: 3'], [count($reports), $reports[0]['body'], $reports[count($reports) - 1]['body']]);
    }

    public function testSignOutOrAnotherSignInStopsSendingAndDropsTheDevice(): void
    {
        $this->fresh();
        $token = $this->sessions->signIn('openpgp:aaa')->token;
        $this->subscribeDevice($token);
        $this->sessions->signOut($token);
        assertSame(401, $this->call('test', $token, ['endpoint' => self::ENDPOINT])[0]);
        // Suppose sign-out's delete was missed: signing in again starts a new session, so the
        // old row no longer sends, and the test drops it.
        $again = $this->sessions->signIn('openpgp:aaa', $token)->token;
        [, $payload] = $this->call('test', $again, ['endpoint' => self::ENDPOINT]);
        assertSame(['gone', 'signed out'], [$payload['outcome'], $payload['reason']]);
        assertSame([null, 0], [$this->store->find(self::ENDPOINT), count($this->sent)]);

        // Someone else signs in on the same browser: the first member's device row stops working.
        $first = $this->sessions->signIn('openpgp:aaa')->token;
        $this->subscribeDevice($first);
        $this->now += 3600;
        $second = $this->sessions->signIn('openpgp:bbb', $first)->token;
        [, $payload] = $this->call('test', $second, ['endpoint' => self::ENDPOINT]);
        assertSame('not turned on', $payload['reason']);
        assertSame(true, $this->store->find(self::ENDPOINT) !== null, 'another member cannot test or remove it');
        assertSame('', $this->sessions->signedInIdentityForKey($this->store->find(self::ENDPOINT)['session_key']));
        $this->cleanup();
    }

    public function testAGoneDeviceIsDeletedAndARefusalIsReported(): void
    {
        $this->fresh();
        $token = $this->sessions->signIn('openpgp:aaa')->token;
        $this->subscribeDevice($token);
        $this->answer = [403, '{"reason":"BadJwtToken"}'];
        [, $payload] = $this->call('test', $token, ['endpoint' => self::ENDPOINT]);
        assertSame(['refused', 403, 'BadJwtToken'], [$payload['outcome'], $payload['http_status'], $payload['reason']]);
        assertSame(true, $this->store->find(self::ENDPOINT) !== null);

        $this->answer = [410, ''];
        [, $payload] = $this->call('test', $token, ['endpoint' => self::ENDPOINT]);
        assertSame('gone', $payload['outcome']);
        assertSame(null, $this->store->find(self::ENDPOINT));
        $this->cleanup();
    }

    public function testRoutesAnswer404WithTheFlagOff(): void
    {
        $previous = getenv('FORUM_PUSH_ENABLED');
        putenv('FORUM_PUSH_ENABLED=0');
        $databasePath = sys_get_temp_dir() . '/push-flag-off-' . bin2hex(random_bytes(6)) . '.sqlite3';
        try {
            foreach (['/api/push/key' => 'GET', '/api/push/subscribe' => 'POST', '/api/push/unsubscribe' => 'POST', '/api/push/test' => 'POST', '/api/push/report' => 'POST'] as $path => $method) {
                http_response_code(200);
                ob_start();
                (new ForumRewrite\Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $databasePath))->handle($method, $path);
                $body = (string) ob_get_clean();
                assertSame([$path, 404, 'push_disabled'], [$path, (int) http_response_code(), json_decode($body, true)['code'] ?? null]);
            }
        } finally {
            putenv($previous === false ? 'FORUM_PUSH_ENABLED' : 'FORUM_PUSH_ENABLED=' . $previous);
            @unlink($databasePath);
        }
    }

    public function testThePushStoreAndKeyNeverReachGitBackupsOrSnapshots(): void
    {
        foreach ([PushStorageConfig::databasePath(dirname(__DIR__)), PushStorageConfig::vapidKeyPath(dirname(__DIR__))] as $path) {
            $relative = substr($path, strlen(dirname(__DIR__)) + 1);
            exec('git -C ' . escapeshellarg(dirname(__DIR__)) . ' check-ignore -q ' . escapeshellarg($relative), $output, $status);
            assertSame([$relative, 0], [$relative, $status]);
        }

        $w = new ImportTestWorkspace();
        $repository = $w->repository('source');
        $w->put($repository . '/records/posts/post-1.txt', $w->post('post-1'));
        $w->git($repository);
        // Worst case: a misconfigured PUSH_DATABASE_PATH inside the content repository.
        $this->storeMarker($repository . '/push.sqlite3');
        $backup = new ImageBackup($repository, $w->root . '/index.sqlite3');
        $export = $backup->export('tar.gz');
        $plain = (string) gzdecode((string) stream_get_contents($export['stream']));
        fclose($export['stream']);
        assertSame([true, false, false], [str_contains($plain, 'post-1'), str_contains($plain, 'push.sqlite3'), str_contains($plain, self::MARKER)]);

        $root = sys_get_temp_dir() . '/push-snapshot-' . bin2hex(random_bytes(5));
        mkdir($root . '/state/private', 0777, true);
        $source = $root . '/read_model.sqlite3';
        $_COOKIE = [];
        ob_start();
        (new ForumRewrite\Application(dirname(__DIR__), __DIR__ . '/fixtures/parity_minimal_v1', $source))->handle('GET', '/');
        ob_end_clean();
        $this->storeMarker($root . '/state/private/push.sqlite3');
        (new PublicOfflineSnapshotBuilder($root))->build($source, $root . '/snapshot.sqlite3');
        assertSame(false, str_contains((string) file_get_contents($root . '/snapshot.sqlite3'), self::MARKER));
        assertSame([], (new PDO('sqlite:' . $root . '/snapshot.sqlite3'))->query("SELECT name FROM sqlite_master WHERE name LIKE 'push%'")->fetchAll(PDO::FETCH_COLUMN));
    }

    private function subscribeDevice(string $token): void
    {
        $keys = ['p256dh' => WebPushCrypto::base64UrlEncode(P256Key::rawPublicKey(P256Key::generate())), 'auth' => WebPushCrypto::base64UrlEncode(random_bytes(16))];
        assertSame(200, $this->call('subscribe', $token, ['endpoint' => self::ENDPOINT, 'keys' => $keys])[0]);
    }

    private function storeMarker(string $path): void
    {
        PushSubscriptionStore::open($path)->save($this->subscription('https://web.push.apple.com/' . self::MARKER), 'ilyag', 'openpgp:aaa', 's', 1);
    }

    private function subscription(string $endpoint): PushSubscription
    {
        return new PushSubscription($endpoint, P256Key::rawPublicKey(P256Key::generate()), random_bytes(16));
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null|false $profile false: the profile for the session's identity
     * @return array{0:int,1:array<string,mixed>,2:string}
     */
    private function call(string $action, string $token, array $data, string $method = 'POST', array|null|false $profile = false, string $header = 'ForumPush', string $fetchSite = 'same-origin'): array
    {
        $sessions = new ViewerSessions(fn (): ViewerSessionStore => $this->sessions, $token === '' ? [] : [ViewerSessionCookie::HTTPS_NAME => $token], true);
        if ($profile === false) {
            $identity = $sessions->identityId();
            $profile = $identity === '' ? null : ['identity_id' => $identity, 'username' => $identity === 'openpgp:aaa' ? 'IlyaG' : 'other'];
        }
        $services = new RouteServices(
            sys_get_temp_dir() . '/push-api-unused.sqlite3',
            new TemplateRenderer(__DIR__ . '/../templates', 'test'),
            'test',
            false,
            static fn (): ?array => null,
            __DIR__ . '/fixtures/parity_minimal_v1',
            dirname(__DIR__),
            null,
            null,
            FeatureFlagEvaluator::forApplication(__DIR__ . '/fixtures/parity_minimal_v1', dirname(__DIR__)),
            static fn (): ?array => null,
            viewerSessions: static fn (): ViewerSessions => $sessions,
        );
        $keys = new PushVapidKeys($this->root . '/push_vapid.pem');
        $controller = new PushApiController(
            $services,
            static fn (): ?array => $profile,
            fn (): PushSubscriptionStore => $this->store,
            $keys,
            fn (): PushSender => new PushSender($keys, 'https://zenmemes.com', function (string $url, array $headers, string $body): array {
                $this->sent[] = [$url, $headers, $body];

                return $this->answer;
            }),
            clock: fn (): int => $this->now,
        );

        $server = $_SERVER;
        unset($_SERVER['HTTP_ORIGIN']);
        $_SERVER['HTTP_SEC_FETCH_SITE'] = $fetchSite;
        $_SERVER['HTTP_X_REQUESTED_WITH'] = $header;
        $_SERVER['CONTENT_TYPE'] = 'application/json';
        http_response_code(200);
        ob_start();
        try {
            match ($action) {
                'key' => $controller->key($method),
                'subscribe' => $controller->subscribe($method, $data),
                'unsubscribe' => $controller->unsubscribe($method, $data),
                'report' => $controller->report($method, $data),
                'reports-page' => $controller->reportsPage((bool) ($data['operator'] ?? false)),
                default => $controller->test($method, $data),
            };
        } finally {
            $output = (string) ob_get_clean();
            $_SERVER = $server;
        }

        return [(int) http_response_code(), (array) json_decode($output, true), $output];
    }

    private function cleanup(): void
    {
        @unlink($this->root . '/push_vapid.pem');
        @unlink($this->root . '/push_vapid.pem.lock');
        @rmdir($this->root);
    }
}
