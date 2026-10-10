<?php

declare(strict_types=1);
require __DIR__ . '/../autoload.php';

use ForumRewrite\Messaging\PrivateMessageStore;

final class PrivateMessageReadStateTest
{
    public function testIncomingCountsAreSharedBoundedAndMonotonic(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new PrivateMessageStore($pdo);
        $store->storeEnvelope('one', '2026-10-10T12:00:00Z', 'bob', 'alice', 'key', 'cipher');
        $store->storeEnvelope('two', '2026-10-10T12:00:00Z', 'carol', 'alice', 'key', 'cipher');
        $store->storeEnvelope('own', '2026-10-10T13:00:00Z', 'alice', 'bob', 'key', 'cipher');
        assertSame(2, $store->unreadStateFor('alice')['unread_count']);
        $store->markSeenThrough('alice', 'bob', 'one');
        assertSame(['bob' => false, 'carol' => true], (array) $store->unreadStateFor('alice', ['bob', 'carol'])['conversations']);
        $store->storeEnvelope('backdated', '2020-01-01T00:00:00Z', 'bob', 'alice', 'key', 'cipher');
        assertSame(2, $store->unreadStateFor('alice')['unread_count']);
        $store->markSeenThrough('alice', 'bob', 'backdated');
        $store->markSeenThrough('alice', 'bob', 'one');
        assertSame(1, (new PrivateMessageStore($pdo))->unreadStateFor('alice')['unread_count']);
        assertSame(0, $store->unreadStateFor('outsider')['unread_count']);
        try { $store->markSeenThrough('alice', 'bob', 'own'); throw new RuntimeException('Accepted outgoing boundary'); }
        catch (InvalidArgumentException $expected) { assertStringContains('boundary', $expected->getMessage()); }
        for ($i = 0; $i < 30; $i++) $store->storeEnvelope('many-' . $i, '2026-10-10T12:00:00Z', 'user-' . $i, 'alice', 'key', 'cipher');
        assertSame(31, $store->unreadStateFor('alice', ['bob'])['unread_count']);
    }

    public function testUpgradeBaselineAndRollbackDoNotResetProgress(): void
    {
        $pdo = new PDO('sqlite::memory:');
        // Cycle 3 schema/data, before the tracking tables exist.
        $pdo->exec('CREATE TABLE private_messages (message_id TEXT PRIMARY KEY, created_at TEXT NOT NULL,
            sender_username_token TEXT NOT NULL, recipient_username_token TEXT NOT NULL, sender_identity_id TEXT NOT NULL, encrypted_envelope TEXT NOT NULL)');
        $pdo->exec("INSERT INTO private_messages VALUES ('old', '2020-01-01', 'bob', 'alice', 'key', 'cipher')");
        $store = new PrivateMessageStore($pdo);
        assertSame(0, $store->unreadStateFor('alice')['unread_count']);
        $before = $pdo->query('SELECT * FROM private_message_tracking')->fetch();
        // Old code can still insert unchanged envelopes during rollback.
        $pdo->exec("INSERT INTO private_messages VALUES ('during-rollback', '2020-01-01', 'bob', 'alice', 'key', 'cipher')");
        $store = new PrivateMessageStore($pdo);
        assertSame(1, $store->unreadStateFor('alice')['unread_count']);
        $store->markSeenThrough('alice', 'bob', 'during-rollback');
        $store = new PrivateMessageStore($pdo);
        assertSame(0, $store->unreadStateFor('alice')['unread_count']);
        assertSame($before, $pdo->query('SELECT * FROM private_message_tracking')->fetch());
        $pdo->exec("UPDATE private_messages SET message_id = 'changed' WHERE message_id = 'old'");
        try { $store->unreadStateFor('alice'); throw new LogicException('Invalid anchor accepted'); }
        catch (RuntimeException $expected) { assertStringContains('baseline needs repair', $expected->getMessage()); }
    }

    public function testConcurrentInitializationAndAcknowledgment(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'message-read-state-');
        try {
            $script = 'require ' . var_export(__DIR__ . '/../autoload.php', true) . ';'
                . '$s=new ForumRewrite\\Messaging\\PrivateMessageStore(new PDO(' . var_export('sqlite:' . $path, true) . '));';
            $this->race($script);
            $pdo = new PDO('sqlite:' . $path);
            $store = new PrivateMessageStore($pdo);
            assertSame(1, (int) $pdo->query('SELECT COUNT(*) FROM private_message_tracking')->fetchColumn());
            $store->storeEnvelope('a', '2026-10-10', 'bob', 'alice', 'key', 'cipher');
            $store->storeEnvelope('b', '2026-10-10', 'bob', 'alice', 'key', 'cipher');
            $this->race($script . '$s->markSeenThrough("alice","bob",$argv[1]);', ['a', 'b', 'a', 'b']);
            assertSame(0, $store->unreadStateFor('alice')['unread_count']);
        } finally { @unlink($path); }
    }

    private function race(string $script, array $arguments = ['', '', '', '']): void
    {
        $processes = [];
        foreach ($arguments as $argument) {
            $process = proc_open([PHP_BINARY, '-r', $script, $argument], [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
            $processes[] = [$process, $pipes];
        }
        foreach ($processes as [$process, $pipes]) {
            $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
            fclose($pipes[1]); fclose($pipes[2]);
            assertSame(0, proc_close($process), $output);
        }
    }
}
