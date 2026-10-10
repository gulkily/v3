<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

use ForumRewrite\Messaging\PrivateMessageStore;

final class PrivateMessageStoreTest
{
    public function testConversationTiesFollowInsertionOrder(): void
    {
        $store = new PrivateMessageStore(new PDO('sqlite::memory:'));
        $store->storeEnvelope('z', '2026-10-09T12:00:00Z', 'alice', 'bob', 'sender', 'one');
        $store->storeEnvelope('a', '2026-10-09T12:00:00Z', 'bob', 'alice', 'sender', 'two');
        assertSame(['z', 'a'], array_column($store->conversationFor('alice', 'bob'), 'message_id'));
    }

    public function testConcurrentAcceptanceKeepsOneOriginalEnvelope(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'private-message-race-');
        $attempt = ['message_id' => 'race', 'created_at' => '2026-10-09T12:00:00Z',
            'sender_username_token' => 'alice', 'recipient_username_token' => 'bob',
            'sender_identity_id' => 'alice-key', 'encrypted_envelope' => 'cipher'];
        $store = new PrivateMessageStore(new PDO('sqlite:' . $path));
        $children = [];
        $script = 'require ' . var_export(__DIR__ . '/../autoload.php', true) . ';'
            . '$store = new ForumRewrite\\Messaging\\PrivateMessageStore(new PDO(' . var_export('sqlite:' . $path, true) . '));'
            . '$result = $store->acceptEnvelope(' . var_export($attempt, true) . ');'
            . 'exit($result["message_id"] === "race" ? 0 : 1);';
        try {
            for ($i = 0; $i < 4; $i++) {
                $process = proc_open([PHP_BINARY, '-r', $script], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
                if (!is_resource($process)) throw new RuntimeException('Unable to start race fixture.');
                $children[] = [$process, $pipes];
            }
            foreach ($children as [$process, $pipes]) {
                $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
                fclose($pipes[1]);
                fclose($pipes[2]);
                assertSame(0, proc_close($process), $output);
            }
            assertSame(1, count($store->sentBy('alice')));
        } finally {
            @unlink($path);
        }
    }

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

    public function testLimitsEachMailboxToItsNewestTwentyFiveMessages(): void
    {
        $store = new PrivateMessageStore(new PDO('sqlite::memory:'));
        for ($index = 1; $index <= 26; $index++) {
            $store->storeEnvelope(
                sprintf('message-%02d', $index),
                sprintf('2026-10-09T12:%02d:00Z', $index),
                'alice',
                'ilyag',
                'openpgp:alice',
                'ciphertext-' . $index,
            );
        }

        assertSame(25, count($store->inboxFor('ilyag')));
        assertSame(25, count($store->sentBy('alice')));
        assertSame('message-26', $store->inboxFor('ilyag')[0]['message_id']);
        assertSame('message-02', $store->inboxFor('ilyag')[24]['message_id']);
    }

    public function testConversationPagesFreezeArrivalsAndGroupBeforeLimiting(): void
    {
        $store = new PrivateMessageStore(new PDO('sqlite::memory:'));
        for ($i = 1; $i <= 60; $i++) {
            $other = sprintf('user-%02d', $i);
            $store->storeEnvelope(sprintf('message-%02d', $i), '2026-10-09T12:00:00Z',
                $i % 2 ? 'alice' : $other, $i % 2 ? $other : 'alice', 'sender', 'envelope-' . $i);
        }
        // Many newer messages with one counterpart must not hide older counterparts.
        for ($i = 1; $i <= 30; $i++) {
            $store->storeEnvelope(sprintf('repeat-%02d', $i), '2026-10-09T13:00:00Z', 'alice', 'user-60', 'sender', 'repeat');
        }
        $store->storeEnvelope('unrelated', '2026-10-09T15:00:00Z', 'mallory', 'eve', 'sender', 'secret');
        $first = $store->conversationsFor('alice');
        assertSame(25, count($first['conversations']));
        assertSame('repeat-30', $first['conversations'][0]['message_id']);
        $store->storeEnvelope('new-unseen', '2026-10-09T16:00:00Z', 'user-01', 'alice', 'sender', 'new');
        $store->storeEnvelope('new-seen', '2026-10-09T16:00:00Z', 'alice', 'user-60', 'sender', 'new');
        $store->storeEnvelope('new-counterpart', '2026-10-09T12:00:00Z', 'new-user', 'alice', 'sender', 'new');
        assertSame($first, $store->conversationsFor('alice', $first['page_cursor']));
        $second = $store->conversationsFor('alice', $first['next_cursor']);
        assertSame($second, $store->conversationsFor('alice', $first['next_cursor']));
        $third = $store->conversationsFor('alice', $second['next_cursor']);
        assertSame(25, count($second['conversations']));
        assertSame(10, count($third['conversations']));
        assertSame(null, $third['next_cursor']);
        $rows = array_merge($first['conversations'], $second['conversations'], $third['conversations']);
        assertSame(60, count(array_unique(array_column($rows, 'counterpart'))));
        assertSame('message-01', $rows[59]['message_id']);
        assertSame('new-seen', $store->conversationsFor('alice')['conversations'][0]['message_id']);
        assertSame([], $store->conversationsFor('nobody')['conversations']);
        foreach (['broken', $first['next_cursor']] as $cursor) {
            try {
                $store->conversationsFor('mallory', $cursor);
                throw new RuntimeException('An invalid or foreign cursor was accepted.');
            } catch (InvalidArgumentException $exception) {
                assertStringContains('Reload Messages', $exception->getMessage());
            }
        }
    }

    public function testLatestPreviewUsesInsertionOrderForSameSecondMessages(): void
    {
        $store = new PrivateMessageStore(new PDO('sqlite::memory:'));
        $store->storeEnvelope('z-random-id', '2026-10-09T12:00:00Z', 'alice', 'bob', 'sender', 'first');
        $store->storeEnvelope('a-random-id', '2026-10-09T12:00:00Z', 'alice', 'bob', 'sender', 'latest');
        assertSame('latest', $store->conversationsFor('alice')['conversations'][0]['encrypted_envelope']);
    }
}
