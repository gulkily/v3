<?php

declare(strict_types=1);

require __DIR__ . '/../autoload.php';

/** Installed app opens instantly (owa_instant_launch): the worker side, under Node with fake caches. */
final class OwaInstantLaunchWorkerTest
{
    /** @return array<string, mixed> */
    private function run(string $scenario): array
    {
        $script = <<<'NODE'
const fs = require('fs');
const vm = require('vm');
// A short fallback keeps the test quick; the shipped value is checked separately.
const source = fs.readFileSync(process.argv[1], 'utf8').replace('INSTANT_FALLBACK_MS = 3000', 'INSTANT_FALLBACK_MS = 60');
const scenario = JSON.parse(process.argv[2]);
const ORIGIN = 'https://forum.test';
const stores = new Map();
function store(name) {
  if (!stores.has(name)) stores.set(name, new Map());
  const entries = stores.get(name);
  return {
    match: async (key) => { const found = entries.get(typeof key === 'string' ? key : key.url); return found ? found.clone() : undefined; },
    put: async (key, response) => { entries.delete(key); entries.set(key, response); },
    delete: async (key) => entries.delete(typeof key === 'string' ? key : key.url),
    keys: async () => [...entries.keys()].map((url) => ({ url })),
  };
}
const listeners = {};
global.self = {
  location: { href: ORIGIN + '/service_worker.js', origin: ORIGIN },
  navigator: { onLine: true },
  addEventListener(type, listener) { listeners[type] = listener; }
};
global.caches = { open: async (name) => store(name), has: async (name) => stores.has(name) };
// Same-origin network answers are "basic" in a browser; Node's Response says "default".
const page = (body) => Object.defineProperty(new Response(body, { headers: { 'Content-Type': 'text/html', 'Cache-Control': 'public, no-cache' } }), 'type', { value: 'basic' });
let fetches = 0;
global.fetch = (request) => {
  fetches++;
  if (scenario.network === 'fail') return Promise.reject(new Error('offline'));
  return new Promise((done) => setTimeout(() => done(page('network ' + new URL(typeof request === 'string' ? request : request.url).pathname)), scenario.networkMs));
};
vm.runInThisContext(source);
(async () => {
  const main = store('zenmemes-offline-reader-v16');
  await main.put(ORIGIN + '/offline/reader/', page('reader shell'));
  const copies = 'zenmemes-offline-reader-nav-copy';
  if (scenario.marker) await store(copies).put(ORIGIN + '/__instant_launch__', new Response('1'));
  if (scenario.navbar) await store(copies).put(ORIGIN + '/__nav_copy_paths__', new Response(scenario.navbar.join('\n')));
  for (const path of scenario.kept || []) await store(copies).put(ORIGIN + path, page('kept ' + path));
  for (let i = 0; i < (scenario.filler || 0); i++) await store(copies).put(ORIGIN + '/threads/filler-' + i, page('old'));
  const waits = [];
  let answer;
  const started = Date.now();
  listeners.fetch({
    request: { mode: 'navigate', method: 'GET', url: ORIGIN + scenario.path },
    preloadResponse: undefined,
    waitUntil(promise) { waits.push(Promise.resolve(promise).catch(() => {})); },
    respondWith(promise) { answer = promise; }
  });
  const response = await answer;
  const elapsed = Date.now() - started;
  const body = await response.text();
  await new Promise((done) => setTimeout(done, (scenario.networkMs || 0) + 100));
  // The navbar-copy bookkeeping holds its waitUntil open for 30 s; the copies settle well before.
  await Promise.race([Promise.all(waits.splice(0)), new Promise((done) => setTimeout(done, 300))]);
  const kept = stores.has(copies) ? stores.get(copies) : new Map();
  const keptCopy = kept.get(ORIGIN + scenario.path);
  process.stdout.write(JSON.stringify({
    body, elapsed, fetches,
    keptAfter: keptCopy ? await keptCopy.text() : null,
    keptPages: [...kept.keys()].filter((url) => !url.endsWith('/__instant_launch__') && !url.endsWith('/__nav_copy_paths__')).length,
    marker: kept.has(ORIGIN + '/__instant_launch__'),
  }));
  process.exit(0);
})().catch((error) => { console.error(error); process.exit(1); });
NODE;
        $command = sprintf(
            'node -e %s %s %s',
            escapeshellarg($script),
            escapeshellarg(__DIR__ . '/../public/service_worker.js'),
            escapeshellarg($scenario),
        );
        exec($command . ' 2>&1', $output, $exitCode);
        if ($exitCode !== 0) {
            throw new RuntimeException('Instant launch worker scenario failed: ' . implode("\n", $output));
        }

        return json_decode(implode("\n", $output), true, 512, JSON_THROW_ON_ERROR);
    }

    public function testKeptCopyAnswersAtOnceWhileTheNetworkIsSlowAndIsRefreshed(): void
    {
        $result = $this->run('{"marker":true,"kept":["/threads/root-001"],"path":"/threads/root-001","networkMs":500}');

        assertSame('kept /threads/root-001', $result['body']);
        assertTrue($result['elapsed'] < 200, 'The kept copy waited for the network: ' . $result['elapsed'] . ' ms');
        assertSame('network /threads/root-001', $result['keptAfter']);
    }

    public function testHomePageIsAReaderRouteForTheInstalledApp(): void
    {
        $result = $this->run('{"marker":true,"kept":["/"],"path":"/","networkMs":500}');

        assertSame('kept /', $result['body']);
    }

    public function testPageWithNoCopyGetsTheReaderAfterTheLimitAndTheLateAnswerIsKept(): void
    {
        $result = $this->run('{"marker":true,"path":"/threads/root-002","networkMs":400}');

        assertSame('reader shell', $result['body']);
        assertTrue($result['elapsed'] >= 50 && $result['elapsed'] < 300, 'Reader shown after ' . $result['elapsed'] . ' ms');
        assertSame('network /threads/root-002', $result['keptAfter']);
    }

    public function testFastNetworkAnswersAndBecomesTheKeptCopy(): void
    {
        $result = $this->run('{"marker":true,"path":"/tags/general","networkMs":5}');

        assertSame('network /tags/general', $result['body']);
        assertSame('network /tags/general', $result['keptAfter']);
    }

    public function testFailedNetworkStillFallsBackToTheReader(): void
    {
        $result = $this->run('{"marker":true,"path":"/threads/root-003","network":"fail"}');

        assertSame('reader shell', $result['body']);
        assertSame(null, $result['keptAfter']);
    }

    public function testWithoutTheMarkerNavigationWaitsForTheNetworkAsBefore(): void
    {
        $result = $this->run('{"marker":false,"kept":["/threads/root-001"],"path":"/threads/root-001","networkMs":200}');

        assertSame('network /threads/root-001', $result['body']);
        assertTrue($result['elapsed'] >= 150, 'Answered before the network: ' . $result['elapsed'] . ' ms');
    }

    public function testNavbarCopiesStillWorkWithoutTheMarker(): void
    {
        $result = $this->run('{"marker":false,"navbar":["/threads/"],"kept":["/threads/"],"path":"/threads/","networkMs":300}');

        assertSame('kept /threads/', $result['body']);
    }

    public function testRoutesTheReaderCannotServeStillWaitForTheNetwork(): void
    {
        $result = $this->run('{"marker":true,"path":"/profiles/alice","networkMs":200}');

        assertSame('network /profiles/alice', $result['body']);
        assertSame(null, $result['keptAfter']);
    }

    public function testKeptCopiesAreCappedOldestFirst(): void
    {
        $result = $this->run('{"marker":true,"filler":160,"path":"/threads/root-004","networkMs":5}');

        assertSame(150, $result['keptPages']);
        assertSame('network /threads/root-004', $result['keptAfter']);
        assertTrue($result['marker'], 'The marker was pruned with the pages.');
    }

    public function testShippedFallbackIsThreeSeconds(): void
    {
        $source = (string) file_get_contents(__DIR__ . '/../public/service_worker.js');

        assertTrue(str_contains($source, 'const INSTANT_FALLBACK_MS = 3000;'), 'The fallback limit changed.');
    }
}
