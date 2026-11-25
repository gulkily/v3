<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';
require_once __DIR__ . '/WebPushCryptoTest.php';

use ForumRewrite\Push\P256Key;
use ForumRewrite\Push\PushDelivery;
use ForumRewrite\Push\PushNotifier;
use ForumRewrite\Push\PushSender;
use ForumRewrite\Push\PushSubscription;
use ForumRewrite\Push\PushSubscriptionStore;
use ForumRewrite\Push\PushVapidKeys;

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

/** The DM and reply send pass: every device, the hold window, the per-member cap, read and gone devices. */
final class PushNotifierTest
{
    private int $now = 1_800_000_000;
    private PushSubscriptionStore $store;
    /** @var list<array{endpoint:string,payload:array<string,mixed>}> */
    private array $sent = [];
    /** @var array<string, array{0:int,1:string}> endpoint => answer */
    private array $answers = [];
    /** @var array<string, string> session key => identity */
    private array $sessions = [];
    /** @var array<string, true> source IDs read on the server */
    private array $read = [];
    /** @var array<string, array{0:OpenSSLAsymmetricKey,1:string}> endpoint => browser key, auth */
    private array $browsers = [];
    private ?PushVapidKeys $keys = null;
    private string $root = '';

    private function fresh(): void
    {
        $this->sent = [];
        $this->answers = [];
        $this->sessions = [];
        $this->read = [];
        $this->browsers = [];
        $this->store = new PushSubscriptionStore(new PDO('sqlite::memory:'));
        $this->root = sys_get_temp_dir() . '/v3-push-notifier-' . bin2hex(random_bytes(6));
        $this->keys = new PushVapidKeys($this->root . '/push_vapid.pem');
    }

    public function testAReplyReachesEveryDeviceOfTheMemberOnce(): void
    {
        $this->fresh();
        foreach (['a', 'b', 'c'] as $device) {
            $this->device('ilyag', $device);
        }
        $this->device('someone', 'd');
        $this->reply('reply-1', 'ilyag', 'thread-1', 'claude', 'First line of the reply');

        $counts = $this->run();
        assertSame([1, 3], [$counts['sent'], $counts['devices']]);
        assertSame(['https://web.push.apple.com/a', 'https://web.push.apple.com/b', 'https://web.push.apple.com/c'], array_column($this->sent, 'endpoint'));
        assertSame(['title' => 'claude replied', 'body' => 'First line of the reply', 'url' => '/posts/reply-1', 'tag' => 'thread:thread-1'], $this->sent[0]['payload']);

        // Nothing is sent twice, and the same event recorded again is ignored.
        assertSame(false, $this->store->addPending($this->replyNotice('reply-1', 'ilyag', 'thread-1', 'claude', 'x'), $this->now));
        assertSame(0, $this->run()['sent']);
        assertSame(3, count($this->sent));
        $this->cleanup();
    }

    public function testFollowUpsInOneThreadAreHeldAndSentAsOneReplacement(): void
    {
        $this->fresh();
        $this->device('ilyag', 'a');
        $this->reply('reply-1', 'ilyag', 'thread-1', 'claude', 'one');
        $this->run();
        $this->now += 60;
        $this->reply('reply-2', 'ilyag', 'thread-1', 'guest', 'two');
        $this->now += 30;
        $this->reply('reply-3', 'ilyag', 'thread-1', 'claude', 'three');
        // Another thread is not held by the first one.
        $this->reply('reply-4', 'ilyag', 'thread-2', 'claude', 'four');

        $counts = $this->run();
        assertSame([1, 2], [$counts['sent'], $counts['held']]);
        assertSame('thread:thread-2', $this->sent[1]['payload']['tag']);

        $this->now += PushNotifier::HOLD_SECONDS;
        assertSame(2, $this->run()['sent']);
        assertSame(['title' => '2 replies', 'body' => 'claude, guest', 'url' => '/posts/reply-3', 'tag' => 'thread:thread-1'], $this->sent[2]['payload']);
        assertSame(3, count($this->sent));
        $this->cleanup();
    }

    public function testABurstAcrossManyThreadsStaysWithinTheCap(): void
    {
        $this->fresh();
        $this->device('ilyag', 'a');
        for ($index = 1; $index <= 10; $index++) {
            $this->reply('reply-' . $index, 'ilyag', 'thread-' . $index, 'claude', 'line ' . $index);
        }

        $this->run();
        assertSame(PushNotifier::CAP_SENDS + 1, count($this->sent), 'six notices and one folded notice');
        $last = $this->sent[count($this->sent) - 1]['payload'];
        assertSame(['ZenMemes', '4 new replies · claude', 'more'], [$last['title'], $last['body'], $last['tag']]);

        // More replies inside the window wait for the folded notice's own hold.
        $this->now += 60;
        $this->reply('reply-11', 'ilyag', 'thread-11', 'claude', 'eleven');
        assertSame([0, 1], [$this->run()['sent'], $this->countPending()]);
        $this->now += PushNotifier::CAP_WINDOW_SECONDS;
        assertSame(1, $this->run()['sent']);
        assertSame('claude replied', $this->sent[count($this->sent) - 1]['payload']['title']);
        $this->cleanup();
    }

    public function testDmsNameOnlyTheSenderAndReadNoticesAreDropped(): void
    {
        $this->fresh();
        $this->device('ilyag', 'a');
        $this->store->addPending(['kind' => 'dm', 'source_id' => 'm-1', 'recipient' => 'ilyag', 'tag' => 'dm:friend', 'actor' => 'friend', 'body' => '', 'url' => '/messages/friend', 'site' => 'https://zenmemes.com'], $this->now);
        $this->run();
        assertSame(['title' => 'friend', 'body' => 'New message', 'url' => '/messages/friend', 'tag' => 'dm:friend'], $this->sent[0]['payload']);

        $this->now += 120;
        $this->store->addPending(['kind' => 'dm', 'source_id' => 'm-2', 'recipient' => 'ilyag', 'tag' => 'dm:friend', 'actor' => 'friend', 'body' => '', 'url' => '/messages/friend', 'site' => 'https://zenmemes.com'], $this->now);
        $this->read['m-2'] = true;
        $this->now += PushNotifier::HOLD_SECONDS;
        $counts = $this->run();
        assertSame([0, 1, 1], [$counts['sent'], $counts['read'], count($this->sent)]);
        $this->cleanup();
    }

    public function testSignedOutGoneAndOldNoticesAreNotSent(): void
    {
        $this->fresh();
        $this->device('ilyag', 'a');
        $this->device('ilyag', 'b');
        $this->device('ilyag', 'c');
        unset($this->sessions['session-b']);
        $this->answers['https://web.push.apple.com/c'] = [410, ''];
        $this->reply('reply-1', 'ilyag', 'thread-1', 'claude', 'one');
        $counts = $this->run();
        assertSame([1, 1], [$counts['sent'], $counts['devices']]);
        assertSame(['https://web.push.apple.com/a'], array_map(static fn (array $device): string => $device['endpoint'], $this->store->devicesFor('ilyag')));

        // Older than a day: dropped, never sent late. With no devices left: dropped too.
        $this->reply('reply-2', 'ilyag', 'thread-2', 'claude', 'two');
        $this->now += PushNotifier::MAX_AGE_SECONDS + 1;
        $counts = $this->run();
        assertSame([0, 1], [$counts['sent'], $counts['dropped']]);
        $this->reply('reply-3', 'nobody', 'thread-3', 'claude', 'three');
        assertSame(1, $this->run()['dropped']);
        assertSame(0, $this->countPending());
        $this->cleanup();
    }

    public function testOnlyOneSenderRunsAtATime(): void
    {
        $this->fresh();
        $this->device('ilyag', 'a');
        $this->reply('reply-1', 'ilyag', 'thread-1', 'claude', 'one');
        assertSame(true, $this->store->takeLease('other', $this->now, 60));
        assertSame(true, $this->run()['busy']);
        assertSame([0, 1], [count($this->sent), $this->countPending()]);
        $this->now += 61;
        assertSame(1, $this->run()['sent']);
        $this->cleanup();
    }

    /** @return array{sent:int,held:int,read:int,dropped:int,devices:int,busy:bool} */
    private function run(): array
    {
        $notifier = new PushNotifier($this->store, function (string $site): PushDelivery {
            assertSame('https://zenmemes.com', $site);
            return new PushDelivery($this->store, new PushSender($this->keys, $site, function (string $url, array $headers, string $body): array {
                $answer = $this->answers[$url] ?? [201, ''];
                if ($answer[0] === 201) {
                    // Read what each device got the way its browser would.
                    [$key, $auth] = $this->browsers[$url];
                    $this->sent[] = ['endpoint' => $url, 'payload' => json_decode(WebPushCryptoTest::decryptAsBrowser($body, $key, $auth), true)];
                }
                return $answer;
            }), fn (string $key): string => $this->sessions[$key] ?? '');
        }, fn (array $row): bool => isset($this->read[(string) $row['source_id']]));
        $counts = $notifier->sendDue($this->now, 'queue');

        return $counts;
    }

    private function device(string $username, string $name): void
    {
        $endpoint = 'https://web.push.apple.com/' . $name;
        $key = P256Key::generate();
        $auth = random_bytes(16);
        $this->browsers[$endpoint] = [$key, $auth];
        $this->sessions['session-' . $name] = 'openpgp:' . $username;
        $this->store->save(new PushSubscription($endpoint, P256Key::rawPublicKey($key), $auth), $username, 'openpgp:' . $username, 'session-' . $name, $this->now++);
    }

    private function reply(string $postId, string $recipient, string $threadId, string $actor, string $body): void
    {
        assertSame(true, $this->store->addPending($this->replyNotice($postId, $recipient, $threadId, $actor, $body), $this->now));
    }

    /** @return array<string, string> */
    private function replyNotice(string $postId, string $recipient, string $threadId, string $actor, string $body): array
    {
        return ['kind' => 'reply', 'source_id' => $postId, 'recipient' => $recipient, 'tag' => 'thread:' . $threadId, 'actor' => $actor, 'body' => $body, 'url' => '/posts/' . $postId, 'site' => 'https://zenmemes.com'];
    }

    private function countPending(): int
    {
        return count($this->store->pending());
    }

    private function cleanup(): void
    {
        foreach (glob($this->root . '/*') ?: [] as $file) {
            @unlink($file);
        }
        @rmdir($this->root);
    }
}
