import assert from 'node:assert/strict';
import { cp, mkdtemp, mkdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

// Reading margins (FORUM_READING_MARGINS_ENABLED) on a thread page, in every
// permitted theme: the radios are evenly spaced, each checked radio sits over
// the column edge it sets, choosing another radio moves the nearer edge, a
// choice survives reload without moving after paint, choosing a checked radio
// resets, and phones show neither the ruler nor a saved column.
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
    assert.equal(centres.length, 7, `${theme}: seven stops`);
    const gap = centres[1][0] - centres[0][0];
    centres.forEach(([x, y], i) => {
      assert.ok(Math.abs(y - centres[0][1]) <= 1, `${theme}: stops share one row`);
      assert.ok(Math.abs(x - centres[0][0] - i * gap) <= 1.5, `${theme}: stops evenly spaced`);
    });
    if (shots) await page.screenshot({ path: join(shots, `${theme}-default.png`) });

    // Nothing chosen: 20 is left of the column's centre, so it becomes the left
    // edge and the right edge goes to the stop nearest the column's right side.
    await stop(20).click();
    const [firstLeft, firstRight] = (await checkedStops()).split(' ');
    assert.equal(firstLeft, '20', `${theme}: first choice is the left edge`);
    assert.ok(Number(firstRight) > 50, `${theme}: right edge filled in`);
    assert.ok(await fits(20, firstRight), `${theme}: column between the checked radios`);

    // Another radio moves the nearer edge: 95 is nearer the right edge.
    await stop(95).click();
    assert.equal(await checkedStops(), '20 95');
    assert.ok(await fits(20, 95), `${theme}: right edge moved`);
    assert.equal(await page.evaluate(k => localStorage.getItem(k), runtime.readingMarginsStorageKey), '20 95');
    assert.equal(await pageScrollsSideways(), false, `${theme}: wide column adds no sideways scroll`);

    await page.reload();
    assert.equal(await checkedStops(), '20 95', `${theme}: choice restored`);
    const settled = await column();
    const first = await firstPaintColumn();
    assert.ok(first && Math.abs(first.left - settled.left) <= 1 && Math.abs(first.right - settled.right) <= 1, `${theme}: column final on first paint`);
    if (shots) await page.screenshot({ path: join(shots, `${theme}-20-95.png`) });

    // 5 is nearer the left edge; then 65 is nearer the right edge (95) than the left (5).
    await stop(5).click(); await stop(65).click();
    assert.equal(await checkedStops(), '5 65');
    assert.ok(await fits(5, 65), `${theme}: off-centre column`);
    if (shots) await page.screenshot({ path: join(shots, `${theme}-5-65.png`) });
    // Halfway between 5 and 65 the left edge moves, so the column can narrow from either side.
    await stop(35).click();
    assert.equal(await checkedStops(), '35 65');
    assert.ok(await fits(35, 65), `${theme}: narrow column`);

    // Choosing a checked radio again returns to the site's normal column.
    await stop(65).click();
    assert.equal(await checkedStops(), '', `${theme}: reset clears both edges`);
    assert.equal(await page.evaluate(k => localStorage.getItem(k), runtime.readingMarginsStorageKey), null);
    assert.equal(await page.evaluate(() => document.documentElement.hasAttribute('data-reading-margins')), false);

    // A choice saved by the earlier left/right ruler is ignored.
    await page.evaluate(k => localStorage.setItem(k, '24 96'), runtime.readingMarginsStorageKey);
    await page.reload();
    assert.equal(await checkedStops(), '', `${theme}: old saved choice ignored`);
  }

  // Listing pages: the same ruler, and a saved column applies there on first paint.
  const listings = ['/', '/threads/?view=all', '/tags/bug/', '/recent/', '/search?q=hello'];
  for (const theme of themes) {
    await page.evaluate(([themeKey, theme, marginsKey]) => {
      localStorage.clear(); localStorage.setItem(themeKey, theme); localStorage.setItem(marginsKey, '20 95');
    }, [runtime.themeStorageKey, theme, runtime.readingMarginsStorageKey]);
    for (const listing of listings) {
      await page.goto(base + listing);
      assert.equal(await checkedStops(), '20 95', `${theme} ${listing}: saved choice shown`);
      assert.ok(await fits(20, 95), `${theme} ${listing}: column between the checked radios`);
      const settled = await column();
      const first = await firstPaintColumn();
      assert.ok(first && Math.abs(first.left - settled.left) <= 1 && Math.abs(first.right - settled.right) <= 1, `${theme} ${listing}: column final on first paint`);
      assert.equal(await pageScrollsSideways(), false, `${theme} ${listing}: no sideways scroll`);
      if (shots && listing === '/') await page.screenshot({ path: join(shots, `${theme}-board-20-95.png`) });
    }
    if (shots) await page.evaluate(k => localStorage.removeItem(k), runtime.readingMarginsStorageKey);
    if (shots) { await page.goto(base + '/'); await page.screenshot({ path: join(shots, `${theme}-board-default.png`) }); }
  }

  // Phones: no ruler, and a saved column does not narrow the page.
  await page.setViewportSize({ width: 390, height: 844 });
  await page.evaluate(k => { localStorage.clear(); localStorage.setItem(k, '35 65'); }, runtime.readingMarginsStorageKey);
  await page.goto(url);
  assert.equal(await page.locator('[data-role="reading-margins"]').isVisible(), false, 'phone hides the ruler');
  const phone = await column();
  assert.ok(phone.right - phone.left > 300, 'phone keeps the full-width column');
  if (shots) await page.screenshot({ path: join(shots, 'phone.png') });

  assert.deepEqual(errors, []);
  console.log(`reading margins: ${themes.length} themes at 1280px on a thread and ${listings.length} listing pages, plus phone`);
} finally {
  if (browser) await browser.close();
  try { process.kill(-server.pid); } catch {}
  await rm(root, { recursive: true, force: true });
}
