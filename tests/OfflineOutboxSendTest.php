<?php

declare(strict_types=1);

final class OfflineOutboxSendTest
{
    public function testQueuedThreadUsesOnePreparedDeliveryAndRecordsAcceptance(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
global.navigator = { onLine: true };
global.window = { crypto: { randomUUID() { return 'send-uuid'; } } };
const saved = [];
window.forumOutboxStorage = { save(item) { saved.push(item); return Promise.resolve(item); } };
window.ForumBrowserSigning = {
  ensureActionIdentity() { return Promise.resolve(); },
  prepareOutboxPost() { return Promise.resolve({ ok: true, fields: { author_identity_id: 'openpgp:test' }, prepared: { prepareToken: 'prepared-token', postId: 'thread-1', threadId: 'thread-1', recordPath: 'records/posts/thread-1.txt', canonicalRecord: 'record' } }); },
  finalizeOutboxPost() { return Promise.resolve({ ok: true, postId: 'thread-1', threadId: 'thread-1', commitSha: 'commit' }); }
};
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
const item = window.forumOutbox.createItem({ id: 'outbox-thread-send', action: 'thread', state: 'queued', payload: { subject: 'Topic', body: 'Body', boardTags: 'general' } });
window.forumOutboxSender.send(item).then((result) => process.stdout.write(JSON.stringify({ result, saved })));
NODE;
        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_store.js'),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_sender.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox sender helper failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('accepted', $result['result']['state']);
        assertSame('thread-1', $result['result']['outcome']['postId']);
        assertSame('sending', $result['saved'][0]['state']);
        assertSame('prepared-token', $result['saved'][1]['delivery']['prepared']['prepareToken']);
        assertSame('accepted', $result['saved'][2]['state']);
    }
}
