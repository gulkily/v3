<?php

declare(strict_types=1);

final class OfflineOutboxComposeTest
{
    public function testReplyDraftCreatesOnlyLocalOutboxItem(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
global.window = { crypto: { randomUUID() { return 'reply-uuid'; } } };
global.document = { addEventListener() {}, querySelectorAll() { return []; } };
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
process.stdout.write(JSON.stringify(window.forumOutboxCompose.replyItem({ threadId: 'thread-1', parentId: 'post-1', body: 'Local reply' })));
NODE;
        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_store.js'),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_compose.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox reply draft helper failed: ' . implode("\n", $output));
        }
        $item = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('outbox-reply-reply-uuid', $item['id']);
        assertSame('reply', $item['action']);
        assertSame('draft', $item['state']);
        assertSame(['threadId' => 'thread-1', 'parentId' => 'post-1', 'body' => 'Local reply'], $item['payload']);
    }

    public function testThreadDraftCreatesOnlyLocalOutboxItem(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
global.window = { crypto: { randomUUID() { return 'thread-uuid'; } } };
global.document = { addEventListener() {}, querySelectorAll() { return []; } };
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
process.stdout.write(JSON.stringify(window.forumOutboxCompose.threadItem({ subject: 'Local topic', body: 'Local body', boardTags: 'general topic' })));
NODE;
        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_store.js'),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_compose.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox thread draft helper failed: ' . implode("\n", $output));
        }
        $item = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('outbox-thread-thread-uuid', $item['id']);
        assertSame('thread', $item['action']);
        assertSame('Local topic', $item['summary']);
        assertSame(['subject' => 'Local topic', 'body' => 'Local body', 'boardTags' => 'general topic'], $item['payload']);
    }

    public function testFormatChoiceTravelsWithTheDraft(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
global.window = { crypto: { randomUUID() { return 'plain-uuid'; } } };
global.document = { addEventListener() {}, querySelectorAll() { return []; } };
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
process.stdout.write(JSON.stringify([
  window.forumOutboxCompose.threadItem({ subject: 'Log', body: '1. not a list', boardTags: 'general', bodyFormat: 'plain' }).payload,
  window.forumOutboxCompose.replyItem({ threadId: 'thread-1', parentId: 'thread-1', body: '*x*', bodyFormat: 'markdown' }).payload,
]));
NODE;
        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_store.js'),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_compose.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox plain draft helper failed: ' . implode("\n", $output));
        }
        [$thread, $reply] = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('plain', $thread['bodyFormat']);
        assertSame('markdown', $reply['bodyFormat']);
    }

    public function testScriptLoadedAfterParsingBindsEachComposerOnce(): void
    {
        // Board and thread pages can load the script after DOMContentLoaded; it must still enable Save to Outbox, once.
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const listeners = [];
const button = { disabled: true, addEventListener(type, fn) { listeners.push(type); } };
const form = {
  dataset: {},
  querySelector(selector) { return selector === '.compose-form-actions' ? {} : selector === '[data-action="save-compose-outbox"]' ? button : null; },
  appendChild() {},
};
global.window = { crypto: { randomUUID() { return 'uuid'; } }, forumOutboxStorage: {} };
global.document = {
  readyState: 'complete',
  addEventListener() { throw new Error('should bind now, not wait for DOMContentLoaded'); },
  querySelectorAll(selector) { return selector.indexOf('"thread"') !== -1 ? [form] : []; },
  createElement() { return { setAttribute() {} }; },
};
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
window.forumOutboxCompose.bind();
process.stdout.write(JSON.stringify({ disabled: button.disabled, clickListeners: listeners.length, bound: form.dataset.outboxComposeBound }));
NODE;
        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_store.js'),
            escapeshellarg(__DIR__ . '/../public/assets/outbox_compose.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Late Outbox compose binding failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame(false, $result['disabled']);
        assertSame(1, $result['clickListeners']);
        assertSame('true', $result['bound']);
    }
}
