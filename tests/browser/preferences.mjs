// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/preferences.mjs [screenshot-dir]
// Tools → Preferences (preferences_page): every row shows this browser's saved
// value from the inline script, before the page script loads; changes save to
// the keys each feature reads; each reset clears only its own keys; Reset all
// clears the listed preferences and keeps keys, drafts and the usage log.
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile, mkdir } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { resolve, join } from 'node:path';

const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.argv[2] || null;
const env = { ...process.env, FORUM_THREAD_DENSITY_TOGGLE_ENABLED: '1', FORUM_READING_MARGINS_ENABLED: '1', FORUM_DRAGGABLE_WINDOWS_ENABLED: '1', FORUM_OPEN_BESIDE_ENABLED: '1' };
const pages = {};
const renderApp = path => pages[path] ??= execFileSync('php', [`${project}/tests/Support/usage_app_page_fixture.php`, path], { encoding: 'utf8', env, maxBuffer: 1 << 26 });
const php = code => JSON.parse(execFileSync('php', ['-r', `require '${project}/autoload.php'; echo json_encode(${code});`], { encoding: 'utf8' }));
const runtime = php('ForumRewrite\\BrowserRuntimeProfile::fromProfile(ForumRewrite\\SiteProfileRegistry::active())');
const tableKeys = Object.values(php('ForumRewrite\\View\\ZoomTable::KINDS'));
const base = 'http://prefs.test';
if (shots) await mkdir(shots, { recursive: true });

const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
let blockPageScript = false;

async function open(width) {
  const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width, height: 900 }, reducedMotion: 'reduce' });
  await context.route(base + '/**', async route => {
    const url = new URL(route.request().url());
    if (url.pathname.startsWith('/assets/')) {
      const file = url.pathname.replace(/\.[0-9a-f]{12}\./, '.');
      if (blockPageScript && file === '/assets/preferences.js') return route.abort();
      try { return route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : 'application/javascript', body: await readFile(`${project}/public${file}`, 'utf8') }); }
      catch { return route.fulfill({ status: 404, body: '' }); }
    }
    if (url.pathname.startsWith('/api/')) return route.fulfill({ status: 404, contentType: 'application/json', body: '{}' });
    return route.fulfill({ contentType: 'text/html', body: renderApp(url.pathname === '/tools/preferences/' ? url.pathname : '/tools/') });
  });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', error => errors.push(error.message));
  page.setDefaultTimeout(15000);
  return { context, page, errors };
}

const value = (page, name) => page.locator(`[data-preference-value="${name}"]`).textContent();
const resetVisible = (page, name) => page.locator(`[data-preference-reset="${name}"]`).isVisible();
const storage = page => page.evaluate(() => Object.fromEntries(Object.keys(localStorage).map(key => [key, localStorage.getItem(key)])));

const saved = {
  [runtime.themeStorageKey]: 'word97',
  [runtime.themeStorageKey + '-gloss-palette']: 'mint',
  [runtime.themeStorageKey + '-page-exit:/guestbook/']: '1',
  [runtime.themeStorageKey + '-page-exit:/threads/abc']: '1',
  [runtime.threadDensityStorageKey]: 'compact',
  [runtime.readingMarginsStorageKey]: '1 4',
  [runtime.windowControlsStorageKey]: 'off',
  [runtime.collapsedWindowsStorageKey]: 'root-001',
  [runtime.openBesideStorageKey]: 'on',
  [runtime.openBesidePaneStorageKey]: '/threads/root-001',
  [runtime.openBesidePaneStorageKey + '-scroll']: '{"address":"/threads/root-001","y":40}',
  [tableKeys[0]]: '1.5',
  forum_nav_slots: JSON.stringify({ v: 2, slots: ['board', 'calendar', 'recent'], visits: {} }),
  forum_visitor_intro_hidden: '1',
  forum_usage_log: JSON.stringify({ recording: true, entries: [] }),
  'gallery-zoom': '1.563',
  forum_pki_fingerprint: 'keep-me',
  forum_recently_cleared_compose_draft: 'keep-me-too',
};

try {
  // A fresh browser: defaults, no reset buttons.
  {
    const { context, page, errors } = await open(1280);
    await page.goto(base + '/tools/preferences/');
    assert.equal(await page.locator('h1').textContent(), 'Preferences');
    assert.equal(await page.locator('#preference-theme').inputValue(), await page.evaluate(() => document.documentElement.getAttribute('data-default-theme') || 'auto'));
    assert.equal(await page.locator('#preference-thread-view').inputValue(), 'comfortable');
    assert.equal(await page.locator('#preference-window-controls').inputValue(), 'on');
    assert.equal(await page.locator('#preference-open-beside').inputValue(), 'off', 'Open links beside is off until chosen');
    assert.equal(await value(page, 'reading-margins'), 'Site default');
    assert.equal(await value(page, 'table-size'), 'Site default');
    assert.equal(await value(page, 'gallery-size'), 'Site default');
    assert.equal(await value(page, 'page-themes'), 'Shown');
    assert.equal(await value(page, 'welcome-note'), 'Shown');
    for (const name of ['reading-margins', 'table-size', 'gallery-size', 'page-themes', 'welcome-note']) {
      assert.equal(await resetVisible(page, name), false, `${name}: no reset on a fresh browser`);
    }
    assert.deepEqual(errors, []);
    await context.close();
  }

  // Saved values show without the page script (the inline script fills them in during parsing).
  {
    blockPageScript = true;
    const { context, page, errors } = await open(1280);
    await context.addInitScript(entries => {
      if (sessionStorage.getItem('seeded')) return;
      sessionStorage.setItem('seeded', '1');
      for (const [key, item] of Object.entries(entries)) localStorage.setItem(key, item);
    }, saved);
    await page.goto(base + '/tools/preferences/');
    assert.equal(await page.locator('#preference-theme').inputValue(), 'word97');
    assert.equal(await page.locator('#preference-thread-view').inputValue(), 'compact');
    assert.equal(await page.locator('#preference-window-controls').inputValue(), 'off');
    assert.equal(await page.locator('#preference-open-beside').inputValue(), 'on');
    assert.equal(await value(page, 'reading-margins'), 'Custom');
    assert.equal(await value(page, 'table-size'), 'Custom');
    assert.equal(await value(page, 'gallery-size'), 'Custom');
    assert.equal(await value(page, 'page-themes'), 'Left on 2 pages');
    assert.equal(await value(page, 'welcome-note'), 'Hidden');
    assert.equal(await value(page, 'usage-log'), 'Recording');
    assert.equal(await page.locator('[data-preference-row="phone-tabs"]').isHidden(), false, 'phone tabs row shows in the app header');
    assert.match(await value(page, 'phone-tabs'), /^Board, Recent/);
    for (const name of ['reading-margins', 'table-size', 'gallery-size', 'phone-tabs', 'page-themes', 'welcome-note']) {
      assert.equal(await resetVisible(page, name), true, `${name}: reset shown`);
    }
    assert.deepEqual(errors, []);
    await context.close();
    blockPageScript = false;
  }

  // Changes and resets, one at a time.
  {
    const { context, page, errors } = await open(1280);
    await page.goto(base + '/tools/preferences/');
    await page.evaluate(entries => { for (const [key, item] of Object.entries(entries)) localStorage.setItem(key, item); }, saved);
    await page.reload();

    await page.locator('#preference-theme').selectOption('midnight');
    await page.waitForFunction(() => document.documentElement.getAttribute('data-resolved-theme') === 'midnight');
    assert.equal((await storage(page))[runtime.themeStorageKey], 'midnight');

    await page.locator('#preference-thread-view').selectOption('comfortable');
    assert.equal((await storage(page))[runtime.threadDensityStorageKey], 'comfortable');

    await page.locator('#preference-window-controls').selectOption('on');
    assert.equal((await storage(page))[runtime.windowControlsStorageKey], undefined, 'On is the default: key removed');
    assert.equal(await page.evaluate(() => document.documentElement.getAttribute('data-window-controls')), 'on');
    await page.locator('#preference-window-controls').selectOption('off');
    assert.equal((await storage(page))[runtime.windowControlsStorageKey], 'off');
    assert.equal(await page.evaluate(() => document.documentElement.hasAttribute('data-window-controls')), false);
    await page.reload();
    assert.equal(await page.locator('#preference-window-controls').inputValue(), 'off', 'Off survives reload');

    // Open links beside: the row and the ruler's Beside toggle share one saved choice.
    const beside = page.locator('[data-open-beside-toggle]');
    await page.locator('#preference-open-beside').selectOption('on');
    assert.equal((await storage(page))[runtime.openBesideStorageKey], 'on');
    assert.equal(await page.evaluate(() => document.documentElement.getAttribute('data-open-beside')), 'on');
    assert.equal(await beside.getAttribute('aria-pressed'), 'true', 'the ruler toggle follows the row');
    await beside.click();
    assert.equal(await page.locator('#preference-open-beside').inputValue(), 'off', 'the row follows the ruler toggle');
    assert.equal((await storage(page))[runtime.openBesideStorageKey], undefined, 'Off is the default: key removed');
    await page.locator('#preference-open-beside').selectOption('on');
    await page.reload();
    assert.equal(await page.locator('#preference-open-beside').inputValue(), 'on', 'On survives reload');
    assert.equal(await beside.getAttribute('aria-pressed'), 'true');
    await page.locator('#preference-open-beside').selectOption('off');
    assert.equal(await page.evaluate(() => document.documentElement.hasAttribute('data-open-beside')), false);

    await page.locator('[data-preference-reset="reading-margins"]').click();
    assert.equal((await storage(page))[runtime.readingMarginsStorageKey], undefined);
    assert.equal(await page.evaluate(() => document.documentElement.hasAttribute('data-reading-margins')), false);
    assert.equal(await value(page, 'reading-margins'), 'Site default');
    assert.equal(await resetVisible(page, 'reading-margins'), false);

    await page.locator('[data-preference-reset="table-size"]').click();
    for (const key of tableKeys) assert.equal((await storage(page))[key], undefined, key);

    await page.locator('[data-preference-reset="gallery-size"]').click();
    assert.equal((await storage(page))['gallery-zoom'], undefined);
    assert.equal(await page.evaluate(() => document.documentElement.style.getPropertyValue('--gallery-zoom')), '');
    assert.equal(await value(page, 'gallery-size'), 'Site default');

    await page.locator('[data-preference-reset="page-themes"]').click();
    assert.deepEqual(Object.keys(await storage(page)).filter(key => key.includes('-page-exit:')), []);
    assert.equal(await value(page, 'page-themes'), 'Shown');

    await page.locator('[data-preference-reset="phone-tabs"]').click();
    assert.equal((await storage(page)).forum_nav_slots, undefined);

    await page.locator('[data-preference-reset="welcome-note"]').click();
    assert.equal((await storage(page)).forum_visitor_intro_hidden, undefined);

    // The header's theme menu and the Theme row agree.
    await page.evaluate(() => window.forumSelectTheme('word97'));
    await page.waitForFunction(() => document.querySelector('#preference-theme').value === 'word97');

    assert.deepEqual(errors, []);
    await context.close();
  }

  // Reset all: listed preferences go, identity, drafts and the usage log stay.
  {
    const { context, page, errors } = await open(390);
    await page.goto(base + '/tools/preferences/');
    await page.evaluate(entries => { for (const [key, item] of Object.entries(entries)) localStorage.setItem(key, item); }, saved);
    await page.reload();
    await Promise.all([page.waitForEvent('load'), page.locator('[data-preference-reset="all"]').click()]);
    const after = await storage(page);
    assert.deepEqual(Object.keys(after).sort(), ['forum_pki_fingerprint', 'forum_recently_cleared_compose_draft', 'forum_usage_log'].sort(), JSON.stringify(after));
    assert.equal(await page.locator('#preference-theme').inputValue(), await page.evaluate(() => document.documentElement.getAttribute('data-default-theme') || 'auto'));
    assert.deepEqual(errors, []);
    await context.close();
  }

  // Looks: no horizontal overflow, in the default and icon themes at phone and desktop widths.
  for (const theme of ['default', 'gloss', 'glossdark', 'word97', 'midnight', 'bubblegum', 'chicago']) {
    for (const width of [390, 1280]) {
      const { context, page, errors } = await open(width);
      if (theme !== 'default') await context.addInitScript(([key, name]) => localStorage.setItem(key, name), [runtime.themeStorageKey, theme]);
      await page.goto(base + '/tools/preferences/');
      await page.waitForLoadState('networkidle');
      const overflow = await page.evaluate(() => document.documentElement.scrollWidth - document.documentElement.clientWidth);
      assert.ok(overflow <= 1, `${theme} ${width}px: overflows by ${overflow}px`);
      if (theme !== 'default') {
        const icon = await page.locator('.tools-nav .nav-link[href="/tools/preferences/"]').evaluate(link => getComputedStyle(link, '::before').backgroundImage);
        assert.match(icon, /svg/, `${theme}: Preferences icon in the Tools bar`);
      }
      if (shots) await page.screenshot({ path: join(shots, `preferences-${theme}-${width}.png`), fullPage: true });
      assert.deepEqual(errors, [], `${theme} ${width}px`);
      await context.close();
    }
  }
  console.log('preferences: ok');
} finally {
  await browser.close();
}
