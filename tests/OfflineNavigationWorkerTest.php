<?php

declare(strict_types=1);

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
  '/tags', '/tags/', '/tags/general', '/tags/bug-fix',
  '/tools/outbox/',
  '/compose/thread', '/profiles/alice', '/search/', '/tags/Bad', '/tags/bug/extra'
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

        foreach (['/', '/threads', '/threads/?view=liked&sort=top', '/threads/root-001', '/tags', '/tags/', '/tags/general', '/tags/bug-fix'] as $path) {
            assertSame(true, $policy[$path]);
        }
        foreach (['/compose/thread', '/profiles/alice', '/search/', '/tags/Bad', '/tags/bug/extra'] as $path) {
            assertSame(false, $policy[$path]);
        }
    }

    public function testRefreshMessageReportsSuccessAndFailureToRequester(): void
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
const source = fs.readFileSync(process.argv[1], 'utf8');
async function refreshResult(fail) {
  const listeners = {};
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
      open() { return Promise.resolve({ put() { return Promise.resolve(); }, keys() { return Promise.resolve([]); } }); },
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
        $command = sprintf(
            'node -e %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/service_worker.js'),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Worker refresh-message contract failed: ' . implode("\n", $output));
        }
        $result = json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);

        assertSame('offline-reader-refreshed', $result[0]['type']);
        assertSame('ready', $result[0]['status']);
        assertSame('zenmemes-offline-reader-v13', $result[0]['cacheName']);
        assertSame('offline-reader-refreshed', $result[1]['type']);
        assertSame('error', $result[1]['status']);
        assertSame('Network unavailable', $result[1]['errorMessage']);
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
      open() { return Promise.resolve({ put(request) { stored.push(new URL(request.url || request).pathname); return Promise.resolve(); }, keys() { return Promise.resolve([]); } }); },
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
  request: { mode: 'navigate', url: 'https://forum.test/tools/outbox/' },
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
}
