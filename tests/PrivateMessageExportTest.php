<?php

declare(strict_types=1);
require __DIR__ . '/../autoload.php';

use ForumRewrite\Messaging\PrivateMessageMailboxService;
use ForumRewrite\Messaging\PrivateMessageReadState;
use ForumRewrite\Messaging\PrivateMessageStore;

final class PrivateMessageExportTest
{
    private function service(PDO $pdo): PrivateMessageMailboxService
    {
        return new PrivateMessageMailboxService(new PrivateMessageStore($pdo), new PDO('sqlite::memory:'));
    }

    private function viewer(string $name, int $approved = 1): array
    {
        return ['username_token' => $name, 'identity_id' => 'openpgp:' . str_repeat('a', 40), 'is_approved' => $approved];
    }

    public function testExportReturnsOnlyTheViewersOwnMailOldestFirst(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new PrivateMessageStore($pdo);
        $store->storeEnvelope('m2', '2026-11-02T00:00:00Z', 'bob', 'alice', 'bob-key', 'to alice');
        $store->storeEnvelope('m1', '2026-11-01T00:00:00Z', 'alice', 'bob', 'alice-key', 'from alice');
        $store->storeEnvelope('m3', '2026-11-03T00:00:00Z', 'bob', 'carol', 'bob-key', 'carol secret');
        $store->storeEnvelope('m4', '2026-11-04T00:00:00Z', 'carol', 'bob', 'carol-key', 'bob secret');
        $page = $this->service($pdo)->export($this->viewer('alice'));
        assertSame('alice', $page['account']);
        assertSame(['m1', 'm2'], array_column($page['messages'], 'message_id'));
        assertSame(null, $page['next']);
        assertStringNotContains('secret', json_encode($page));
        $empty = $this->service($pdo)->export($this->viewer('dave'));
        assertSame([], $empty['messages']);
    }

    public function testExportResumesFromAReadablePositionWithTiesBrokenById(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new PrivateMessageStore($pdo);
        for ($i = 0; $i < PrivateMessageStore::EXPORT_PAGE_SIZE + 3; $i++) {
            $store->storeEnvelope(sprintf('id-%03d', $i), '2026-11-01T00:00:00Z', 'bob', 'alice', 'key', 'c' . $i);
        }
        $first = $this->service($pdo)->export($this->viewer('alice'));
        assertSame(PrivateMessageStore::EXPORT_PAGE_SIZE, count($first['messages']));
        assertSame(['after_time' => '2026-11-01T00:00:00Z', 'after_id' => 'id-024'], $first['next']);
        $second = $this->service($pdo)->export($this->viewer('alice'), $first['next']['after_time'], $first['next']['after_id']);
        assertSame(['id-025', 'id-026', 'id-027'], array_column($second['messages'], 'message_id'));
        assertSame(null, $second['next']);
    }

    public function testExportReportsReadStateForReceivedMessages(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $store = new PrivateMessageStore($pdo);
        $store->storeEnvelope('seen', '2026-11-01T00:00:00Z', 'bob', 'alice', 'key', 'c');
        $store->storeEnvelope('unseen', '2026-11-02T00:00:00Z', 'bob', 'alice', 'key', 'c');
        $store->storeEnvelope('other', '2026-11-03T00:00:00Z', 'carol', 'alice', 'key', 'c');
        $store->storeEnvelope('mine', '2026-11-04T00:00:00Z', 'alice', 'bob', 'key', 'c');
        (new PrivateMessageReadState($pdo))->mark('alice', 'bob', 'seen');
        $read = array_column($this->service($pdo)->export($this->viewer('alice'))['messages'], 'read', 'message_id');
        assertSame(['seen' => true, 'unseen' => false, 'other' => false, 'mine' => true], $read);
    }

    public function testExportRejectsUnapprovedViewersAndMalformedPositions(): void
    {
        $pdo = new PDO('sqlite::memory:');
        try { $this->service($pdo)->export($this->viewer('alice', 0)); throw new LogicException('Unapproved viewer exported'); }
        catch (RuntimeException $expected) { assertStringContains('approved', $expected->getMessage()); }
        foreach ([['2026-11-01', ''], ['', 'id'], ["x' OR 1", 'id'], ['2026-11-01', 'bad id']] as [$time, $id]) {
            try { $this->service($pdo)->export($this->viewer('alice'), $time, $id); throw new LogicException('Malformed position accepted'); }
            catch (InvalidArgumentException $expected) { assertStringContains('position', $expected->getMessage()); }
        }
    }
}
