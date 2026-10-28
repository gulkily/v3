<?php

declare(strict_types=1);

final class OfflineOutboxIntentTest
{
    public function testSignedIntentBindsActionTimeAndKeepsPrivateRecordOutOfSummary(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
global.window = { crypto: { randomUUID() { return 'intent-uuid'; } } };
window.__forumBrowserIdentity = { currentAuthorIdentityId() { return 'openpgp:abc'; } };
window.ForumBrowserSigning = { signCanonicalRecord(record) { return Promise.resolve('signature-for:' + record); } };
global.document = { addEventListener() {} };
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
window.forumOutboxIntent.createSignedIntent({
  action: 'reaction', state: 'queued', target: { kind: 'post', id: 'reply-1' },
  payload: { tag: 'like', postId: 'reply-1' }, summary: 'Like reply', actionAt: '2026-10-01T12:00:00Z'
}).then((item) => process.stdout.write(JSON.stringify({ item, summary: window.forumOutbox.safeSummary(item) })));
NODE;
        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_store.js'),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_intent.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox intent helper failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('outbox-reaction-intent-uuid', $result['item']['id']);
        assertSame('2026-10-01T12:00:00Z', $result['item']['actionAt']);
        assertStringContains('Action-At: 2026-10-01T12:00:00Z', $result['item']['intent']['canonicalRecord']);
        assertStringContains('"postId":"reply-1","tag":"like"', $result['item']['intent']['canonicalRecord']);
        assertFalse(array_key_exists('intent', $result['summary']));
        assertFalse(array_key_exists('actionAt', $result['summary']));
    }

    public function testPublicKeyTravelsOnlyWithAFirstAction(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const results = {};
global.document = { addEventListener() {} };
function setup(storage) {
  global.window = { crypto: { randomUUID() { return 'intent-uuid'; } } };
  window.localStorage = { getItem(key) { return Object.prototype.hasOwnProperty.call(storage, key) ? storage[key] : null; } };
  window.__forumBrowserIdentity = { currentAuthorIdentityId() { return 'openpgp:abc'; } };
  window.ForumBrowserSigning = { signCanonicalRecord() { return Promise.resolve('signature'); } };
  vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
  vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
  return window.forumOutboxIntent.createSignedIntent({ action: 'reaction', state: 'queued', target: { kind: 'thread', id: 't' }, payload: { kind: 'thread_tag', threadId: 't', tag: 'like' } });
}
setup({ forum_pki_fingerprint: 'ABC', forum_pki_public_key: 'public-key' })
  .then((item) => { results.first = item.intent.publicKey || ''; })
  .then(() => setup({ forum_pki_fingerprint: 'ABC', forum_pki_published_fingerprint: 'ABC', forum_pki_public_key: 'public-key' }))
  .then((item) => { results.registered = Object.prototype.hasOwnProperty.call(item.intent, 'publicKey'); process.stdout.write(JSON.stringify(results)); });
NODE;
        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_store.js'),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_intent.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox intent helper failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('public-key', $result['first']);
        assertSame(false, $result['registered']);
    }
}
