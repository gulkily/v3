<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';
require_once __DIR__ . '/Support/ProfileRegressionContract.php';
require_once __DIR__ . '/Support/ProfileRegressionFixture.php';

final class OfflineNavigationWorkerTest
{
    /** @return array<string, bool> */
    private function navigationPolicy(): array
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
global.self = {
  location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' },
  addEventListener() {}
};
const paths = [
  '/', '/threads', '/threads/?view=liked&sort=top', '/threads/root-001',
  '/tags', '/tags/', '/tags/general', '/tags/bug-fix', '/tags/BUG-Fix/', '/tags/Bad',
  '/tools/outbox/',
  '/recent/', '/recent/?page=2', '/activity/', '/activity/?view=all&page=2', '/activity/?view=security', '/forte/activity/?view=security', '/api/forte_activity_page?view=security',
  '/compose/thread', '/profiles/alice', '/profiles/alice?posts_page=2', '/search/', '/tags/bug/extra', '/TAGS/bug', '/tags/bug_fix'
];
vm.runInThisContext(source);
const policy = Object.fromEntries(paths.map((path) => [
  path,
  supportsOfflineNavigation(new URL(path, 'https://forum.test'))
]));
process.stdout.write(JSON.stringify(policy));
NODE;

        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/service_worker.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Worker policy helper failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testOfflineNavigationPolicyAdmitsOnlySupportedReadRoutes(): void
    {
        $policy = $this->navigationPolicy();

        foreach (['/', '/threads', '/threads/?view=liked&sort=top', '/threads/root-001', '/tags', '/tags/', '/tags/general', '/tags/bug-fix', '/tags/BUG-Fix/', '/tags/Bad'] as $path) {
            assertSame(true, $policy[$path]);
        }
        foreach (['/recent/', '/recent/?page=2', '/activity/', '/activity/?view=all&page=2', '/activity/?view=security', '/forte/activity/?view=security', '/api/forte_activity_page?view=security', '/compose/thread', '/profiles/alice', '/profiles/alice?posts_page=2', '/search/', '/tags/bug/extra', '/TAGS/bug', '/tags/bug_fix'] as $path) {
            assertSame(false, $policy[$path]);
        }
    }

    public function testUncachedActivityPositionFailsOnlineRecoveryRatherThanUsingReaderShell(): void
    {
        $script = <<<'NODE'
const fs = require('fs'), vm = require('vm');
global.self = { location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' }, addEventListener() {} };
let cacheLookups = 0;
global.caches = { open: async () => ({ match: async () => { cacheLookups++; return new Response('Wrong first page'); } }) };
global.fetch = async () => { throw new Error('offline'); };
vm.runInThisContext(fs.readFileSync(process.argv[1], 'utf8'));
(async () => {
  for (const path of ['/activity/?view=all&page=2', '/recent/', '/recent/?page=2']) {
  const request = new Request('https://forum.test' + path);
  if (cacheableRequest(request)) throw new Error('A later page was cacheable');
  try { await networkFirstNavigation({ request, waitUntil() {} }); throw new Error('Wrong fallback supplied'); }
  catch (error) { if (error.message !== 'offline') throw error; }
  if (cacheLookups !== 0) throw new Error('An unrelated shell was read');
  }
})().catch(error => { console.error(error); process.exitCode = 1; });
NODE;
        $output = [];
        exec('node -e ' . escapeshellarg($script) . ' ' . escapeshellarg(__DIR__ . '/../public/service_worker.js') . ' 2>&1', $output, $code);
        assertSame(0, $code, implode("\n", $output));
    }

    public function testRefreshMessageReportsSuccessAndFailureToRequester(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const runtime = JSON.parse(process.argv[2]);
async function refreshResult(fail) {
  const listeners = {};
  const port = { messages: [], postMessage(message) { this.messages.push(message); } };
  const context = {
    URL, Request, Response, Promise, TextEncoder, crypto: require('crypto').webcrypto, console: { info() {}, error() {} },
    self: {
      __forumBrowserRuntime: runtime,
      location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' },
      navigator: { onLine: true },
      addEventListener(type, listener) { listeners[type] = listener; },
      skipWaiting() { return Promise.resolve(); },
      clients: { claim() { return Promise.resolve(); } }
    },
    caches: {
      open() { return Promise.resolve({ put() { return Promise.resolve(); }, match() { return Promise.resolve(undefined); }, keys() { return Promise.resolve([]); } }); },
      keys() { return Promise.resolve([]); }, delete() { return Promise.resolve(true); }
    },
    fetch: async function (request) {
      if (fail) throw new Error('Network unavailable');
      const pathname = new URL(request.url).pathname;
      const html = pathname === '/offline/reader/'
        ? '<section data-offline-reader data-runtime-url="/assets/sql-wasm.wasm"><script src="/assets/offline_reader.js"></script></section>'
        : pathname === '/offline/' ? '<script src="/assets/offline_health.js"></script>' : 'asset';
      return new Response(html, { status: 200 });
    }
  };
  vm.runInNewContext(source, context);
  let completion;
  listeners.message({ data: { type: 'refresh-offline-reader' }, ports: [port], waitUntil(promise) { completion = promise; } });
  try { await completion; } catch (error) {}
  return port.messages[0];
}
Promise.all([refreshResult(false), refreshResult(true)]).then((results) => process.stdout.write(JSON.stringify(results)));
NODE;
        foreach (ProfileRegressionContract::all() as $contract) {
            $command = sprintf(
                'node -e %s %s %s',
                escapeshellarg($script),
                escapeshellarg(__DIR__ . '/../public/service_worker.js'),
                escapeshellarg(json_encode($contract['runtime'], JSON_THROW_ON_ERROR)),
            );
            $output = [];
            exec($command . ' 2>&1', $output, $exitCode);
            if ($exitCode !== 0) {
                throw new RuntimeException('Worker refresh-message contract failed: ' . implode("\n", $output));
            }
            $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

            assertSame('offline-reader-refreshed', $result[0]['type']);
            assertSame('ready', $result[0]['status']);
            assertSame($contract['runtime']['offlineCacheName'], $result[0]['cacheName']);
            assertSame('offline-reader-refreshed', $result[1]['type']);
            assertSame('error', $result[1]['status']);
            assertSame('Network unavailable', $result[1]['errorMessage']);
        }
    }

    public function testMissingSnapshotDoesNotBlockReaderShellAndAssetCaching(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
async function refreshWithMissingSnapshot() {
  const listeners = {};
  const stored = [];
  const port = { messages: [], postMessage(message) { this.messages.push(message); } };
  const context = {
    URL, Request, Response, Promise, TextEncoder, crypto: require('crypto').webcrypto, console: { info() {}, error() {} },
    self: {
      location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' },
      navigator: { onLine: true },
      addEventListener(type, listener) { listeners[type] = listener; },
      skipWaiting() { return Promise.resolve(); },
      clients: { claim() { return Promise.resolve(); } }
    },
    caches: {
      open() { return Promise.resolve({ put(request) { stored.push(new URL(request.url || request).pathname); return Promise.resolve(); }, match() { return Promise.resolve(undefined); }, keys() { return Promise.resolve([]); } }); },
      keys() { return Promise.resolve([]); }, delete() { return Promise.resolve(true); }
    },
    fetch: async function (request) {
      const pathname = new URL(request.url).pathname;
      if (pathname === '/offline/snapshot.sqlite3') return new Response('missing', { status: 404 });
      const html = pathname === '/offline/reader/'
        ? '<section data-offline-reader><script src="/assets/offline_reader.js"></script></section>'
        : pathname === '/offline/' ? '<script src="/assets/offline_health.js"></script>' : 'asset';
      return new Response(html, { status: 200 });
    }
  };
  vm.runInNewContext(source, context);
  let completion;
  listeners.message({ data: { type: 'refresh-offline-reader' }, ports: [port], waitUntil(promise) { completion = promise; } });
  await completion;
  return { result: port.messages[0], stored };
}
refreshWithMissingSnapshot().then((outcome) => process.stdout.write(JSON.stringify(outcome)));
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/service_worker.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Worker missing-snapshot contract failed: ' . implode("\n", $output));
        }
        $outcome = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('ready', $outcome['result']['status']);
        assertTrue(in_array('/offline/reader/', $outcome['stored'], true), 'Reader shell should be cached.');
        assertTrue(in_array('/assets/offline_reader.js', $outcome['stored'], true), 'Reader assets should be cached.');
        assertTrue(in_array('/compose/thread', $outcome['stored'], true), 'The New post page should be cached for offline use.');
        assertTrue(in_array('/compose/reply', $outcome['stored'], true), 'The reply page should be cached for offline use.');
        assertTrue(!in_array('/offline/snapshot.sqlite3', $outcome['stored'], true), 'A missing snapshot must not be cached.');
    }

    public function testReaderRevisionFollowsEmbeddedSnapshotRevisionAndIsStoredByRefresh(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const { webcrypto } = require('crypto');
const source = fs.readFileSync(process.argv[1], 'utf8');
async function run() {
  const state = { snapshotRevision: 'a1' };
  const store = new Map();
  const listeners = {};
  const port = { messages: [], postMessage(message) { this.messages.push(message); } };
  const context = {
    URL, Request, Response, Promise, TextEncoder, crypto: webcrypto, console: { info() {}, error() {} },
    self: {
      location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' },
      navigator: { onLine: true },
      addEventListener(type, listener) { listeners[type] = listener; },
      skipWaiting() { return Promise.resolve(); },
      clients: { claim() { return Promise.resolve(); } }
    },
    caches: {
      open() {
        return Promise.resolve({
          put(key, response) { store.set(key, response); return Promise.resolve(); },
          match(key) { const hit = store.get(key); return Promise.resolve(hit ? hit.clone() : undefined); },
          keys() { return Promise.resolve([...store.keys()].map((url) => ({ url }))); }
        });
      },
      keys() { return Promise.resolve([]); }, delete() { return Promise.resolve(true); }
    },
    fetch: async function (request) {
      const pathname = new URL(request.url).pathname;
      if (pathname === '/offline/snapshot.sqlite3') return new Response('missing', { status: 404 });
      const html = pathname === '/offline/reader/'
        ? `<section data-offline-reader data-snapshot-revision="${state.snapshotRevision}"><script src="/assets/offline_reader.js"></script></section>`
        : pathname === '/offline/' ? `<section data-snapshot-revision="${state.snapshotRevision}"></section>`
        : pathname === '/tools/outbox/' ? '<section></section>' : 'asset';
      return new Response(html, { status: 200 });
    }
  };
  vm.runInNewContext(source, context);
  const a = await context.currentRevision();
  const aAgain = await context.currentRevision();
  state.snapshotRevision = 'b2';
  const b = await context.currentRevision();
  let completion;
  listeners.message({ data: { type: 'refresh-offline-reader' }, ports: [port], waitUntil(promise) { completion = promise; } });
  await completion;
  const stored = await context.storedRevision();
  return { a, aAgain, b, stored, status: port.messages[0].status };
}
run().then((outcome) => process.stdout.write(JSON.stringify(outcome)));
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/service_worker.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Worker revision contract failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertTrue(preg_match('/^[a-f0-9]{64}$/', (string) $result['a']) === 1, 'Revision should be a SHA-256 hex digest.');
        assertSame($result['a'], $result['aAgain']);
        assertTrue($result['a'] !== $result['b'], 'A changed embedded snapshot revision should change the reader revision.');
        assertSame($result['b'], $result['stored']);
        assertSame('ready', $result['status']);
    }

    public function testUnchangedRevisionSkipsRefreshAndFailedRefreshKeepsPreviousRevision(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const { webcrypto } = require('crypto');
const source = fs.readFileSync(process.argv[1], 'utf8');
async function run() {
  const state = { snapshotRevision: 'a1', failAssets: false };
  const fetched = [];
  const store = new Map();
  const listeners = {};
  const context = {
    URL, Request, Response, Promise, TextEncoder, crypto: webcrypto, console: { info() {}, error() {} },
    self: {
      location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' },
      navigator: { onLine: true },
      addEventListener(type, listener) { listeners[type] = listener; },
      skipWaiting() { return Promise.resolve(); },
      clients: { claim() { return Promise.resolve(); } }
    },
    caches: {
      open() {
        return Promise.resolve({
          put(key, response) { store.set(key, response); return Promise.resolve(); },
          match(key) { const hit = store.get(key); return Promise.resolve(hit ? hit.clone() : undefined); },
          keys() { return Promise.resolve([...store.keys()].map((url) => ({ url }))); }
        });
      },
      keys() { return Promise.resolve([]); }, delete() { return Promise.resolve(true); }
    },
    fetch: async function (request) {
      const pathname = new URL(request.url).pathname;
      fetched.push(pathname);
      if (pathname === '/offline/snapshot.sqlite3') return new Response('missing', { status: 404 });
      if (pathname.startsWith('/assets/') && state.failAssets) return new Response('boom', { status: 500 });
      const html = pathname === '/offline/reader/'
        ? `<section data-offline-reader data-snapshot-revision="${state.snapshotRevision}"><script src="/assets/offline_reader.js"></script></section>`
        : pathname === '/offline/' ? `<section data-snapshot-revision="${state.snapshotRevision}"></section>`
        : pathname === '/tools/outbox/' ? '<section></section>' : 'asset';
      return new Response(html, { status: 200 });
    }
  };
  vm.runInNewContext(source, context);
  async function message() {
    const port = { messages: [], postMessage(message) { this.messages.push(message); } };
    fetched.length = 0;
    let completion;
    listeners.message({ data: { type: 'refresh-offline-reader' }, ports: [port], waitUntil(promise) { completion = promise; } });
    try { await completion; } catch (error) {}
    return {
      status: port.messages[0].status,
      assetFetches: fetched.filter((path) => path.startsWith('/assets/')).length,
      snapshotFetches: fetched.filter((path) => path === '/offline/snapshot.sqlite3').length
    };
  }
  const first = await message();
  const revisionAfterFirst = await context.storedRevision();
  const second = await message();
  state.snapshotRevision = 'b2';
  state.failAssets = true;
  const third = await message();
  const revisionAfterFailure = await context.storedRevision();
  const cachedAssetAfterFailure = store.has('https://forum.test/assets/offline_reader.js');
  state.failAssets = false;
  const fourth = await message();
  return { first, second, third, fourth, revisionAfterFirst, revisionAfterFailure, cachedAssetAfterFailure, revisionAfterRecovery: await context.storedRevision() };
}
run().then((outcome) => process.stdout.write(JSON.stringify(outcome)));
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/service_worker.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Worker gating contract failed: ' . implode("\n", $output));
        }
        $outcome = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('ready', $outcome['first']['status']);
        assertSame('unchanged', $outcome['second']['status']);
        assertSame(0, $outcome['second']['assetFetches']);
        assertSame(0, $outcome['second']['snapshotFetches']);
        assertSame('error', $outcome['third']['status']);
        assertSame($outcome['revisionAfterFirst'], $outcome['revisionAfterFailure']);
        assertTrue($outcome['cachedAssetAfterFailure'], 'A failed refresh should keep previously cached assets.');
        assertSame('ready', $outcome['fourth']['status']);
        assertTrue($outcome['revisionAfterRecovery'] !== $outcome['revisionAfterFirst'], 'A successful refresh should store the new revision.');
    }

    public function testForcedRefreshRefetchesEvenWhenRevisionMatches(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const { webcrypto } = require('crypto');
const source = fs.readFileSync(process.argv[1], 'utf8');
async function run() {
  const fetched = [];
  const store = new Map();
  const listeners = {};
  const context = {
    URL, Request, Response, Promise, TextEncoder, crypto: webcrypto, console: { info() {}, error() {} },
    self: {
      location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' },
      navigator: { onLine: true },
      addEventListener(type, listener) { listeners[type] = listener; },
      skipWaiting() { return Promise.resolve(); },
      clients: { claim() { return Promise.resolve(); } }
    },
    caches: {
      open() {
        return Promise.resolve({
          put(key, response) { store.set(key, response); return Promise.resolve(); },
          match(key) { const hit = store.get(key); return Promise.resolve(hit ? hit.clone() : undefined); },
          keys() { return Promise.resolve([...store.keys()].map((url) => ({ url }))); }
        });
      },
      keys() { return Promise.resolve([]); }, delete() { return Promise.resolve(true); }
    },
    fetch: async function (request) {
      const pathname = new URL(request.url).pathname;
      fetched.push(pathname);
      if (pathname === '/offline/snapshot.sqlite3') return new Response('missing', { status: 404 });
      const html = pathname === '/offline/reader/'
        ? '<section data-offline-reader data-snapshot-revision="a1"><script src="/assets/offline_reader.js"></script></section>'
        : pathname === '/offline/' ? '<section data-snapshot-revision="a1"></section>'
        : pathname === '/tools/outbox/' ? '<section></section>' : 'asset';
      return new Response(html, { status: 200 });
    }
  };
  vm.runInNewContext(source, context);
  async function message(data) {
    const port = { messages: [], postMessage(message) { this.messages.push(message); } };
    fetched.length = 0;
    let completion;
    listeners.message({ data, ports: [port], waitUntil(promise) { completion = promise; } });
    try { await completion; } catch (error) {}
    const count = (path) => fetched.filter((fetchedPath) => fetchedPath === path).length;
    return {
      status: port.messages[0].status,
      assetFetches: fetched.filter((path) => path.startsWith('/assets/')).length,
      shellFetches: [count('/offline/reader/'), count('/offline/'), count('/tools/outbox/')]
    };
  }
  await message({ type: 'refresh-offline-reader' });
  const unchanged = await message({ type: 'refresh-offline-reader' });
  const forced = await message({ type: 'refresh-offline-reader', force: true });
  return { unchanged, forced };
}
run().then((outcome) => process.stdout.write(JSON.stringify(outcome)));
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/service_worker.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Worker force-refresh contract failed: ' . implode("\n", $output));
        }
        $outcome = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('unchanged', $outcome['unchanged']['status']);
        assertSame('ready', $outcome['forced']['status']);
        assertTrue($outcome['forced']['assetFetches'] > 0, 'A forced refresh should refetch assets even when the revision matches.');
        assertSame([1, 1, 1], $outcome['forced']['shellFetches'], 'A full refresh should fetch each shell page once.');
    }

    public function testOutboxNavigationFallsBackToItsDedicatedCachedShell(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const listeners = {};
global.self = {
  location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' },
  navigator: { onLine: false },
  addEventListener(type, listener) { listeners[type] = listener; }
};
global.caches = { open: async () => ({ match: async (url) => url.endsWith('/tools/outbox/') ? { shell: 'outbox' } : null }) };
global.fetch = async () => { throw new Error('Offline'); };
vm.runInThisContext(source);
let responsePromise;
listeners.fetch({
  request: { mode: 'navigate', method: 'GET', url: 'https://forum.test/tools/outbox/' },
  waitUntil() {},
  respondWith(promise) { responsePromise = promise; }
});
responsePromise.then((response) => process.stdout.write(JSON.stringify(response)));
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/service_worker.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Outbox worker fallback failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('outbox', $result['shell']);
    }

    public function testNotificationTapOpensItsPageInTheOpenWindowOrANewOne(): void
    {
        $script = <<<'NODE'
const fs = require('fs'), vm = require('vm');
const listeners = {};
const log = [];
let windows = [];
const self = {
  location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' },
  navigator: { onLine: true },
  addEventListener(type, listener) { listeners[type] = listener; },
  clients: { matchAll: async () => windows, openWindow: async (url) => { log.push(['open', url]); return null; } }
};
vm.runInNewContext(fs.readFileSync(process.argv[1], 'utf8'), { self, URL, Response, caches: {}, fetch, console });
const win = (url, navigate) => {
  const client = { url, focus: async () => { log.push(['focus', client.url]); return client; }, postMessage: (message) => log.push(['message', message.type, message.url]) };
  if (navigate === 'works') client.navigate = async (to) => { log.push(['navigate', to]); return { url: to }; };
  if (navigate === 'fails') client.navigate = async () => { throw new TypeError('not controlled'); };
  return client;
};
const tap = (url) => new Promise((done) => listeners.notificationclick({ notification: { data: { url }, close() {} }, waitUntil: (promise) => promise.then(done) }));
const reply = '/threads/root-1#post-reply-2';
(async () => {
  const results = {};
  windows = [];
  await tap(reply); results.closed = log.splice(0);
  windows = [win('https://forum.test/tags/', 'works')];
  await tap(reply); results.navigates = log.splice(0);
  windows = [win('https://forum.test/tags/', 'fails')];
  await tap('/messages/conversation/alice'); results.asksThePage = log.splice(0);
  windows = [win('https://forum.test/tags/')];
  await tap('/unread/'); results.noNavigate = log.splice(0);
  windows = [win('https://forum.test/', 'works'), win('https://forum.test/unread/', 'works')];
  await tap('/unread/'); results.alreadyThere = log.splice(0);
  windows = [win('https://other.test/', 'works')];
  await tap('https://evil.test/x'); results.otherOrigin = log.splice(0);
  self.navigator.onLine = false;
  windows = [];
  await tap('/messages/conversation/alice'); await tap('/unread/'); await tap(reply); results.offline = log.splice(0);
  process.stdout.write(JSON.stringify(results));
})().catch((error) => { console.error(error); process.exitCode = 1; });
NODE;
        $output = [];
        exec('node -e ' . escapeshellarg($script) . ' ' . escapeshellarg(__DIR__ . '/../public/service_worker.js') . ' 2>&1', $output, $code);
        assertSame(0, $code, implode("\n", $output));
        $r = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame([['open', 'https://forum.test/threads/root-1#post-reply-2']], $r['closed']);
        assertSame([['focus', 'https://forum.test/tags/'], ['navigate', 'https://forum.test/threads/root-1#post-reply-2']], $r['navigates']);
        assertSame([['focus', 'https://forum.test/tags/'], ['message', 'open-url', '/messages/conversation/alice']], $r['asksThePage']);
        assertSame([['focus', 'https://forum.test/tags/'], ['message', 'open-url', '/unread/']], $r['noNavigate']);
        assertSame([['focus', 'https://forum.test/unread/']], $r['alreadyThere'], 'a window already on the page is only focused');
        assertSame([['open', 'https://forum.test/']], $r['otherOrigin'], 'another site is never opened, and its windows are not used');
        assertSame([['open', 'https://forum.test/'], ['open', 'https://forum.test/'], ['open', 'https://forum.test/threads/root-1#post-reply-2']], $r['offline'],
            'offline, pages with no offline copy open the home page; a thread still opens');
    }

    public function testPageFollowsOnlySameSiteOpenUrlMessages(): void
    {
        $script = <<<'NODE'
const fs = require('fs'), vm = require('vm');
let listener = null;
const assigned = [];
const window = {
  forumBrowserRuntime: { offlineDiagnosticKey: 'diag' },
  location: { origin: 'https://forum.test', href: 'https://forum.test/tags/', assign: (url) => assigned.push(url) },
  addEventListener() {}, localStorage: { setItem() {}, removeItem() {} }
};
const navigator = { serviceWorker: { addEventListener(type, fn) { if (type === 'message') listener = fn; }, startMessages() {} } };
vm.runInNewContext(fs.readFileSync(process.argv[1], 'utf8'), { window, navigator, URL, console });
for (const data of [{ type: 'open-url', url: '/threads/t#post-p' }, { type: 'open-url', url: '//evil.test/x' }, { type: 'open-url', url: 'https://evil.test/' },
  { type: 'open-url', url: '/tags/' }, { type: 'other', url: '/unread/' }, null]) listener({ data });
process.stdout.write(JSON.stringify(assigned));
NODE;
        $output = [];
        exec('node -e ' . escapeshellarg($script) . ' ' . escapeshellarg(__DIR__ . '/../public/assets/pwa_registration.js') . ' 2>&1', $output, $code);
        assertSame(0, $code, implode("\n", $output));
        assertSame(['https://forum.test/threads/t#post-p'], json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testActivationRefreshesOnlyTheActiveProfileCacheFamily(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const runtime = JSON.parse(process.argv[2]);
const cacheNames = JSON.parse(process.argv[3]);
const listeners = {};
const deleted = [];
const context = {
  URL, Promise, console: { info() {}, error() {} },
  self: {
    __forumBrowserRuntime: runtime,
    location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' },
    navigator: { onLine: true },
    addEventListener(type, listener) { listeners[type] = listener; },
    clients: { claim() { return Promise.resolve(); } }
  },
  caches: {
    keys() { return Promise.resolve(cacheNames); },
    delete(name) { deleted.push(name); return Promise.resolve(true); }
  }
};
vm.runInNewContext(source, context);
let completion;
listeners.activate({ waitUntil(promise) { completion = promise; } });
completion.then(() => process.stdout.write(JSON.stringify(deleted)));
NODE;
        $contracts = ProfileRegressionContract::all();
        foreach ($contracts as $contract) {
            $runtime = $contract['runtime'];
            $cacheNames = [$runtime['offlineCachePrefix'] . 'v13', $runtime['offlineCacheName']];
            foreach ($contracts as $foreignContract) {
                if ($foreignContract['runtime']['offlineCacheName'] !== $runtime['offlineCacheName']) {
                    $cacheNames[] = $foreignContract['runtime']['offlineCacheName'];
                }
            }
            $command = sprintf(
                'node -e %s %s %s %s',
                escapeshellarg($script),
                escapeshellarg(__DIR__ . '/../public/service_worker.js'),
                escapeshellarg(json_encode($runtime, JSON_THROW_ON_ERROR)),
                escapeshellarg(json_encode($cacheNames, JSON_THROW_ON_ERROR)),
            );
            $output = [];
            exec($command . ' 2>&1', $output, $exitCode);
            if ($exitCode !== 0) {
                throw new RuntimeException('Worker cache-isolation contract failed: ' . implode("\n", $output));
            }

            assertSame([$runtime['offlineCachePrefix'] . 'v13'], json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR));
        }
    }

    /** @return array{fetched: list<string>, stored: list<string>, skipped: bool} */
    private function installWithExistingCaches(array $cacheNames): array
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const cacheNames = JSON.parse(process.argv[2]);
const listeners = {};
const fetched = [];
const stored = [];
let skipped = false;
const context = {
  URL, Request, Response, Promise, TextEncoder, crypto: require('crypto').webcrypto, console: { info() {}, error() {} },
  self: {
    location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' },
    navigator: { onLine: true },
    addEventListener(type, listener) { listeners[type] = listener; },
    skipWaiting() { skipped = true; return Promise.resolve(); },
    clients: { claim() { return Promise.resolve(); } }
  },
  caches: {
    open() { return Promise.resolve({ put(request) { stored.push(new URL(request.url || request).pathname); return Promise.resolve(); }, match() { return Promise.resolve(undefined); } }); },
    keys() { return Promise.resolve(cacheNames); }, delete() { return Promise.resolve(true); }
  },
  fetch: async function (request) {
    const pathname = new URL(request.url).pathname;
    fetched.push(pathname);
    return new Response(pathname === '/offline/reader/' ? '<script src="/assets/offline_reader.js"></script>' : 'asset', { status: 200 });
  }
};
vm.runInNewContext(source, context);
let completion;
listeners.install({ waitUntil(promise) { completion = promise; } });
completion.then(() => process.stdout.write(JSON.stringify({ fetched, stored, skipped })));
NODE;
        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/service_worker.js'),
            escapeshellarg(json_encode($cacheNames, JSON_THROW_ON_ERROR)),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Worker install contract failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testFirstInstallCachesOnlyTheReaderShellSoTheWorkerActivatesAtOnce(): void
    {
        // Another site's cache on the same origin does not count as this site's install.
        $outcome = $this->installWithExistingCaches(['someone-elses-cache']);

        assertSame(['/offline/reader/'], $outcome['fetched']);
        assertSame(['/offline/reader/'], $outcome['stored']);
        assertTrue($outcome['skipped'], 'A first install should activate without waiting.');
    }

    public function testUpdateInstallKeepsTheFullRefreshSoTheSnapshotIsNeverLost(): void
    {
        $outcome = $this->installWithExistingCaches(['zenmemes-offline-reader-v15']);

        assertTrue(in_array('/offline/snapshot.sqlite3', $outcome['fetched'], true), 'An update should refetch the snapshot.');
        assertTrue(in_array('/compose/reply', $outcome['stored'], true), 'An update should recache the compose pages.');
        assertTrue(in_array('/assets/offline_reader.js', $outcome['stored'], true), 'An update should recache reader assets.');
        assertTrue($outcome['skipped'], 'An update should activate without waiting.');
    }

    public function testFourthProfileFixtureUsesTheSameWorkerMatrix(): void
    {
        ProfileRegressionFixture::withFourthProfile(function (): void {
            $this->testRefreshMessageReportsSuccessAndFailureToRequester();
            $this->testActivationRefreshesOnlyTheActiveProfileCacheFamily();
        });
    }

    public function testComposeNavigationServesTheCachedPageWithTheReplyTargetFromTheAddress(): void
    {
        // Offline, New post and Reply open the real compose pages at their usual addresses.
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
const listeners = {};
const page = (title) => '<title>' + title + '</title><section data-compose-root><input type="hidden" name="thread_id" value=""><input type="hidden" name="parent_id" value=""></section>';
const context = {
  URL, Response, Promise, console: { info() {}, error() {} },
  self: { location: { href: 'https://forum.test/service_worker.js', origin: 'https://forum.test' }, navigator: { onLine: false }, addEventListener(type, listener) { listeners[type] = listener; } },
  caches: { open: async () => ({ match: async (url) => {
    const path = new URL(url).pathname;
    return path === '/compose/thread' ? new Response(page('New Post')) : path === '/compose/reply' ? new Response(page('Reply')) : undefined;
  } }) },
  fetch: async () => { throw new TypeError('Failed to fetch'); },
};
vm.runInNewContext(source, context);
const navigate = (url, method = 'GET') => new Promise((resolve) => listeners.fetch({
  request: { mode: 'navigate', method, url },
  waitUntil() {},
  respondWith(promise) { promise.then((response) => response.text(), (error) => 'ERROR ' + error.message).then(resolve); },
}));
(async () => {
  process.stdout.write(JSON.stringify({
    thread: await navigate('https://forum.test/compose/thread'),
    reply: await navigate('https://forum.test/compose/reply?thread_id=root-001&parent_id=reply-001'),
    escaped: await navigate('https://forum.test/compose/reply?thread_id=a%22%3E%3Cb&parent_id='),
    posted: await navigate('https://forum.test/compose/reply', 'POST'),
  }));
})();
NODE;
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/service_worker.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Compose worker fallback failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertStringContains('<title>New Post</title>', $result['thread']);
        assertStringContains('data-compose-root data-offline-copy="1"', $result['thread']);
        assertStringContains('data-compose-root data-offline-copy="1"', $result['reply']);
        assertStringContains('name="thread_id" value="root-001"', $result['reply']);
        assertStringContains('name="parent_id" value="reply-001"', $result['reply']);
        assertStringContains('name="thread_id" value="a&quot;&gt;&lt;b"', $result['escaped']);
        assertStringContains('ERROR', $result['posted']);
    }
}
