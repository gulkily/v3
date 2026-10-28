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

    public function testSignedReactionRegistersItsKeyBeforeSending(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
global.navigator = { onLine: true };
global.window = { crypto: { randomUUID() { return 'send-uuid'; } } };
const events = [];
window.forumOutboxStorage = { save(item) { return Promise.resolve(item); } };
window.ForumBrowserSigning = {
  ensureActionIdentity() { events.push('register'); return Promise.resolve(); }
};
global.fetch = function (url) {
  events.push(String(url));
  return Promise.resolve({ text() { return Promise.resolve('status=ok\nthread_id=thread-1\n'); } });
};
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
const item = window.forumOutbox.createItem({ id: 'outbox-like', action: 'reaction', state: 'queued', payload: { kind: 'thread_tag', threadId: 'thread-1', tag: 'like' }, intent: { canonicalRecord: 'record', detachedSignature: 'signature' } });
window.forumOutboxSender.send(item).then((result) => process.stdout.write(JSON.stringify({ state: result.state, events })));
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

        assertSame('accepted', $result['state']);
        assertSame(['register', '/api/apply_signed_reaction'], $result['events']);
    }

    public function testForegroundProcessorSendsQueuedItemsButLeavesDraftsLocal(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
global.navigator = { onLine: true };
global.window = { crypto: { randomUUID() { return 'auto-uuid'; } } };
const saved = [];
window.forumOutboxStorage = {
  list() { return Promise.resolve([
    { id: 'queued-thread', action: 'thread', state: 'queued', createdAt: '2026-10-01T00:00:00Z', updatedAt: '2026-10-01T00:00:00Z', target: {}, summary: '', payload: { body: 'Queued', boardTags: 'general' }, outcome: null },
    { id: 'draft-thread', action: 'thread', state: 'draft', createdAt: '2026-10-01T00:00:00Z', updatedAt: '2026-10-01T00:00:00Z', target: {}, summary: '', payload: { body: 'Draft', boardTags: 'general' }, outcome: null }
  ]); },
  save(item) { saved.push(item); return Promise.resolve(item); }
};
window.ForumBrowserSigning = {
  prepareOutboxPost() { return Promise.resolve({ ok: true, fields: { author_identity_id: 'openpgp:test' }, prepared: { prepareToken: 'prepared-token', postId: 'thread-1', threadId: 'thread-1', recordPath: 'records/posts/thread-1.txt', canonicalRecord: 'record' } }); },
  finalizeOutboxPost() { return Promise.resolve({ ok: true, postId: 'thread-1', threadId: 'thread-1', commitSha: 'commit' }); }
};
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
window.forumOutboxSender.processQueuedOutbox().then((items) => process.stdout.write(JSON.stringify({ items, saved })));
NODE;
        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_store.js'),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_sender.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox foreground sender helper failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame(1, count($result['items']));
        assertSame('queued-thread', $result['items'][0]['id']);
        assertSame('accepted', $result['items'][0]['state']);
        assertFalse(in_array('draft-thread', array_column($result['saved'], 'id'), true));
    }
}
