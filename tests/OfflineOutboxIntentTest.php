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
}
