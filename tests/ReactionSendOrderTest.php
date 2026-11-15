<?php

declare(strict_types=1);

/**
 * The page sends reactions one at a time in click order, so Close then Resolve both land
 * without the second waiting on the server's write lock. A reaction saved to the Outbox
 * keeps saying so on its feedback line until the Outbox resends it.
 */
final class ReactionSendOrderTest
{
    public function testQuickStateClicksAreSentInOrderWithoutOverlap(): void
    {
        $result = $this->run(<<<'NODE'
respond = async (tag) => {
  if (tag === 'closed') await new Promise((done) => realSetTimeout(done, 40));
  return ok();
};
const first = clickHandler({ target: buttons.closed, preventDefault() {} });
const second = clickHandler({ target: buttons.resolved, preventDefault() {} });
await Promise.all([first, second]);
NODE);

        assertSame(['start:closed', 'end:closed', 'start:resolved', 'end:resolved'], $result['events']);
        // Record times have whole seconds; the later mark must not tie with the earlier one.
        assertTrue($result['seconds'][1] > $result['seconds'][0]);
        assertSame('Resolved.', $result['feedback']);
        assertSame('resolved', $result['resolution']);
        assertSame(0, $result['stored']);
    }

    public function testOutboxNoticeOutlivesLaterMessagesUntilTheResendLands(): void
    {
        $result = $this->run(<<<'NODE'
respond = async (tag) => tag === 'like' ? busy() : ok();
await clickHandler({ target: buttons.like, preventDefault() {} });
const queued = feedbackNode.textContent;
await clickHandler({ target: buttons.closed, preventDefault() {} });
const afterClose = feedbackNode.textContent;
respond = async () => ok();
await retryTimers.shift()();
output.queued = queued;
output.afterClose = afterClose;
NODE);

        assertTrue(str_contains($result['queued'], 'The server is busy.'));
        assertTrue(str_contains($result['queued'], 'Saved in your Outbox'));
        assertSame('Closed. Like is saved in your Outbox and will be sent automatically.', $result['afterClose']);
        assertSame('Liked.', $result['feedback']);
        assertSame(['start:like', 'end:like', 'start:closed', 'end:closed', 'start:like', 'end:like'], $result['events']);
        assertSame(0, $result['stored']);
    }

    public function testRefusedResendReplacesTheNoticeWithTheError(): void
    {
        $result = $this->run(<<<'NODE'
respond = async () => busy();
await clickHandler({ target: buttons.like, preventDefault() {} });
respond = async () => refused();
await retryTimers.shift()();
NODE);

        assertSame('Post is closed.', $result['feedback']);
        assertSame('Like', $result['likeText']);
        assertSame(1, $result['stored']);
    }

    /** @return array<string, mixed> */
    private function run(string $steps): array
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const events = [];
const seconds = [];
const output = {};
let clickHandler = null;
let respond = null;
const retryTimers = [];
const realSetTimeout = setTimeout;
// The Outbox retry (15 s and up) is captured so the test can fire it; the 20 s fetch abort timer never fires.
global.setTimeout = function (callback, delay) {
  if (delay === 20000) return 0;
  if (delay >= 10000) { retryTimers.push(callback); return 0; }
  return realSetTimeout(callback, delay);
};
class HTMLButtonElement {
  constructor(tag, text, applied, state) {
    this.disabled = false; this.hidden = false; this.textContent = text;
    this.attributes = { 'data-tag': tag, 'data-applied-label': applied };
    if (state) this.attributes['data-state-tag'] = '1';
  }
  getAttribute(name) { return name in this.attributes ? this.attributes[name] : null; }
  setAttribute(name, value) { this.attributes[name] = String(value); }
  removeAttribute(name) { delete this.attributes[name]; }
  closest(selector) { return selector === '[data-action="apply-post-tag"]' ? this : null; }
}
const buttons = {
  like: new HTMLButtonElement('like', 'Like', 'Liked', false),
  closed: new HTMLButtonElement('closed', 'Close', 'Closed', true),
  resolved: new HTMLButtonElement('resolved', 'Resolve', 'Resolved', true),
};
const feedbackNode = { textContent: '', hidden: true, setAttribute(name, value) { this[name] = value; }, querySelector() { return null; }, append() {} };
const root = {
  hidden: false,
  attributes: { 'data-post-id': 'post-1' },
  getAttribute(name) { return this.attributes[name] || ''; },
  setAttribute(name, value) { this.attributes[name] = String(value); },
  removeAttribute(name) { delete this.attributes[name]; },
  querySelector(selector) { return selector === '[data-role="post-reaction-feedback"]' ? feedbackNode : null; },
  querySelectorAll(selector) { return selector === '[data-state-tag]' ? [buttons.closed, buttons.resolved] : []; },
  addEventListener(type, handler) { if (type === 'click') clickHandler = handler; },
  matches(selector) { return selector === '.post-card[data-post-id]'; }
};
function response(status, text) { return { status, headers: { get() { return ''; } }, async text() { return text; } }; }
function ok() { return response(200, 'status=ok\nwrote_record=yes\nviewer_is_approved=yes\n'); }
function busy() { return response(503, '{"status":"busy"}'); }
function refused() { return response(200, 'status=error\nerror=Post is closed.\n'); }
global.navigator = { onLine: true };
global.Element = HTMLButtonElement;
global.HTMLButtonElement = HTMLButtonElement;
const store = new Map();
global.window = {
  location: { search: '' },
  crypto: { randomUUID() { return 'uuid-' + Math.random().toString(16).slice(2); } },
  __forumBrowserIdentity: { async ensureActionIdentity() {} }
};
window.forumOutboxStorage = {
  list() { return Promise.resolve(Array.from(store.values())); },
  save(item) { store.set(item.id, item); return Promise.resolve(item); },
  remove(id) { store.delete(id); return Promise.resolve(); }
};
global.fetch = async function (url, options) {
  const tag = new URLSearchParams(options.body).get('tag');
  events.push('start:' + tag);
  seconds.push(Math.floor(Date.now() / 1000));
  const result = await respond(tag);
  events.push('end:' + tag);
  return result;
};
global.document = {
  addEventListener() {},
  dispatchEvent() {},
  querySelector() { return null; },
  querySelectorAll() { return []; },
  createElement() { return { addEventListener() {} }; }
};
for (const file of process.argv.slice(1)) vm.runInThisContext(fs.readFileSync(file, 'utf8'));
window.ForumThreadReactions.bindWithin(root);
(async () => {
STEPS
  process.stdout.write(JSON.stringify(Object.assign(output, {
    events, seconds, feedback: feedbackNode.textContent, stored: store.size,
    resolution: root.attributes['data-resolution'] || '', likeText: buttons.like.textContent
  })));
  process.exit(0);
})().catch((error) => { console.error(error && error.stack || error); process.exit(1); });
NODE;
        $command = 'node -e ' . escapeshellarg(str_replace('STEPS', $steps, $script));
        foreach (['outbox_store.js', 'outbox_sender.js', 'thread_reactions.js'] as $asset) {
            $command .= ' ' . escapeshellarg(__DIR__ . '/../public/assets/' . $asset);
        }
        exec($command . ' 2>&1', $lines, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Reaction send order helper failed: ' . implode("\n", $lines));
        }

        return json_decode(implode("\n", $lines), true, 512, JSON_THROW_ON_ERROR);
    }
}
