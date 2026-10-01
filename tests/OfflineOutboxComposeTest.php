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
}
