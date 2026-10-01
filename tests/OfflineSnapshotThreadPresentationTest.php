<?php

declare(strict_types=1);

final class OfflineSnapshotThreadPresentationTest
{
    public function testSnapshotThreadCardsFollowOnlineRootAndCommentPresentationOrder(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
function element(type) {
  return { type, children: [], className: '', dataset: {}, style: {}, textContent: '', id: '', href: '', title: '',
    appendChild(child) { this.children.push(child); return child; }, removeChild(child) { this.children.splice(this.children.indexOf(child), 1); },
    get firstChild() { return this.children[0] || null; }, setAttribute(name, value) { this[name] = value; }, addEventListener() {} };
}
global.window = {};
global.document = { addEventListener() {}, createElement: element };
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
const rows = [
  ['root-1', '', 'Topic', 'Topic\nRoot body', 'author-a', '2026-10-01T12:00:00Z'],
  ['reply-1', 'root-1', '', 'Reply body', 'author-b', '2026-10-01T12:01:00Z']
];
let calls = 0;
const database = { exec() { calls += 1; return calls === 1 ? [{ values: [['2026-10-01T12:01:00Z', 1]] }] : [{ values: rows }]; } };
const content = element('div');
window.forumOfflineSnapshot.renderThreadDetail({ content, database, threadId: 'root-1', setStatus() {} });
const cards = content.children;
process.stdout.write(JSON.stringify(cards.map((card) => ({
  id: card.id, className: card.className, postId: card.dataset.postId, author: card.dataset.author,
  children: card.children.map((child) => ({ type: child.type, className: child.className, text: child.textContent, href: child.href, whiteSpace: child.style.whiteSpace || '' }))
}))));
NODE;
        $command = sprintf('node -e %s %s', escapeshellarg($script), escapeshellarg(__DIR__ . '/../public/assets/offline_reader.js'));
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Offline thread presentation helper failed: ' . implode("\n", $output));
        }
        $cards = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('post-root-1', $cards[0]['id']);
        assertSame('card post-card thread-root-card', $cards[0]['className']);
        assertSame(['h1', 'div', 'p', 'a'], array_column($cards[0]['children'], 'type'));
        assertSame('Root body', $cards[0]['children'][1]['text']);
        assertSame('pre-wrap', $cards[0]['children'][1]['whiteSpace']);
        assertSame(['p', 'div', 'a'], array_column($cards[1]['children'], 'type'));
        assertSame('/posts/reply-1', $cards[1]['children'][2]['href']);
    }

    public function testSnapshotRootAndCommentLikesUseTheOnlineControlContractAndQueueIntent(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
function element(type) {
  return { type, children: [], className: '', dataset: {}, style: {}, textContent: '', attributes: {},
    appendChild(child) { this.children.push(child); return child; }, removeChild(child) { this.children.splice(this.children.indexOf(child), 1); },
    get firstChild() { return this.children[0] || null; }, setAttribute(name, value) { this.attributes[name] = value; },
    addEventListener(name, handler) { this.listeners = this.listeners || {}; this.listeners[name] = handler; } };
}
global.window = {};
global.document = { addEventListener() {}, createElement: element };
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
const rows = [
  ['root-1', '', 'Topic', 'Root body', 'author-a', '2026-10-01T12:00:00Z'],
  ['reply-1', 'root-1', '', 'Reply body', 'author-b', '2026-10-01T12:01:00Z']
];
let calls = 0;
const database = { exec() { calls += 1; return calls === 1 ? [{ values: [['2026-10-01T12:01:00Z', 1]] }] : [{ values: rows }]; } };
const queued = [];
const content = element('div');
window.forumOfflineSnapshot.renderThreadDetail({ content, database, threadId: 'root-1', setStatus() {}, onQueueReaction(target, summary, payload) { queued.push({ target, summary, payload }); return Promise.resolve(); } });
const rootButton = content.children[0].children[4].children[0];
const replyButton = content.children[1].children[3].children[0];
rootButton.listeners.click();
replyButton.listeners.click();
Promise.resolve().then(() => Promise.resolve()).then(() => process.stdout.write(JSON.stringify({
  buttons: [rootButton, replyButton].map((button) => ({ className: button.className, text: button.textContent, action: button.attributes['data-action'], tag: button.attributes['data-tag'], pressed: button.attributes['aria-pressed'] })), queued
})));
NODE;
        $command = sprintf('node -e %s %s', escapeshellarg($script), escapeshellarg(__DIR__ . '/../public/assets/offline_reader.js'));
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Offline Like control helper failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame(['thread-reaction-button', 'thread-reaction-button'], array_column($result['buttons'], 'className'));
        assertSame(['Liked', 'Liked'], array_column($result['buttons'], 'text'));
        assertSame(['queue-offline-like', 'queue-offline-like'], array_column($result['buttons'], 'action'));
        assertSame('thread', $result['queued'][0]['target']['kind']);
        assertSame('post', $result['queued'][1]['target']['kind']);
        assertSame('thread_tag', $result['queued'][0]['payload']['kind']);
        assertSame('post_tag', $result['queued'][1]['payload']['kind']);
    }
}
