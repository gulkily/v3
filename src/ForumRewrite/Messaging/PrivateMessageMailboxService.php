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
        private readonly ?string $repositoryRoot = null,
        /** @var (\Closure(string, string, string): mixed)|null message ID, sender, recipient; after a new message is accepted */
        private readonly ?\Closure $onMessageAccepted = null,
    ) {
    }

    /**
     * @param array<string, mixed> $viewer
     * @param array<string, mixed> $input
     * @return array<string, string>
     */
    public function send(array $viewer, array $input): array
    {
        $isNew = false;
        $accept = function () use ($viewer, $input, &$isNew): array {
            return $this->sendAccepted($viewer, $input, $isNew);
        };
        $message = $this->repositoryRoot === null ? $accept() : \ForumRewrite\Support\PublicationLock::withLock($this->repositoryRoot, $accept);
        if ($isNew && $this->onMessageAccepted !== null) {
            // After the lock; only who wrote to whom, never the envelope (PushNotices).
            try {
                ($this->onMessageAccepted)((string) $message['message_id'], (string) $message['sender_username_token'], (string) $message['recipient_username_token']);
            } catch (\Throwable $error) {
                error_log('Push notice after an accepted message failed: ' . $error->getMessage());
            }
        }

        return $message;
    }
    private function sendAccepted(array $viewer, array $input, bool &$isNew = false): array
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

        $message = $this->store->acceptEnvelope($attempt);
        $isNew = true;

        return $message;
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

    /**
     * The signed-in member's own mail, for pulling into another clone. The account always
     * comes from the authenticated viewer, never from the request.
     *
     * @param array<string, mixed> $viewer
     * @return array<string, mixed>
     */
    public function export(array $viewer, string $afterTime = '', string $afterId = '', ?PrivateMessageHistorySyncStore $historySync = null): array
    {
        $viewer = $this->approvedViewer($viewer);
        if ($afterTime !== '' && preg_match('/^\d{4}-\d{2}-\d{2}[0-9T:.Z+-]{0,24}$/D', $afterTime) !== 1) {
            throw new InvalidArgumentException('Export position is invalid.');
        }
        if ($afterId !== '' && preg_match('/^[A-Za-z0-9][A-Za-z0-9._:-]{0,127}$/D', $afterId) !== 1) {
            throw new InvalidArgumentException('Export position is invalid.');
        }
        if (($afterTime === '') !== ($afterId === '')) {
            throw new InvalidArgumentException('Export position needs both after_time and after_id.');
        }
        $account = (string) $viewer['username_token'];
        $page = $this->store->exportPage($account, $afterTime, $afterId);
        $page['transfers'] = $historySync === null ? []
            // Only the signed-in key's transfers: other keys' would multiply the page for no reader.
            : $historySync->transfersFor($account, array_column($page['messages'], 'message_id'), $this->viewerFingerprint($viewer));
        return ['account' => $account] + $page;
    }

    /** @param array<string, mixed> $viewer */
    private function viewerFingerprint(array $viewer): string
    {
        $identityId = strtolower((string) ($viewer['identity_id'] ?? ''));
        if (preg_match('/^openpgp:([a-f0-9]{40})$/D', $identityId, $match) !== 1) {
            throw new RuntimeException('Signed-in key is not an OpenPGP identity.');
        }
        return $match[1];
    }

    /** @param array<string, mixed> $viewer @return array<string, mixed> */
    public function conversations(array $viewer, ?string $cursor = null): array
    {
        return $this->store->conversationsFor((string) $this->approvedViewer($viewer)['username_token'], $cursor);
    }

    /** @param array<string, mixed> $viewer @return array<string, mixed> */
    public function conversationPage(array $viewer, string $counterpartUsernameToken, ?string $cursor = null): array
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

        $page = $this->store->conversationPageFor((string) $viewer['username_token'], $counterpartUsernameToken, $cursor);
        $page['read_token'] = $cursor === null ? $this->store->readTokenFor((string) $viewer['username_token'], $counterpartUsernameToken, $page['page_cursor']) : null;
        return $page;
    }

    public function unreadState(array $viewer, array $counterparts = []): array
    {
        return $this->store->unreadStateFor((string) $this->approvedViewer($viewer)['username_token'], $counterparts);
    }

    /** @return list<array{counterpart:string,unread_count:int,latest_at:string}> */
    public function unreadConversations(array $viewer, int $limit = 25): array
    {
        return $this->store->unreadConversationsFor((string) $this->approvedViewer($viewer)['username_token'], $limit);
    }

    public function acknowledge(array $viewer, string $counterpart, string $readToken): array
    {
        $viewer = $this->approvedViewer($viewer);
        $counterpart = strtolower(trim($counterpart));
        $this->recipientKeys($viewer, $counterpart);
        $this->store->acknowledgeRead((string) $viewer['username_token'], $counterpart, $readToken);
        return $this->unreadState($viewer, [$counterpart]);
    }

    /** @param array<string, mixed> $viewer @return list<array{identity_id:string,profile_slug:string,public_key:string}> */
    public function recipientKeys(array $viewer, string $usernameToken, bool $historical = false): array
    {
        $this->approvedViewer($viewer);
        $usernameToken = strtolower(trim($usernameToken));
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,63}$/', $usernameToken) !== 1) {
            throw new InvalidArgumentException('Recipient username is invalid.');
        }

        $keys = $this->keyResolver->keysForUsernameToken($this->readPdo, $usernameToken, $historical);
        if ($keys === []) {
            throw new InvalidArgumentException('Recipient has no approved profile keys.');
        }

        return $keys;
    }

    /** @param array<string, mixed> $viewer @return array<string, mixed> */
    private function approvedViewer(array $viewer): array
    {
        if ($this->repositoryRoot !== null) \ForumRewrite\Security\KeyRetirement::assertActive($this->repositoryRoot, (string) ($viewer['identity_id'] ?? ''));
        if (((int) ($viewer['is_approved'] ?? 0)) !== 1
            || trim((string) ($viewer['identity_id'] ?? '')) === ''
            || trim((string) ($viewer['username_token'] ?? '')) === '') {
            throw new RuntimeException('An approved authenticated identity is required.');
        }
        if (\ForumRewrite\Security\PrivateKeyAccess::enforced($this->repositoryRoot)
            && !(new \ForumRewrite\Security\PrivateKeyAccess($this->readPdo, $this->repositoryRoot))->hasAccess((string) $viewer['identity_id'])) {
            throw new \ForumRewrite\Security\PublicOnlyKeyException();
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
