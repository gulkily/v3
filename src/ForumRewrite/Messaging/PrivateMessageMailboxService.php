<?php

declare(strict_types=1);

namespace ForumRewrite\Messaging;

use InvalidArgumentException;
use PDO;
use RuntimeException;

final class PrivateMessageMailboxService
{
    public function __construct(
        private readonly PrivateMessageStore $store,
        private readonly PDO $readPdo,
        private readonly ApprovedUserKeyResolver $keyResolver = new ApprovedUserKeyResolver(),
    ) {
    }

    /**
     * @param array<string, mixed> $viewer
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function send(array $viewer, array $input): array
    {
        $viewer = $this->approvedViewer($viewer);
        $messageId = trim((string) ($input['message_id'] ?? ''));
        $recipientUsernameToken = strtolower(trim((string) ($input['recipient_username_token'] ?? '')));
        $encryptedEnvelope = (string) ($input['encrypted_envelope'] ?? '');

        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/', $messageId) !== 1) {
            throw new InvalidArgumentException('Message ID is invalid.');
        }
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $recipientUsernameToken) !== 1) {
            throw new InvalidArgumentException('Recipient username is invalid.');
        }
        if (!$this->isArmoredMessage($encryptedEnvelope)) {
            throw new InvalidArgumentException('Encrypted message envelope is invalid.');
        }
        $attempt = [
            'message_id' => $messageId,
            'created_at' => gmdate('Y-m-d\TH:i:s\Z'),
            'sender_username_token' => (string) $viewer['username_token'],
            'recipient_username_token' => $recipientUsernameToken,
            'sender_identity_id' => (string) $viewer['identity_id'],
            'encrypted_envelope' => $encryptedEnvelope,
        ];
        $accepted = $this->store->acceptedEnvelope($attempt);
        if ($accepted !== null) {
            return $accepted;
        }
        if ($this->keyResolver->keysForUsernameToken($this->readPdo, $recipientUsernameToken) === []) {
            throw new InvalidArgumentException('Recipient has no approved profile keys.');
        }

        return $this->store->acceptEnvelope($attempt);
    }

    /** @param array<string, mixed> $viewer @return list<array<string, string>> */
    public function inbox(array $viewer): array
    {
        return $this->store->inboxFor((string) $this->approvedViewer($viewer)['username_token']);
    }

    /** @param array<string, mixed> $viewer @return list<array<string, string>> */
    public function sent(array $viewer): array
    {
        return $this->store->sentBy((string) $this->approvedViewer($viewer)['username_token']);
    }

    /** @param array<string, mixed> $viewer @return array<string, mixed> */
    public function conversations(array $viewer, ?string $cursor = null): array
    {
        return $this->store->conversationsFor((string) $this->approvedViewer($viewer)['username_token'], $cursor);
    }

    /** @param array<string, mixed> $viewer @return list<array<string, string>> */
    public function conversation(array $viewer, string $counterpartUsernameToken): array
    {
        $viewer = $this->approvedViewer($viewer);
        $counterpartUsernameToken = strtolower(trim($counterpartUsernameToken));
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $counterpartUsernameToken) !== 1) {
            throw new InvalidArgumentException('Conversation counterpart is invalid.');
        }
        if ($counterpartUsernameToken === (string) $viewer['username_token']) {
            throw new InvalidArgumentException('A conversation counterpart must be another user.');
        }
        if ($this->keyResolver->keysForUsernameToken($this->readPdo, $counterpartUsernameToken) === []) {
            throw new InvalidArgumentException('Conversation counterpart has no approved profile keys.');
        }

        return $this->store->conversationFor((string) $viewer['username_token'], $counterpartUsernameToken);
    }

    /** @param array<string, mixed> $viewer @return list<array{identity_id:string,profile_slug:string,public_key:string}> */
    public function recipientKeys(array $viewer, string $usernameToken): array
    {
        $this->approvedViewer($viewer);
        $usernameToken = strtolower(trim($usernameToken));
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $usernameToken) !== 1) {
            throw new InvalidArgumentException('Recipient username is invalid.');
        }

        $keys = $this->keyResolver->keysForUsernameToken($this->readPdo, $usernameToken);
        if ($keys === []) {
            throw new InvalidArgumentException('Recipient has no approved profile keys.');
        }

        return $keys;
    }

    /** @param array<string, mixed> $viewer @return array<string, mixed> */
    private function approvedViewer(array $viewer): array
    {
        if (((int) ($viewer['is_approved'] ?? 0)) !== 1
            || trim((string) ($viewer['identity_id'] ?? '')) === ''
            || trim((string) ($viewer['username_token'] ?? '')) === '') {
            throw new RuntimeException('An approved authenticated identity is required.');
        }

        return $viewer;
    }

    private function isArmoredMessage(string $value): bool
    {
        return strlen($value) <= 1048576
            && str_contains($value, '-----BEGIN PGP MESSAGE-----')
            && str_contains($value, '-----END PGP MESSAGE-----');
    }
}
