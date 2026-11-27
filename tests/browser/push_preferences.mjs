// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/push_preferences.mjs [screenshot-dir]
// Preferences → Notifications (push_subscribe): the row shows only with the flag on, a saved
// key and push support; Turn on asks permission and subscribes; Send a test reports the
// result; Turn off unsubscribes; a refusal shows Blocked. The service worker shows every
// push, with a plain notice when the payload can't be read, and a tap opens its page.
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile, mkdir } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import vm from 'node:vm';

const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.argv[2] || null;
const render = flag => execFileSync('php', [`${project}/tests/Support/usage_app_page_fixture.php`, '/tools/preferences/'], { encoding: 'utf8', env: { ...process.env, FORUM_PUSH_ENABLED: flag }, maxBuffer: 1 << 26 });
const pages = { on: render('1'), off: render('0') };
const base = 'https://prefs.test';
if (shots) await mkdir(shots, { recursive: true });

// Browser push APIs, stubbed: permission answers `answer`; subscriptions are kept in memory.
const stubs = answer => `
  window.__push = { calls: [], subscription: null };
  const key = new Uint8Array(65); key[0] = 4;
  class FakeSubscription {
    constructor(serverKey) { this.endpoint = 'https://web.push.apple.com/device-1'; this.options = { applicationServerKey: serverKey.buffer }; }
    toJSON() { return { endpoint: this.endpoint, keys: { p256dh: 'BP4', auth: 'AAA' } }; }
    unsubscribe() { window.__push.calls.push('unsubscribe'); window.__push.subscription = null; sessionStorage.removeItem('stub-subscription'); return Promise.resolve(true); }
  }
  // Kept in sessionStorage so a reload finds the same permission and subscription.
  const saved = sessionStorage.getItem('stub-subscription');
  if (saved) window.__push.subscription = new FakeSubscription(Uint8Array.from(JSON.parse(saved)));
  const pushManager = {
    getSubscription: () => Promise.resolve(window.__push.subscription),
    subscribe: options => { if (window.__pushHang) return new Promise(() => {}); window.__push.calls.push('subscribe:' + options.userVisibleOnly); window.__push.subscription = new FakeSubscription(options.applicationServerKey); sessionStorage.setItem('stub-subscription', JSON.stringify(Array.from(options.applicationServerKey))); return Promise.resolve(window.__push.subscription); },
  };
  window.PushManager = function () {};
  Object.defineProperty(navigator, 'serviceWorker', { value: { ready: Promise.resolve({ pushManager }), register: () => Promise.resolve({}), getRegistrations: () => Promise.resolve([]), addEventListener() {} } });
  let permission = sessionStorage.getItem('stub-permission') || 'default';
  window.Notification = { get permission() { return permission; }, requestPermission: () => { window.__push.calls.push('ask'); permission = ${JSON.stringify(answer)}; sessionStorage.setItem('stub-permission', permission); return Promise.resolve(permission); } };
`;

let blockPushScript = false;
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

async function open({ flag = 'on', key = true, answer = 'granted', testOutcome = 'sent', width = 380, hangSubscribe = false, saveStatus = 200 } = {}) {
  const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width, height: 900 }, reducedMotion: 'reduce' });
  const api = [];
  await context.addInitScript(stubs(answer));
  if (hangSubscribe) await context.addInitScript(() => { window.__pushHang = true; window.__forumPushStepMs = 300; });
  await context.addInitScript(() => { window.__copied = []; Object.defineProperty(navigator, 'clipboard', { value: { writeText: text => { window.__copied.push(text); return Promise.resolve(); } } }); });
  if (key) await context.addInitScript(() => { localStorage.setItem('forum_pki_fingerprint', 'a'.repeat(40)); localStorage.setItem('forum_pki_private_key', 'x'); localStorage.setItem('forum_pki_public_key', 'x'); });
  await context.route(base + '/**', async route => {
    const url = new URL(route.request().url());
    if (url.pathname.startsWith('/assets/')) {
      const file = url.pathname.replace(/\.[0-9a-f]{12}\./, '.');
      if (blockPushScript && file === '/assets/push_preferences.js') return route.abort();
      try { return route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : 'application/javascript', body: await readFile(`${project}/public${file}`, 'utf8') }); }
      catch { return route.fulfill({ status: 404, body: '' }); }
    }
    if (url.pathname.startsWith('/api/push/')) {
      const request = route.request();
      api.push([url.pathname, request.headers()['x-requested-with'] || '', request.postData() || '']);
      if (url.pathname === '/api/push/subscribe' && saveStatus !== 200) {
        return route.fulfill({ status: saveStatus, contentType: 'application/json', body: JSON.stringify({ status: 'error', error: 'Notifications could not be turned on; try again.' }) });
      }
      const body = url.pathname === '/api/push/key' ? { status: 'ok', public_key: Buffer.from(Uint8Array.from([4, ...new Array(64).fill(0)])).toString('base64url') }
        : url.pathname === '/api/push/test' ? { status: 'ok', outcome: testOutcome, reason: testOutcome === 'refused' ? 'BadJwtToken' : '' }
        : { status: 'ok' };
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify(body) });
    }
    if (url.pathname.startsWith('/api/')) return route.fulfill({ status: 404, contentType: 'application/json', body: '{}' });
    return route.fulfill({ contentType: 'text/html', body: pages[flag] });
  });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.setDefaultTimeout(15000);
  await page.goto(base + '/tools/preferences/');
  return { context, page, errors, api };
}

const row = page => page.locator('[data-preference-row="notifications"]');
const value = page => page.locator('[data-preference-value="notifications"]').textContent();
const calls = page => page.evaluate(() => window.__push.calls);

try {
  {
    const { context, page } = await open({ flag: 'off' });
    assert.equal(await row(page).count(), 0, 'no row with the flag off');
    await context.close();
  }
  {
    const { context, page } = await open({ key: false });
    assert.equal(await row(page).isHidden(), true, 'hidden without a saved key');
    await context.close();
  }
  {
    const { context, page, errors, api } = await open();
    assert.equal(await row(page).isVisible(), true);
    assert.equal(await value(page), 'Off');
    assert.deepEqual(await calls(page), [], 'nothing is asked on load');
    assert.equal(await page.locator('[data-push-test]').isHidden(), true);
    if (shots) await page.screenshot({ path: `${shots}/push-off.png`, fullPage: true });

    await page.locator('[data-push-toggle]').click();
    await page.waitForFunction(() => document.querySelector('[data-preference-row="notifications"]').getAttribute('data-push-state') === 'on');
    assert.equal(await value(page), 'On');
    assert.deepEqual(await calls(page), ['ask', 'subscribe:true']);
    const subscribe = api.find(([path]) => path === '/api/push/subscribe');
    assert.equal(subscribe[1], 'ForumPush');
    assert.equal(JSON.parse(subscribe[2]).endpoint, 'https://web.push.apple.com/device-1');
    assert.equal(await page.locator('[data-push-toggle]').textContent(), 'Turn off');
    assert.equal(await page.evaluate(() => localStorage.getItem('forum_push_state')), 'on');

    await page.locator('[data-push-test]').click();
    await page.waitForFunction(() => document.querySelector('[data-preference-value="notifications"]').textContent === 'Test sent');
    if (shots) await page.screenshot({ path: `${shots}/push-on.png`, fullPage: true });

    // Reloaded without the page script: the state is drawn before paint.
    blockPushScript = true;
    await page.reload();
    assert.equal(await page.locator('[data-push-toggle]').textContent(), 'Turn off');
    assert.equal(await value(page), 'On');
    blockPushScript = false;
    // Reloaded: the subscription the browser holds is saved again.
    const before = api.length;
    await page.reload();
    await page.waitForFunction(() => document.querySelector('[data-preference-value="notifications"]').textContent === 'On');
    assert.equal(api.slice(before).some(([path]) => path === '/api/push/subscribe'), true, 'reload saves the subscription again');

    await page.locator('[data-push-toggle]').click();
    await page.waitForFunction(() => document.querySelector('[data-preference-row="notifications"]').getAttribute('data-push-state') === 'off');
    assert.equal(api.at(-1)[0], '/api/push/unsubscribe');
    assert.equal(await page.locator('[data-push-test]').isHidden(), true);
    assert.deepEqual(errors, []);
    await context.close();
  }
  {
    const { context, page } = await open({ answer: 'denied' });
    await page.locator('[data-push-toggle]').click();
    await page.waitForFunction(() => document.querySelector('[data-preference-value="notifications"]').textContent === 'Blocked');
    assert.deepEqual(await calls(page), ['ask']);
    await context.close();
  }
  {
    const { context, page } = await open({ testOutcome: 'refused' });
    await page.locator('[data-push-toggle]').click();
    await page.waitForFunction(() => document.querySelector('[data-preference-row="notifications"]').getAttribute('data-push-state') === 'on');
    await page.locator('[data-push-test]').click();
    await page.waitForFunction(() => document.querySelector('[data-preference-value="notifications"]').textContent === 'Refused: BadJwtToken');
    await context.close();
  }
  {
    const { context, page } = await open({ testOutcome: 'gone' });
    await page.locator('[data-push-toggle]').click();
    await page.waitForFunction(() => document.querySelector('[data-preference-row="notifications"]').getAttribute('data-push-state') === 'on');
    await page.locator('[data-push-test]').click();
    await page.waitForFunction(() => document.querySelector('[data-preference-row="notifications"]').getAttribute('data-push-state') === 'off');
    assert.equal((await calls(page)).at(-1), 'unsubscribe', 'a gone device unsubscribes the browser too');
    await context.close();
  }

  // A step that never answers ends Off with Details naming it, copyable, and reported.
  {
    const { context, page, errors, api } = await open({ hangSubscribe: true });
    assert.equal(await page.locator('[data-push-details]').isHidden(), true, 'no details before a failure');
    await page.locator('[data-push-toggle]').click();
    await page.waitForFunction(() => document.querySelector('[data-preference-value="notifications"]').textContent === 'Didn\u2019t respond');
    assert.equal(await row(page).getAttribute('data-push-state'), 'off');
    assert.equal(await page.locator('[data-push-toggle]').textContent(), 'Turn on');
    const details = page.locator('[data-push-details]');
    assert.equal(await details.isVisible(), true);
    assert.equal(await details.evaluate(element => element.open), false, 'details start closed');
    const text = await page.locator('[data-push-details-text]').textContent();
    assert.match(text, /^Action: Turn on notifications\nStep: push subscribe\nError: Timeout: No answer after 0 s\nWaited: 0\.\d s\nPermission: granted\nInstalled app: no\n/);
    assert.doesNotMatch(text, /web\.push\.apple\.com|forum_pki/, 'no endpoint or key in the details');
    for (let tries = 0; tries < 50 && !api.some(([path]) => path === '/api/push/report'); tries++) await page.waitForTimeout(20);
    const report = api.find(([path]) => path === '/api/push/report');
    assert.ok(report, 'the failure is reported');
    assert.equal(report[1], 'ForumPush');
    assert.equal(JSON.parse(report[2]).report, text);
    await details.locator('summary').click();
    await page.locator('[data-push-details-copy]').click();
    await page.waitForFunction(() => document.querySelector('[data-push-details-copy]').textContent === 'Copied');
    assert.deepEqual(await page.evaluate(() => window.__copied), [text]);
    if (shots) await page.screenshot({ path: `${shots}/push-stalled.png`, fullPage: true });
    assert.deepEqual(errors, []);
    await context.close();
  }
  // A server refusal shows Try again, with its status and text in Details.
  {
    const { context, page } = await open({ saveStatus: 503 });
    await page.locator('[data-push-toggle]').click();
    await page.waitForFunction(() => document.querySelector('[data-preference-value="notifications"]').textContent === 'Try again');
    const text = await page.locator('[data-push-details-text]').textContent();
    assert.match(text, /Step: save subscription\nError: Error: Notifications could not be turned on; try again\.\nHTTP status: 503\n/);
    await context.close();
  }

  // The worker: every push shows a notification; a tap opens its page.
  {
    const listeners = {};
    const shown = [];
    const opened = [];
    const focused = [];
    let windows = [];
    const self = {
      location: new URL('https://zenmemes.com/service_worker.js'),
      navigator: { onLine: true },
      addEventListener: (type, listener) => { listeners[type] = listener; },
      registration: { showNotification: (title, options) => { shown.push([title, options]); return Promise.resolve(); } },
      clients: { matchAll: () => Promise.resolve(windows), openWindow: url => { opened.push(url); return Promise.resolve(); }, claim: () => Promise.resolve() },
      skipWaiting: () => Promise.resolve(),
    };
    vm.runInNewContext(await readFile(`${project}/public/service_worker.js`, 'utf8'), { self, URL, Response, caches: {}, fetch, console });
    const push = data => new Promise(done => listeners.push({ data, waitUntil: promise => promise.then(done) }));

    await push({ json: () => ({ title: 'ZenMemes', body: 'Test notification', url: '/tools/preferences/', tag: 'push-test' }) });
    await push({ json: () => { throw new SyntaxError('not JSON'); } });
    await push(null);
    await push({ json: () => ({ title: 'x', url: 'https://evil.example/' }) });
    assert.deepEqual(shown.map(([title, options]) => [title, options.body, options.data.url, options.tag ?? null]), [
      ['ZenMemes', 'Test notification', '/tools/preferences/', 'push-test'],
      ['ZenMemes', 'Something new', '/', null],
      ['ZenMemes', 'Something new', '/', null],
      ['x', 'Something new', '/', null],
    ]);

    const click = url => new Promise(done => listeners.notificationclick({ notification: { data: { url }, close() {} }, waitUntil: promise => promise.then(done) }));
    await click('/tools/preferences/');
    assert.deepEqual(opened, ['https://zenmemes.com/tools/preferences/']);
    // An open window is focused and sent to the notice's page (push_tap_routing).
    const navigated = [];
    windows = [{ url: 'https://zenmemes.com/', focus() { focused.push(1); return Promise.resolve(this); }, navigate: url => { navigated.push(url); return Promise.resolve({ url }); } }];
    await click('/tools/preferences/');
    assert.deepEqual([opened.length, focused.length, navigated], [1, 1, ['https://zenmemes.com/tools/preferences/']]);
  }
  console.log('push_preferences: ok');
} finally {
  await browser.close();
}
