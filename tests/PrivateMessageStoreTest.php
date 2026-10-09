<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Messaging\PrivateMessageStore;

final class PrivateMessageStoreTest
{
    public function testStoresOnlyEncryptedEnvelopeAndListsBothMailboxes(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new PrivateMessageStore($pdo);
        $store->storeEnvelope(
            'message-001',
            '2026-10-09T12:00:00Z',
            'alice',
            'ilyag',
            'openpgp:alice',
            '-----BEGIN PGP MESSAGE-----\nCiphertext\n-----END PGP MESSAGE-----\n',
        );

        $columns = array_column($pdo->query('PRAGMA table_info(private_messages)')->fetchAll(), 'name');
        assertSame(true, in_array('encrypted_envelope', $columns, true));
        assertSame(false, in_array('body', $columns, true));
        assertSame('message-001', $store->inboxFor('ilyag')[0]['message_id']);
        assertSame('message-001', $store->sentBy('alice')[0]['message_id']);
        assertSame('-----BEGIN PGP MESSAGE-----\nCiphertext\n-----END PGP MESSAGE-----\n', $store->inboxFor('ilyag')[0]['encrypted_envelope']);
        assertSame([], $store->inboxFor('someone-else'));
    }

    public function testOrdersMailboxRowsNewestFirst(): void
    {
        $store = new PrivateMessageStore(new PDO('sqlite::memory:'));
        $store->storeEnvelope('message-001', '2026-10-09T12:00:00Z', 'alice', 'ilyag', 'openpgp:alice', 'ciphertext-1');
        $store->storeEnvelope('message-002', '2026-10-09T12:01:00Z', 'alice', 'ilyag', 'openpgp:alice', 'ciphertext-2');

        assertSame(['message-002', 'message-001'], array_column($store->inboxFor('ilyag'), 'message_id'));
    }
}
