<?php

declare(strict_types=1);

final class VersionCheckBehaviorTest
{
    /**
     * @return array<string, mixed>
     */
    private function runScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/version_check.js'),
        );

        $output = [];
        $exitCode = 0;
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Node helper execution failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testShowBannerPersistsPendingVersionForLaterNavigation(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const state = { store: {}, bannerHidden: true, visibilityHandlers: [], pageshowHandlers: [], timeoutCalls: [], timeoutHandlers: [], intervalCalls: [], fetchCalls: [] };

const banner = {
  hidden: true,
  querySelector(selector) {
    if (selector === '[data-action="reload-for-new-version"]') {
      return { addEventListener() {} };
    }
    return null;
  }
};

global.window = {
  location: {
    href: 'https://example.test/compose/thread',
    origin: 'https://example.test',
    assign() {},
    replace() {}
  },
  sessionStorage: {
    getItem(key) { return Object.prototype.hasOwnProperty.call(state.store, key) ? state.store[key] : null; },
    setItem(key, value) { state.store[key] = String(value); },
    removeItem(key) { delete state.store[key]; }
  },
  fetch(url) {
    state.fetchCalls.push(String(url));
    return Promise.resolve({ ok: true, text() { return Promise.resolve('next-version'); } });
  },
  setTimeout(fn, delay) {
    state.timeoutCalls.push(delay);
    state.timeoutHandlers.push(fn);
    return 1;
  },
  setInterval(fn, delay) {
    state.intervalCalls.push(delay);
    return 1;
  },
  addEventListener(type, handler) {
    if (type === 'pageshow') {
      state.pageshowHandlers.push(handler);
    }
  }
};

global.document = {
  visibilityState: 'visible',
  querySelector(selector) {
    if (selector === 'meta[name="app-version"]') {
      return { getAttribute(name) { return name === 'content' ? 'current-version' : ''; } };
    }
    if (selector === 'meta[name="app-version-endpoint"]') {
      return { getAttribute(name) { return name === 'content' ? '/api/version' : ''; } };
    }
    if (selector === '[data-role="app-version-banner"]') {
      return banner;
    }
    return null;
  },
  addEventListener(type, handler) {
    if (type === 'visibilitychange') {
      state.visibilityHandlers.push(handler);
    }
  }
};

vm.runInThisContext(source);

(async () => {
  state.timeoutHandlers.shift()();
  await new Promise((resolve) => setImmediate(resolve));
  state.bannerHidden = banner.hidden;
  process.stdout.write(JSON.stringify({
    bannerHidden: state.bannerHidden,
    pendingVersion: state.store.forum_pending_app_version || '',
    fetchCalls: state.fetchCalls
  }));
})().catch((error) => {
  process.stderr.write(String(error && error.stack ? error.stack : error));
  process.exit(1);
});
NODE;

        $result = $this->runScript($script);

        assertSame('next-version', $result['pendingVersion']);
        assertSame(true, count($result['fetchCalls']) >= 1);
    }

    public function testPendingVersionForcesVersionBypassOnLaterStaleLoad(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const state = { store: { forum_pending_app_version: 'next-version' }, replaceCalls: [], fetchCalls: [] };

global.window = {
  location: {
    href: 'https://example.test/compose/thread',
    origin: 'https://example.test',
    assign() {},
    replace(url) { state.replaceCalls.push(String(url)); }
  },
  sessionStorage: {
    getItem(key) { return Object.prototype.hasOwnProperty.call(state.store, key) ? state.store[key] : null; },
    setItem(key, value) { state.store[key] = String(value); },
    removeItem(key) { delete state.store[key]; }
  },
  fetch(url) {
    state.fetchCalls.push(String(url));
    return Promise.resolve({ ok: true, text() { return Promise.resolve('next-version'); } });
  },
  setTimeout() { return 1; },
  setInterval() { return 1; },
  addEventListener() {}
};

global.document = {
  visibilityState: 'visible',
  querySelector(selector) {
    if (selector === 'meta[name="app-version"]') {
      return { getAttribute(name) { return name === 'content' ? 'current-version' : ''; } };
    }
    if (selector === 'meta[name="app-version-endpoint"]') {
      return { getAttribute(name) { return name === 'content' ? '/api/version' : ''; } };
    }
    return null;
  },
  addEventListener() {}
};

vm.runInThisContext(source);

process.stdout.write(JSON.stringify({
  replaceCalls: state.replaceCalls,
  fetchCalls: state.fetchCalls,
  pendingVersion: state.store.forum_pending_app_version || ''
}));
NODE;

        $result = $this->runScript($script);

        assertSame(['https://example.test/compose/thread?__v=next-version'], $result['replaceCalls']);
        assertSame([], $result['fetchCalls']);
        assertSame('next-version', $result['pendingVersion']);
    }

    public function testSatisfiedPendingVersionClearsStorageWithoutReloadLoop(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const state = { store: { forum_pending_app_version: 'next-version' }, replaceCalls: [], fetchCalls: [], timeoutCalls: [], timeoutHandlers: [], intervalCalls: [] };

global.window = {
  location: {
    href: 'https://example.test/compose/thread?__v=next-version',
    origin: 'https://example.test',
    assign() {},
    replace(url) { state.replaceCalls.push(String(url)); }
  },
  sessionStorage: {
    getItem(key) { return Object.prototype.hasOwnProperty.call(state.store, key) ? state.store[key] : null; },
    setItem(key, value) { state.store[key] = String(value); },
    removeItem(key) { delete state.store[key]; }
  },
  fetch(url) {
    state.fetchCalls.push(String(url));
    return Promise.resolve({ ok: true, text() { return Promise.resolve('next-version'); } });
  },
  setTimeout(fn, delay) {
    state.timeoutCalls.push(delay);
    state.timeoutHandlers.push(fn);
    return 1;
  },
  setInterval(fn, delay) {
    state.intervalCalls.push(delay);
    return 1;
  },
  addEventListener() {}
};

global.document = {
  visibilityState: 'visible',
  querySelector(selector) {
    if (selector === 'meta[name="app-version"]') {
      return { getAttribute(name) { return name === 'content' ? 'next-version' : ''; } };
    }
    if (selector === 'meta[name="app-version-endpoint"]') {
      return { getAttribute(name) { return name === 'content' ? '/api/version' : ''; } };
    }
    return null;
  },
  addEventListener() {}
};

vm.runInThisContext(source);

state.timeoutHandlers.shift()();

new Promise((resolve) => setImmediate(resolve)).then(() => {
  process.stdout.write(JSON.stringify({
    replaceCalls: state.replaceCalls,
    fetchCalls: state.fetchCalls,
    pendingVersion: state.store.forum_pending_app_version || ''
  }));
});
NODE;

        $result = $this->runScript($script);

        assertSame([], $result['replaceCalls']);
        assertSame('', $result['pendingVersion']);
        assertSame(true, count($result['fetchCalls']) >= 1);
    }

    public function testVersionChecksBackOffUntilSteadyDelay(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const state = { store: {}, timeoutCalls: [], timeoutHandlers: [], fetchCalls: [] };

global.window = {
  location: {
    href: 'https://example.test/compose/thread',
    origin: 'https://example.test',
    assign() {},
    replace() {}
  },
  sessionStorage: {
    getItem(key) { return Object.prototype.hasOwnProperty.call(state.store, key) ? state.store[key] : null; },
    setItem(key, value) { state.store[key] = String(value); },
    removeItem(key) { delete state.store[key]; }
  },
  fetch(url) {
    state.fetchCalls.push(String(url));
    return Promise.resolve({ ok: true, text() { return Promise.resolve('current-version'); } });
  },
  setTimeout(fn, delay) {
    state.timeoutCalls.push(delay);
    state.timeoutHandlers.push(fn);
    return state.timeoutCalls.length;
  },
  setInterval() { return 1; },
  addEventListener() {}
};

global.document = {
  visibilityState: 'visible',
  querySelector(selector) {
    if (selector === 'meta[name="app-version"]') {
      return { getAttribute(name) { return name === 'content' ? 'current-version' : ''; } };
    }
    if (selector === 'meta[name="app-version-endpoint"]') {
      return { getAttribute(name) { return name === 'content' ? '/api/version' : ''; } };
    }
    return null;
  },
  addEventListener() {}
};

vm.runInThisContext(source);

(async () => {
  for (let i = 0; i < 5; i += 1) {
    const handler = state.timeoutHandlers.shift();
    handler();
    await new Promise((resolve) => setImmediate(resolve));
  }

  process.stdout.write(JSON.stringify({
    timeoutCalls: state.timeoutCalls,
    fetchCallCount: state.fetchCalls.length
  }));
})().catch((error) => {
  process.stderr.write(String(error && error.stack ? error.stack : error));
  process.exit(1);
});
NODE;

        $result = $this->runScript($script);

        assertSame([15000, 30000, 60000, 120000, 120000, 120000], $result['timeoutCalls']);
        assertSame(5, $result['fetchCallCount']);
    }

    public function testPendingComposeDraftClearCookieRemovesLocalDraftAndStoresSessionMarker(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const state = {
  localStore: { 'forum_compose_draft:thread': '{"fields":{"body":"Saved"}}' },
  sessionStore: {},
  cookie: 'forum_clear_compose_draft=forum_compose_draft%3Athread',
  fetchCalls: []
};

global.window = {
  location: {
    href: 'https://example.test/threads/root-001',
    origin: 'https://example.test',
    assign() {},
    replace() {}
  },
  localStorage: {
    getItem(key) { return Object.prototype.hasOwnProperty.call(state.localStore, key) ? state.localStore[key] : null; },
    setItem(key, value) { state.localStore[key] = String(value); },
    removeItem(key) { delete state.localStore[key]; }
  },
  sessionStorage: {
    getItem(key) { return Object.prototype.hasOwnProperty.call(state.sessionStore, key) ? state.sessionStore[key] : null; },
    setItem(key, value) { state.sessionStore[key] = String(value); },
    removeItem(key) { delete state.sessionStore[key]; }
  },
  fetch(url) {
    state.fetchCalls.push(String(url));
    return Promise.resolve({ ok: true, text() { return Promise.resolve('current-version'); } });
  },
  setTimeout() { return 1; },
  setInterval() { return 1; },
  addEventListener() {}
};

global.document = {
  visibilityState: 'visible',
  get cookie() {
    return state.cookie;
  },
  set cookie(value) {
    state.cookie = String(value);
  },
  querySelector(selector) {
    if (selector === 'meta[name="app-version"]') {
      return { getAttribute(name) { return name === 'content' ? 'current-version' : ''; } };
    }
    if (selector === 'meta[name="app-version-endpoint"]') {
      return { getAttribute(name) { return name === 'content' ? '/api/version' : ''; } };
    }
    return null;
  },
  addEventListener() {}
};

vm.runInThisContext(source);

process.stdout.write(JSON.stringify({
  remainingLocalDraft: state.localStore['forum_compose_draft:thread'] || '',
  recentlyClearedDraft: state.sessionStore.forum_recently_cleared_compose_draft || '',
  cookie: state.cookie
}));
NODE;

        $result = $this->runScript($script);

        assertSame('', $result['remainingLocalDraft']);
        assertSame('forum_compose_draft:thread', $result['recentlyClearedDraft']);
        assertStringContains('expires=Thu, 01 Jan 1970 00:00:00 GMT', $result['cookie']);
    }
}
