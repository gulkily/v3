<?php

declare(strict_types=1);

/**
 * Reaction buttons save the reaction to the Outbox before any request, remove it once the
 * server confirms it, and keep it (to be resent) when the request fails.
 */
final class ReactionOutboxTest
{
    public function testConfirmedLikeIsSavedBeforeAnyRequestAndRemovedAfterward(): void
    {
        $result = $this->clickLike("return okResponse();");

        assertSame('save:sending', $result['events'][0]);
        assertSame('ensure', $result['events'][1]);
        assertSame('/api/apply_post_tag', $result['events'][2]);
        assertSame('remove', $result['events'][3]);
        assertSame([], $result['items']);
        assertSame('Liked', $result['buttonText']);
    }

    public function testSafariLoadFailedKeepsTheLikeQueuedAndApplied(): void
    {
        $result = $this->clickLike("throw new TypeError('Load failed');");

        assertSame(1, count($result['items']));
        assertSame('waiting_for_connection', $result['items'][0]['state']);
        assertSame('post_tag', $result['items'][0]['payload']['kind']);
        assertSame('post-1', $result['items'][0]['payload']['postId']);
        assertSame('Liked', $result['buttonText']);
        assertTrue(str_contains($result['feedback'], "Couldn't reach the server."));
        assertTrue(str_contains($result['feedback'], 'Outbox'));
        assertFalse(str_contains($result['feedback'], 'Load failed'));
    }

    public function testBusyServerKeepsTheLikeQueued(): void
    {
        $result = $this->clickLike("return { status: 503, headers: { get() { return ''; } }, async text() { return '{\"status\":\"busy\"}'; } };");

        assertSame('waiting_for_connection', $result['items'][0]['state']);
        assertTrue(str_contains($result['feedback'], 'The server is busy.'));
    }

    public function testRejectedLikeStaysInTheOutboxAndTheButtonIsRestored(): void
    {
        $result = $this->clickLike("return { status: 200, headers: { get() { return ''; } }, async text() { return 'status=error\\nerror=Post is closed.\\n'; } };");

        assertSame('rejected', $result['items'][0]['state']);
        assertSame('Post is closed.', $result['items'][0]['outcome']['message']);
        assertSame('Like', $result['buttonText']);
    }

    public function testSenderResendsAStrandedPostLikeAndRemovesItOnSuccess(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
global.navigator = { onLine: true };
global.window = { crypto: { randomUUID() { return 'uuid'; } } };
const store = new Map();
const events = [];
window.forumOutboxStorage = {
  list() { return Promise.resolve(Array.from(store.values())); },
  save(item) { events.push('save:' + item.state); store.set(item.id, item); return Promise.resolve(item); },
  remove(id) { events.push('remove'); store.delete(id); return Promise.resolve(); }
};
window.ForumThreadReactions = {
  submitSavedReaction(kind, target, tag) { events.push(['submit', kind, target, tag].join(':')); return Promise.resolve({ postId: target }); }
};
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
const stale = new Date(Date.now() - 10 * 60 * 1000).toISOString();
const item = window.forumOutbox.createItem({ id: 'outbox-reaction-1', action: 'reaction', state: 'sending', createdAt: stale, updatedAt: stale,
  target: { kind: 'post', id: 'post-1' }, payload: { kind: 'post_tag', postId: 'post-1', tag: 'like' } });
store.set(item.id, item);
window.forumOutboxSender.processQueuedOutbox().then((results) => {
  process.stdout.write(JSON.stringify({ events, remaining: store.size, state: results[0].state }));
});
NODE;
        $result = $this->runNode($script, ['outbox_store.js', 'outbox_sender.js']);

        assertSame(['save:queued', 'save:sending', 'submit:post:post-1:like', 'remove'], $result['events']);
        assertSame(0, $result['remaining']);
        assertSame('accepted', $result['state']);
    }

    /**
     * @return array<string, mixed>
     */
    private function clickLike(string $fetchBody): array
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const events = [];
let clickHandler = null;
class HTMLButtonElement {
  constructor() { this.disabled = false; this.textContent = 'Like'; this.attributes = {}; }
  getAttribute(name) {
    if (name === 'data-tag') return 'like';
    if (name === 'data-applied-label') return 'Liked';
    return this.attributes[name] || null;
  }
  setAttribute(name, value) { this.attributes[name] = String(value); }
  removeAttribute(name) { delete this.attributes[name]; }
  closest(selector) { return selector === '[data-action="apply-post-tag"]' ? this : null; }
}
const button = new HTMLButtonElement();
const feedbackNode = { textContent: '', hidden: true, setAttribute(name, value) { this[name] = value; }, querySelector() { return null; }, append() {} };
const root = {
  hidden: false,
  getAttribute(name) { return name === 'data-post-id' ? 'post-1' : ''; },
  querySelector(selector) { return selector === '[data-role="post-reaction-feedback"]' ? feedbackNode : null; },
  querySelectorAll() { return []; },
  addEventListener(type, handler) { if (type === 'click') clickHandler = handler; },
  matches(selector) { return selector === '.post-card[data-post-id]'; }
};
global.navigator = { onLine: true };
global.Element = HTMLButtonElement;
global.HTMLButtonElement = HTMLButtonElement;
const store = new Map();
global.window = {
  location: { search: '' },
  crypto: { randomUUID() { return 'uuid'; } },
  __forumBrowserIdentity: { async ensureActionIdentity() { events.push('ensure'); } }
};
window.forumOutboxStorage = {
  list() { return Promise.resolve(Array.from(store.values())); },
  save(item) { events.push('save:' + item.state); store.set(item.id, item); return Promise.resolve(item); },
  remove(id) { events.push('remove'); store.delete(id); return Promise.resolve(); }
};
global.setTimeout = function () { return 1; };
global.clearTimeout = function () {};
function okResponse() {
  return { status: 200, headers: { get() { return ''; } }, async text() { return 'status=ok\nwrote_record=yes\n'; } };
}
global.fetch = async function (url) { events.push(url); FETCH_BODY };
global.document = {
  addEventListener() {},
  querySelector() { return null; },
  querySelectorAll() { return []; },
  createElement() { return { addEventListener() {} }; }
};
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
vm.runInThisContext(fs.readFileSync(process.argv[2], 'utf8'));
window.ForumThreadReactions.bindWithin(root);
clickHandler({ target: button, preventDefault() {} }).then(() => {
  process.stdout.write(JSON.stringify({
    events, items: Array.from(store.values()), feedback: feedbackNode.textContent, buttonText: button.textContent
  }));
});
NODE;

        return $this->runNode(str_replace('FETCH_BODY', $fetchBody, $script), ['outbox_store.js', 'thread_reactions.js']);
    }

    /**
     * @param list<string> $assets
     * @return array<string, mixed>
     */
    private function runNode(string $script, array $assets): array
    {
        $command = 'node -e ' . escapeshellarg($script);
        foreach ($assets as $asset) {
            $command .= ' ' . escapeshellarg(__DIR__ . '/../public/assets/' . $asset);
        }
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Reaction outbox helper failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }
}
