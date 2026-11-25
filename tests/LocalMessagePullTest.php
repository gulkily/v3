<?php

declare(strict_types=1);
require __DIR__ . '/../autoload.php';

use ForumRewrite\Messaging\PrivateMessageHistorySyncStore;
use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageStore;
use ForumRewrite\Messaging\PrivateMessageReadState;
use ForumRewrite\Support\LocalDevelopmentRemote;
use ForumRewrite\Support\LocalMessagePull;

final class LocalMessagePullTest
{
    /** A source whose export is the real service over its own stores. */
    private function remote(PDO $sourceMessages, PrivateMessageHistorySyncStore $sourceSync, string $viewer, ?int &$calls = null): LocalDevelopmentRemote
    {
        $calls = 0;
        $transport = function (string $method, string $url) use ($sourceMessages, $sourceSync, $viewer, &$calls): array {
            $calls++;
            parse_str((string) parse_url($url, PHP_URL_QUERY), $query);
            $service = new PrivateMessageMailboxService(new PrivateMessageStore($sourceMessages), new PDO('sqlite::memory:'));
            $page = $service->export(['username_token' => $viewer, 'identity_id' => 'openpgp:' . str_repeat('b', 40), 'is_approved' => 1],
                (string) ($query['after_time'] ?? ''), (string) ($query['after_id'] ?? ''), $sourceSync);
            return ['status' => 200, 'headers' => [], 'body' => json_encode(['status' => 'ok'] + $page)];
        };
        return new LocalDevelopmentRemote('https://source.example', $transport(...));
    }

    private function seed(PDO $pdo, int $count): void
    {
        $store = new PrivateMessageStore($pdo);
        for ($i = 0; $i < $count; $i++) {
            $store->storeEnvelope(sprintf('m-%03d', $i), sprintf('2026-11-01T00:%02d:00Z', $i % 60), $i % 2 ? 'alice' : 'bob', $i % 2 ? 'bob' : 'alice', 'key', 'cipher-' . $i);
        }
        $store->storeEnvelope('not-yours', '2026-11-01T00:00:00Z', 'bob', 'carol', 'key', 'carol-cipher');
    }

    public function testPullCopiesOwnMailAndTransfersWithOriginalIdsAndRepeatsCleanly(): void
    {
        $source = new PDO('sqlite::memory:');
        $this->seed($source, 30);
        $sourceSync = new PrivateMessageHistorySyncStore(new PDO('sqlite::memory:'));
        $transferId = $sourceSync->upload('alice', 'old-key', 'local-key', 'bundle', [['message_id' => 'm-000', 'digest' => 'd0'], ['message_id' => 'm-029', 'digest' => 'd29']]);
        $local = new PDO('sqlite::memory:');
        $localSync = new PrivateMessageHistorySyncStore(new PDO('sqlite::memory:'));
        $progress = sys_get_temp_dir() . '/local-pull-' . bin2hex(random_bytes(5)) . '/progress.json';
        $pull = new LocalMessagePull(new PrivateMessageStore($local), $localSync, $progress);

        $first = $pull->run($this->remote($source, $sourceSync, 'alice'), 'alice');
        assertSame(true, $first['done']);
        assertSame(30, $first['added']);
        assertSame(1, $first['transfers_added']);
        assertSame(30, (int) $local->query("SELECT COUNT(*) FROM private_messages")->fetchColumn());
        assertSame(0, (int) $local->query("SELECT COUNT(*) FROM private_messages WHERE message_id = 'not-yours'")->fetchColumn());
        assertSame('cipher-7', $local->query("SELECT encrypted_envelope FROM private_messages WHERE message_id = 'm-007'")->fetchColumn());
        assertSame(['m-000', 'm-029'], array_column($localSync->transfersFor('alice', ['m-000', 'm-029'])[0]['items'], 'message_id'));
        assertSame($transferId, $localSync->transfersFor('alice', ['m-000'])[0]['transfer_id']);
        assertTrue(!is_file($progress), 'A finished pass removes its progress file');

        $again = $pull->run($this->remote($source, $sourceSync, 'alice'), 'alice');
        assertSame([true, 0, 30, 0], [$again['done'], $again['added'], $again['existing'], $again['transfers_added']]);
    }

    public function testInterruptedPullResumesAndLocalMessagesAlwaysWin(): void
    {
        $source = new PDO('sqlite::memory:');
        $this->seed($source, 30);
        $sourceSync = new PrivateMessageHistorySyncStore(new PDO('sqlite::memory:'));
        $local = new PDO('sqlite::memory:');
        (new PrivateMessageStore($local))->storeEnvelope('m-004', '2026-11-01T00:04:00Z', 'bob', 'alice', 'key', 'a local copy');
        $progress = sys_get_temp_dir() . '/local-pull-' . bin2hex(random_bytes(5)) . '/progress.json';
        $pull = new LocalMessagePull(new PrivateMessageStore($local), new PrivateMessageHistorySyncStore(new PDO('sqlite::memory:')), $progress);

        $partial = $pull->run($this->remote($source, $sourceSync, 'alice'), 'alice', 1);
        assertSame(false, $partial['done']);
        assertSame(['m-004'], $partial['mismatched']);
        assertTrue(is_file($progress));
        $rest = $pull->run($this->remote($source, $sourceSync, 'alice', $calls), 'alice');
        assertSame(true, $rest['done']);
        assertSame(1, $calls, 'Resume starts after the saved position');
        assertSame(30, (int) $local->query("SELECT COUNT(*) FROM private_messages")->fetchColumn());
        assertSame('a local copy', $local->query("SELECT encrypted_envelope FROM private_messages WHERE message_id = 'm-004'")->fetchColumn());
    }

    public function testPullCopiesReadStateAndRefusesAnotherAccount(): void
    {
        $source = new PDO('sqlite::memory:');
        $sourceStore = new PrivateMessageStore($source);
        $sourceStore->storeEnvelope('read-1', '2026-11-01T00:00:00Z', 'bob', 'alice', 'key', 'c');
        $sourceStore->storeEnvelope('unread-1', '2026-11-02T00:00:00Z', 'bob', 'alice', 'key', 'c');
        (new PrivateMessageReadState($source))->mark('alice', 'bob', 'read-1');
        $local = new PDO('sqlite::memory:');
        $localStore = new PrivateMessageStore($local);
        $pull = new LocalMessagePull($localStore, new PrivateMessageHistorySyncStore(new PDO('sqlite::memory:')), sys_get_temp_dir() . '/local-pull-' . bin2hex(random_bytes(5)) . '/p.json');
        $sync = new PrivateMessageHistorySyncStore(new PDO('sqlite::memory:'));
        $result = $pull->run($this->remote($source, $sync, 'alice'), 'alice');
        assertSame(1, $result['read_marked']);
        assertSame(1, $localStore->unreadStateFor('alice')['unread_count']);
        try { $pull->run($this->remote($source, $sync, 'bob'), 'alice'); throw new LogicException('Pulled another account'); }
        catch (RuntimeException $expected) { assertStringContains('different account', $expected->getMessage()); }
    }
}
