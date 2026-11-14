import assert from 'node:assert/strict';
import { cp, mkdtemp, mkdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

// Reading margins (FORUM_READING_MARGINS_ENABLED) on a thread page, in every
// permitted theme: the radios are evenly spaced in one row, the column spans
// the last two radios clicked and every click moves it at once, the older edge
// is outlined, each checked radio sits over the column edge it sets, a choice survives reload without moving after paint,
// choosing a checked radio resets, the column never gets narrower than 20rem,
// other pages share the saved column, and phones show neither the ruler nor a
// saved column.
// Usage: node tests/browser/reading_margins.mjs [screenshot-dir]
const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.argv[2] || null;
const root = await mkdtemp(join(tmpdir(), 'reading-margins-browser-'));
const repo = join(root, 'repo');
const db = join(root, 'cache', 'read-model.sqlite3');
await cp(join(project, 'tests/fixtures/parity_minimal_v1'), repo, { recursive: true });
await mkdir(join(root, 'cache'));
if (shots) await mkdir(shots, { recursive: true });
const env = { ...process.env, FORUM_REPOSITORY_ROOT: repo, FORUM_DATABASE_PATH: db, FORUM_READING_MARGINS_ENABLED: 'true',
  FORUM_APP_VERSION_NOTIFICATION: 'false', FORUM_SECRETS_PATH: join(root, 'unused.php') };
const rebuild = spawnSync(join(project, 'v3'), ['rebuild', repo, db], { cwd: project, env, encoding: 'utf8' });
assert.equal(rebuild.status, 0, rebuild.stderr + rebuild.stdout);

const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn(join(project, 'v3'), ['start', `127.0.0.1:${port}`, '--quiet'], { cwd: project, env, detached: true });
let browser;
try {
  for (let i = 0; i < 200; i++) { try { await (await fetch(base + '/')).text(); break; } catch { await new Promise(r => setTimeout(r, 50)); } }
  browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
  const context = await browser.newContext({ serviceWorkers: 'block', viewport: { width: 1280, height: 900 } });
  const page = await context.newPage();
  const errors = []; page.on('pageerror', e => errors.push(e.message));
  const url = base + '/threads/root-001';
  await page.goto(url);
  const runtime = await page.evaluate(() => window.forumBrowserRuntime);
  const themes = await page.evaluate(() => Object.keys(window.forumThemeStylesheetPaths).filter(name => name !== 'auto'));
  assert.ok(themes.length > 1, 'permitted themes listed');

  const stop = at => page.locator(`[data-reading-margin-stop][value="${at}"]`);
  const checkedStops = () => page.locator('[data-reading-margin-stop]:checked').evaluateAll(all => all.map(each => each.value).join(' '));
  const column = () => page.locator('.main').evaluate(main => { const r = main.getBoundingClientRect(); return { left: r.left, right: r.right }; });
  const nextStop = () => page.locator('[data-reading-margin-next]').evaluateAll(all => all.map(each => each.value).join(' '));
  const stopCentre = async at => { const r = await stop(at).boundingBox(); return r.x + r.width / 2; };
  const fits = async (left, right) => { const c = await column(); return Math.abs(c.left - await stopCentre(left)) <= 2 && Math.abs(c.right - await stopCentre(right)) <= 2; };
  const pageScrollsSideways = () => page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  // The first paint already has the saved column: record .main's edges from the
  // earliest point the body is parsed and compare them with the settled page.
  const firstPaintColumn = () => page.evaluate(() => window.__firstColumn);
  await context.addInitScript(() => {
    new MutationObserver((records, observer) => {
      const main = document.querySelector('.main');
      if (!main || !document.querySelector('[data-role="reading-margins"]')) return;
      const r = main.getBoundingClientRect(); window.__firstColumn = { left: r.left, right: r.right }; observer.disconnect();
    }).observe(document, { childList: true, subtree: true });
  });

  for (const theme of themes) {
    await page.evaluate(([key, theme]) => { localStorage.clear(); localStorage.setItem(key, theme); }, [runtime.themeStorageKey, theme]);
    await page.goto(url);
    assert.equal(await checkedStops(), '', `${theme}: nothing chosen yet`);
    assert.equal(await pageScrollsSideways(), false, `${theme}: ruler adds no sideways scroll`);
    // One row: same top, equal gaps, no left or right groups.
    const centres = await page.locator('[data-reading-margin-stop]').evaluateAll(all => all.map(each => { const r = each.getBoundingClientRect(); return [r.left + r.width / 2, r.top]; }));
    assert.equal(centres.length, 6, `${theme}: six stops`);
    assert.equal(await page.locator('[data-reading-margin-edge]').count(), 0, `${theme}: no left/right groups`);
    const gap = centres[1][0] - centres[0][0];
    centres.forEach(([x, y], i) => {
      assert.ok(Math.abs(y - centres[0][1]) <= 1, `${theme}: stops share one row`);
      assert.ok(Math.abs(x - centres[0][0] - i * gap) <= 1.5, `${theme}: stops evenly spaced`);
    });
    if (shots) await page.screenshot({ path: join(shots, `${theme}-default.png`) });

    // From the normal column, the first click moves the column at once: the
    // clicked radio is one edge, and the stop nearest the normal column's other
    // side is the other edge, outlined as the one the next click moves.
    const before = await column();
    await stop(95).click();
    const [other] = (await checkedStops()).split(' ');
    assert.ok(['5', '23', '41', '59', '77'].includes(other), `${theme}: other edge filled in`);
    assert.equal(await checkedStops(), `${other} 95`, `${theme}: first click sets a column`);
    assert.notDeepEqual(await column(), before, `${theme}: first click moves the column`);
    assert.equal(await nextStop(), other, `${theme}: filled-in edge moves next`);
    if (shots) await page.screenshot({ path: join(shots, `${theme}-first-click.png`) });

    // Each click replaces the edge clicked longer ago, whichever side it is on.
    await stop(41).click();
    assert.equal(await checkedStops(), '41 95');
    assert.ok(await fits(41, 95), `${theme}: 41 replaced the older edge`);
    assert.equal(await nextStop(), '95', `${theme}: 95 is now the older edge`);
    await stop(77).click();
    assert.equal(await checkedStops(), '41 77', `${theme}: 77 replaced 95`);
    assert.ok(await fits(41, 77), `${theme}: column 41-77`);
    assert.equal(await nextStop(), '41');
    assert.equal(await page.evaluate(k => localStorage.getItem(k), runtime.readingMarginsStorageKey), '41 77');
    assert.equal(await pageScrollsSideways(), false, `${theme}: no sideways scroll`);
    if (shots) await page.screenshot({ path: join(shots, `${theme}-41-77.png`) });

    // A reload keeps the pair and which edge moves next, final on first paint.
    await page.reload();
    assert.equal(await checkedStops(), '41 77', `${theme}: choice restored`);
    assert.equal(await nextStop(), '41', `${theme}: outline restored`);
    const settled = await column();
    const first = await firstPaintColumn();
    assert.ok(first && Math.abs(first.left - settled.left) <= 1 && Math.abs(first.right - settled.right) <= 1, `${theme}: column final on first paint`);

    // 59 replaces 41: 59-77 is narrower than 20rem here, so the column is
    // 20rem, centred between the two radios.
    await stop(59).click();
    assert.equal(await checkedStops(), '59 77');
    assert.equal(await nextStop(), '77');
    const narrow = await column();
    const halfway = (await stopCentre(59) + await stopCentre(77)) / 2;
    assert.ok(Math.abs(narrow.right - narrow.left - 320) <= 1, `${theme}: column no narrower than 20rem`);
    assert.ok(Math.abs((narrow.left + narrow.right) / 2 - halfway) <= 2, `${theme}: narrow column centred between the radios`);
    await stop(5).click();
    assert.equal(await checkedStops(), '5 59', `${theme}: 5 replaced 77`);
    assert.ok(await fits(5, 59), `${theme}: off-centre column`);
    if (shots) await page.screenshot({ path: join(shots, `${theme}-5-59.png`) });

    // Clicking either checked radio returns to the site's normal column.
    await stop(5).click();
    assert.equal(await checkedStops(), '', `${theme}: newer edge resets`);
    assert.equal(await page.evaluate(k => localStorage.getItem(k), runtime.readingMarginsStorageKey), null);
    assert.equal(await page.evaluate(() => document.documentElement.hasAttribute('data-reading-margins')), false);
    assert.equal(await page.locator('[data-reading-margin-next]').count(), 0, `${theme}: outline cleared`);
    await stop(23).click();
    const outlined = await nextStop();
    await stop(outlined).click();
    assert.equal(await checkedStops(), '', `${theme}: outlined edge resets`);

    // A pair saved in either order is restored; anything else is ignored.
    await page.evaluate(k => localStorage.setItem(k, '95 23'), runtime.readingMarginsStorageKey);
    await page.reload();
    assert.equal(await checkedStops(), '23 95', `${theme}: right-to-left pair restored`);
    assert.equal(await nextStop(), '95', `${theme}: older edge outlined after reload`);
    assert.ok(await fits(23, 95), `${theme}: restored column`);
    for (const bad of ['24 96', '20 95', '41 41']) {
      await page.evaluate(([k, v]) => localStorage.setItem(k, v), [runtime.readingMarginsStorageKey, bad]);
      await page.reload();
      assert.equal(await checkedStops(), '', `${theme}: saved value ${bad} ignored`);
    }
  }

  // Other pages: the same ruler, and a saved column applies there on first paint.
  const listings = ['/', '/threads/?view=all', '/tags/bug/', '/recent/', '/search?q=hello', '/users/', '/unread',
    '/messages/', '/about/', '/tools/', '/tools/bookmarklets', '/docs/', '/docs/reference/agent_api.md'];
  for (const theme of themes) {
    await page.evaluate(([themeKey, theme, marginsKey]) => {
      localStorage.clear(); localStorage.setItem(themeKey, theme); localStorage.setItem(marginsKey, '23 95');
    }, [runtime.themeStorageKey, theme, runtime.readingMarginsStorageKey]);
    for (const listing of listings) {
      await page.goto(base + listing);
      assert.equal(await checkedStops(), '23 95', `${theme} ${listing}: saved choice shown`);
      assert.ok(await fits(23, 95), `${theme} ${listing}: column between the checked radios`);
      const settled = await column();
      const first = await firstPaintColumn();
      assert.ok(first && Math.abs(first.left - settled.left) <= 1 && Math.abs(first.right - settled.right) <= 1, `${theme} ${listing}: column final on first paint`);
      assert.equal(await pageScrollsSideways(), false, `${theme} ${listing}: no sideways scroll`);
      if (shots && listing === '/') await page.screenshot({ path: join(shots, `${theme}-board-23-95.png`) });
    }
    if (shots) await page.evaluate(k => localStorage.removeItem(k), runtime.readingMarginsStorageKey);
    if (shots) { await page.goto(base + '/'); await page.screenshot({ path: join(shots, `${theme}-board-default.png`) }); }
  }

  // Phones: no ruler, and a saved column does not narrow the page.
  await page.setViewportSize({ width: 390, height: 844 });
  await page.evaluate(k => { localStorage.clear(); localStorage.setItem(k, '41 59'); }, runtime.readingMarginsStorageKey);
  await page.goto(url);
  assert.equal(await page.locator('[data-role="reading-margins"]').isVisible(), false, 'phone hides the ruler');
  const phone = await column();
  assert.ok(phone.right - phone.left > 300, 'phone keeps the full-width column');
  if (shots) await page.screenshot({ path: join(shots, 'phone.png') });

  assert.deepEqual(errors, []);
  console.log(`reading margins: ${themes.length} themes at 1280px on a thread and ${listings.length} other pages, plus phone`);
} finally {
  if (browser) await browser.close();
  try { process.kill(-server.pid); } catch {}
  await rm(root, { recursive: true, force: true });
}
