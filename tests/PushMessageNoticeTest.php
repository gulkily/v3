<?php

declare(strict_types=1);

require_once __DIR__ . '/../autoload.php';

use ForumRewrite\Messaging\ApprovedUserKeyResolver;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;
use ForumRewrite\Push\P256Key;
use ForumRewrite\Push\PushNotices;
use ForumRewrite\Push\PushSubscription;
use ForumRewrite\Push\PushSubscriptionStore;

if (!function_exists('assertSame')) {
    function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        if ($expected !== $actual) {
            throw new RuntimeException(($message !== '' ? $message . ': ' : '') . 'Expected ' . var_export($expected, true) . ' but got ' . var_export($actual, true) . '.');
        }
    }
}

/** A newly accepted DM records one notice naming only its sender; replays and history sync record none. */
final class PushMessageNoticeTest
{
    private const ENVELOPE = "-----BEGIN PGP MESSAGE-----\nSecretCiphertext\n-----END PGP MESSAGE-----\n";

    private PDO $profiles;
    private PrivateMessageStore $messages;
    private PushSubscriptionStore $push;
    private PrivateMessageMailboxService $service;

    public function testANewDmRecordsOneNoticeWithOnlyTheSendersName(): void
    {
        $this->fresh();
        $this->service->send($this->viewer('openpgp:alice', 'alice'), $this->input('m-1', 'ilyag'));
        $pending = $this->push->pending();
        assertSame(1, count($pending));
        assertSame(['dm', 'm-1', 'ilyag', 'dm:alice', 'alice', '', '/messages/conversation/alice'],
            [$pending[0]['kind'], $pending[0]['source_id'], $pending[0]['recipient'], $pending[0]['tag'], $pending[0]['actor'], $pending[0]['body'], $pending[0]['url']]);
        assertSame(false, str_contains(json_encode($pending), 'SecretCiphertext'), 'never the envelope');

        // The same send again (a retried request) is the same message: no second notice.
        $this->service->send($this->viewer('openpgp:alice', 'alice'), $this->input('m-1', 'ilyag'));
        assertSame(1, count($this->push->pending()));
    }

    public function testHistorySyncAndARecipientWithoutDevicesRecordNone(): void
    {
        $this->fresh();
        $this->messages->storeEnvelope('synced', '2026-11-24T12:00:00Z', 'alice', 'ilyag', 'openpgp:alice', self::ENVELOPE);
        $this->service->send($this->viewer('openpgp:ilyag', 'ilyag'), $this->input('m-2', 'alice'));
        assertSame([], $this->push->pending());
    }

    public function testReadOnAnotherDeviceMeansSeen(): void
    {
        $this->fresh();
        $this->service->send($this->viewer('openpgp:alice', 'alice'), $this->input('m-1', 'ilyag'));
        assertSame(false, $this->messages->isSeenBy('ilyag', 'alice', 'm-1'));
        $ilyag = $this->viewer('openpgp:ilyag', 'ilyag');
        $page = $this->service->conversationPage($ilyag, 'alice');
        $this->service->acknowledge($ilyag, 'alice', $page['read_token']);
        assertSame(true, $this->messages->isSeenBy('ilyag', 'alice', 'm-1'));
        assertSame(false, $this->messages->isSeenBy('alice', 'ilyag', 'm-1'), 'only the recipient reads it');
        assertSame(false, $this->messages->isSeenBy('ilyag', 'alice', 'unknown'));
    }

    public function testAFailingNoticeStillSendsTheMessage(): void
    {
        $this->fresh(static function (): int {
            throw new RuntimeException('push store unavailable');
        });
        $message = $this->service->send($this->viewer('openpgp:alice', 'alice'), $this->input('m-1', 'ilyag'));
        assertSame('m-1', $message['message_id']);
    }

    private function fresh(?Closure $onAccepted = null): void
    {
        $this->profiles = new PDO('sqlite::memory:');
        $this->profiles->exec('CREATE TABLE profiles (identity_id TEXT PRIMARY KEY, profile_slug TEXT NOT NULL, username TEXT NOT NULL,
            username_token TEXT NOT NULL, fallback_label TEXT NOT NULL, signer_fingerprint TEXT NOT NULL, bootstrap_post_id TEXT NOT NULL,
            bootstrap_thread_id TEXT NOT NULL, public_key TEXT NOT NULL, is_approved INTEGER NOT NULL, post_count INTEGER NOT NULL, thread_count INTEGER NOT NULL)');
        foreach (['alice', 'ilyag'] as $name) {
            $this->profiles->prepare("INSERT INTO profiles (identity_id, profile_slug, username, username_token, fallback_label, signer_fingerprint,
                bootstrap_post_id, bootstrap_thread_id, public_key, is_approved, post_count, thread_count)
                VALUES (?, ?, ?, ?, '', '', '', '', 'KEY', 1, 0, 0)")
                ->execute(['openpgp:' . $name, $name, $name, $name]);
        }
        $this->messages = new PrivateMessageStore(new PDO('sqlite::memory:'));
        $this->push = new PushSubscriptionStore(new PDO('sqlite::memory:'));
        $this->push->save(new PushSubscription('https://web.push.apple.com/a', P256Key::rawPublicKey(P256Key::generate()), random_bytes(16)), 'ilyag', 'openpgp:ilyag', 's', time());
        $notices = new PushNotices($this->profiles, fn (): PushSubscriptionStore => $this->push, 'https://zenmemes.com');
        $this->service = new PrivateMessageMailboxService(
            $this->messages,
            $this->profiles,
            new ApprovedUserKeyResolver(),
            null,
            $onAccepted ?? static fn (string $id, string $sender, string $recipient): int => $notices->recordMessage($id, $sender, $recipient),
        );
    }

    /** @return array<string, mixed> */
    private function viewer(string $identityId, string $usernameToken): array
    {
        return ['identity_id' => $identityId, 'username_token' => $usernameToken, 'is_approved' => 1];
    }

    /** @return array<string, string> */
    private function input(string $messageId, string $recipient): array
    {
        return ['message_id' => $messageId, 'recipient_username_token' => $recipient, 'encrypted_envelope' => self::ENVELOPE];
    }
}
