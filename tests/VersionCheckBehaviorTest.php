<?php

declare(strict_types=1);

final class VersionCheckBehaviorTest
{
    public function testThreadNavigationHintsAreConsumedWithoutLosingAnchorOrHistoryState(): void
    {
        $result = $this->runScript(<<<'NODE'
const source = require('fs').readFileSync(process.argv[1], 'utf8');
const vm = require('vm');
function load(path, pending, current) {
  const store = {forum_pending_app_version: pending};
  const replacements = [], redirects = [];
  const state = {saved: 'history'};
  const window = {
    location: {href: 'https://example.test' + path, origin: 'https://example.test',
      replace(url) {redirects.push(url);}},
    history: {state, replaceState(value, title, url) {replacements.push({value, url});}},
    sessionStorage: {getItem(k) {return store[k];}, removeItem(k) {delete store[k];}},
    fetch() {return Promise.resolve({ok: true, text: () => Promise.resolve(current)});},
    setTimeout() {}, addEventListener() {}
  };
  const document = {addEventListener() {}, querySelector(selector) {
    return selector === 'meta[name="app-version"]' ? {getAttribute: () => current} : null;
  }};
  vm.runInNewContext(source, {window, document, URL});
  return {replacements, redirects, pending: store.forum_pending_app_version || ''};
}
process.stdout.write(JSON.stringify({
  created: load('/threads/thread-001?created_post_id=thread-001&__v=target', '', 'current'),
  reply: load('/threads/thread-001?page=2&created_post_id=reply-002&__v=target#post-reply-002', 'target', 'newer'),
  stale: load('/threads/thread-001?created_post_id=thread-001', 'target', 'old'),
  clean: load('/threads/thread-001#post-reply-002', '', 'current'),
  forte: load('/forte?selected=thread-001&created_post_id=reply-002', '', 'current')
}));
NODE);
        assertSame('https://example.test/threads/thread-001', $result['created']['replacements'][0]['url']);
        assertSame(['saved' => 'history'], $result['created']['replacements'][0]['value']);
        assertSame('https://example.test/threads/thread-001?page=2#post-reply-002', $result['reply']['replacements'][0]['url']);
        assertSame('', $result['reply']['pending']);
        assertSame([], $result['reply']['redirects']);
        assertSame([], $result['stale']['replacements']);
        assertSame(['https://example.test/threads/thread-001?created_post_id=thread-001&__v=target'], $result['stale']['redirects']);
        assertSame([], $result['clean']['replacements']);
        assertSame([], $result['forte']['replacements']);
    }

    public function testDocsVersionStateDoesNotConsumeOrOverwriteForumPendingUpdates(): void
    {
        $result = $this->runScript(<<<'NODE'
const source = require('fs').readFileSync(process.argv[1], 'utf8');
const vm = require('vm');
const store = {forum_pending_app_version: 'forum-next'};
const redirects = [], requests = [];
function load(version) {
  const handlers = {}, timers = [];
  const banner = {hidden: true, querySelector() {return {addEventListener() {}};}};
  const window = {
    sessionStorage: {getItem(k) {return store[k] || null;}, setItem(k,v) {store[k]=String(v);}, removeItem(k) {delete store[k];}},
    location: {href:'https://example.test/docs/guide.md', origin:'https://example.test', replace(v) {redirects.push(v);}, assign(v) {redirects.push(v);}},
    fetch(url) {requests.push(url); return Promise.resolve({ok:true, text:()=>Promise.resolve('docs-next')});},
    setTimeout(fn) {timers.push(fn); return timers.length;}, clearTimeout() {},
    addEventListener(name,fn) {handlers[name]=fn;}
  };
  const document = {visibilityState:'visible', addEventListener() {}, querySelector(selector) {
    if(selector==='[data-role="app-version-banner"]') return banner;
    const values = {'meta[name="app-version"]':version, 'meta[name="app-version-endpoint"]':'/api/docs-version',
      'meta[name="app-version-storage-key"]':'forum_pending_docs_version'};
    return selector in values ? {getAttribute:()=>values[selector]} : null;
  }};
  vm.runInNewContext(source,{window,document,URL});
  return {handlers,timers};
}
(async()=>{
  const first=load('docs-current'); first.handlers.pageshow(); first.timers[0]();
  await new Promise(resolve=>setImmediate(resolve));
  const pending=store.forum_pending_docs_version;
  const second=load('docs-next'); second.handlers.pageshow();
  process.stdout.write(JSON.stringify({store,pending,redirects,requests}));
})().catch(error=>{console.error(error);process.exit(1);});
NODE);
        assertSame('docs-next', $result['pending']);
        assertSame('forum-next', $result['store']['forum_pending_app_version']);
        assertTrue(!isset($result['store']['forum_pending_docs_version']));
        assertSame([], $result['redirects']);
        assertStringContains('/api/docs-version', $result['requests'][0]);
    }

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

    /**
     * @return array<string, mixed>
     */
    private function runComposeDraftClearScript(string $script): array
    {
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/assets/compose_draft_clear.js'),
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

    public function testStaleTargetVersionOnReloadClearsPendingInsteadOfReshowingBanner(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const state = { store: { forum_pending_app_version: 'target-version' }, replaceCalls: [], fetchCalls: [] };

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
    href: 'https://example.test/compose/thread?__v=target-version',
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
    return Promise.resolve({ ok: true, text() { return Promise.resolve('even-newer-version'); } });
  },
  setTimeout() { return 1; },
  setInterval() { return 1; },
  addEventListener() {}
};

global.document = {
  visibilityState: 'visible',
  querySelector(selector) {
    if (selector === 'meta[name="app-version"]') {
      return { getAttribute(name) { return name === 'content' ? 'even-newer-version' : ''; } };
    }
    if (selector === 'meta[name="app-version-endpoint"]') {
      return { getAttribute(name) { return name === 'content' ? '/api/version' : ''; } };
    }
    if (selector === '[data-role="app-version-banner"]') {
      return banner;
    }
    return null;
  },
  addEventListener() {}
};

vm.runInThisContext(source);

new Promise((resolve) => setImmediate(resolve)).then(() => {
  process.stdout.write(JSON.stringify({
    replaceCalls: state.replaceCalls,
    fetchCalls: state.fetchCalls,
    pendingVersion: state.store.forum_pending_app_version || '',
    bannerHidden: banner.hidden
  }));
});
NODE;

        $result = $this->runScript($script);

        assertSame([], $result['replaceCalls']);
        assertSame('', $result['pendingVersion']);
        assertSame(true, $result['bannerHidden']);
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

        $result = $this->runComposeDraftClearScript($script);

        assertSame('', $result['remainingLocalDraft']);
        assertSame('forum_compose_draft:thread', $result['recentlyClearedDraft']);
        assertStringContains('expires=Thu, 01 Jan 1970 00:00:00 GMT', $result['cookie']);
    }
}
