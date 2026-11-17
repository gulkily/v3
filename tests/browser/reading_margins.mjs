import assert from 'node:assert/strict';
import { cp, mkdtemp, mkdir, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

// Reading margins (FORUM_READING_MARGINS_ENABLED) on a thread page, in every
// permitted theme: with nothing chosen the two home radios are checked over the
// normal column's edges, the radios are evenly spaced in one row and none is
// outside the window, every click moves the nearer column edge at once and the
// edges never cross, ties move the edge clicked longer ago, which is ringed
// only when a tie is possible, each checked radio sits over the column edge it
// sets, the bar on the ruler and the header match a chosen column, a choice
// survives reload without moving after paint, choosing a checked radio or
// clicking back to the home stops resets, the column never gets narrower than
// 20rem or leaves the window, a column saved in a wider window waits until it
// fits, other pages share the saved column, and phones show neither the ruler
// nor a saved column.
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
  const byPosition = values => values.map(Number).sort((a, b) => a - b).join(' ');
  const checkedStops = async () => byPosition(await page.locator('[data-reading-margin-stop]:checked').evaluateAll(all => all.map(each => each.value)));
  const edges = selector => page.locator(selector).evaluate(each => { const r = each.getBoundingClientRect(); return { left: r.left, right: r.right }; });
  const column = () => edges('.main');
  const same = (a, b, slack = 2) => Math.abs(a.left - b.left) <= slack && Math.abs(a.right - b.right) <= slack;
  const nextStop = () => page.locator('[data-reading-margin-next]').evaluateAll(all => all.map(each => each.value).join(' '));
  const stopCentre = async at => { const r = await stop(at).boundingBox(); return r.x + r.width / 2; };
  const fits = async (left, right) => same(await column(), { left: await stopCentre(left), right: await stopCentre(right) });
  const shown = () => page.locator('[data-reading-margin-stop]').evaluateAll(all => all.filter(each => each.offsetParent !== null).map(each => { const r = each.getBoundingClientRect(); return { at: Number(each.value), x: r.left + r.width / 2, y: r.top + r.height / 2 }; }));
  const saved = () => page.evaluate(k => localStorage.getItem(k), runtime.readingMarginsStorageKey);
  const chosen = () => page.evaluate(() => document.documentElement.getAttribute('data-reading-margins'));
  const pageScrollsSideways = () => page.evaluate(() => document.documentElement.scrollWidth > document.documentElement.clientWidth);
  const windowWidth = () => page.evaluate(() => document.documentElement.clientWidth);
  // The ruler in step with the page: the bar matches the column, and the header
  // matches it when a column is chosen and keeps its own layout otherwise.
  const inStep = async (label, normalHeader) => {
    assert.ok(same(await edges('.reading-margins__column'), await column()), `${label}: bar matches the column`);
    if (await chosen() === null) assert.ok(same(await edges('.site-header'), normalHeader, 0.5), `${label}: header keeps its normal layout`);
    else assert.ok(same(await edges('.site-header'), await column()), `${label}: header matches the column`);
    const header = await page.locator('.site-header').evaluate(each => ({ wide: each.scrollWidth, room: each.clientWidth }));
    assert.ok(header.wide <= header.room + 1, `${label}: header contents fit inside it`);
    assert.equal(await pageScrollsSideways(), false, `${label}: no sideways scroll`);
  };
  // The first paint already has the saved column: record .main's edges from the
  // earliest point the body is parsed and compare them with the settled page.
  // Edges are measured in the ruler's own units (0 and 1 are the normal
  // column's edges), which hold while a theme's stylesheet is still loading
  // and when a scrollbar appears.
  await context.addInitScript(() => {
    window.__columnOnRuler = () => {
      const main = document.querySelector('.main').getBoundingClientRect();
      const ruler = document.querySelector('[data-role="reading-margins"]').getBoundingClientRect();
      return { left: (main.left - ruler.left) / ruler.width, right: (main.right - ruler.left) / ruler.width };
    };
    new MutationObserver((records, observer) => {
      if (!document.querySelector('.main') || !document.querySelector('[data-role="reading-margins"]')) return;
      window.__firstColumn = window.__columnOnRuler(); observer.disconnect();
    }).observe(document, { childList: true, subtree: true });
  });
  const finalOnFirstPaint = async label => {
    const first = await page.evaluate(() => window.__firstColumn);
    const settled = await page.evaluate(() => window.__columnOnRuler());
    assert.ok(same(first || {}, settled, 0.005), `${label}: column final on first paint (${JSON.stringify(first)} then ${JSON.stringify(settled)})`);
  };
  const reloadWith = async value => {
    await page.evaluate(([k, v]) => localStorage.setItem(k, v), [runtime.readingMarginsStorageKey, value]);
    await page.reload();
  };

  for (const theme of themes) {
    await page.evaluate(([key, theme]) => { localStorage.clear(); localStorage.setItem(key, theme); }, [runtime.themeStorageKey, theme]);
    await page.goto(url);
    // Nothing chosen: the home radios are checked over the normal column.
    assert.equal(await checkedStops(), '0 5', `${theme}: home stops checked`);
    assert.ok(await fits(0, 5), `${theme}: home stops sit on the normal column's edges`);
    assert.equal(await chosen(), null, `${theme}: nothing chosen yet`);
    assert.equal(await nextStop(), '', `${theme}: no ring in the normal column`);
    const normal = await column();
    const normalHeader = await edges('.site-header');
    await inStep(`${theme} normal`, normalHeader);
    // One row: same top, equal gaps, consecutive stops, all inside the window.
    const row = await shown();
    const width = await windowWidth();
    assert.ok(row.length >= 8, `${theme}: at least eight stops at 1280px (${row.length})`);
    assert.ok(row[0].at <= -1 && row[row.length - 1].at >= 6, `${theme}: stops on both sides of the normal column`);
    const gap = row[1].x - row[0].x;
    row.forEach(({ at, x, y }, i) => {
      assert.equal(at, row[0].at + i, `${theme}: consecutive stops`);
      assert.ok(Math.abs(y - row[0].y) <= 1, `${theme}: stops share one row`);
      assert.ok(Math.abs(x - row[0].x - i * gap) <= 1.5, `${theme}: stops evenly spaced`);
      assert.ok(x >= 12 && x <= width - 12, `${theme}: stop ${at} inside the window`);
    });
    if (shots) await page.screenshot({ path: join(shots, `${theme}-default.png`) });

    // A checked home radio does nothing in the normal column.
    await stop(0).click();
    assert.equal(await checkedStops(), '0 5', `${theme}: home radio stays checked`);
    assert.equal(await saved(), null);

    // Each click moves the nearer edge. 6 is nearer the right edge; 0-6 has 3
    // midway, so 0, the edge not just clicked, is ringed.
    await stop(6).click();
    assert.equal(await checkedStops(), '0 6', `${theme}: 6 moved the right edge`);
    assert.ok(await fits(0, 6), `${theme}: column 0 to 6`);
    assert.equal(await nextStop(), '0', `${theme}: older edge ringed when a tie is possible`);
    await inStep(`${theme} 0-6`, normalHeader);
    if (shots) await page.screenshot({ path: join(shots, `${theme}-wider.png`) });
    // -1 is outside on the left. -1 to 6 has no midway stop: no ring.
    await stop(-1).click();
    assert.equal(await checkedStops(), '-1 6', `${theme}: -1 moved the left edge`);
    assert.ok(await fits(-1, 6), `${theme}: column -1 to 6`);
    assert.equal(await nextStop(), '', `${theme}: no tie possible, no ring`);
    assert.equal(await saved(), '6 -1', `${theme}: saved older first`);
    await inStep(`${theme} -1-6`, normalHeader);
    // 2 is nearer -1.
    await stop(2).click();
    assert.equal(await checkedStops(), '2 6', `${theme}: 2 moved the left edge`);
    assert.ok(await fits(2, 6), `${theme}: column 2 to 6`);
    assert.equal(await nextStop(), '6');
    await inStep(`${theme} 2-6`, normalHeader);
    if (shots) await page.screenshot({ path: join(shots, `${theme}-2-6.png`) });

    // A reload keeps the pair and which edge moves next, final on first paint.
    await page.reload();
    assert.equal(await checkedStops(), '2 6', `${theme}: choice restored`);
    assert.equal(await nextStop(), '6', `${theme}: ring restored`);
    await finalOnFirstPaint(theme);
    await inStep(`${theme} 2-6 reloaded`, normalHeader);

    // 4 is midway, a tie: 6, the ringed edge, moves. Then 3: one step is
    // narrower than 20rem in every theme, so the column is 20rem, centred
    // between the two radios.
    await stop(4).click();
    assert.equal(await checkedStops(), '2 4', `${theme}: tie moved the ringed edge`);
    assert.equal(await nextStop(), '2');
    await stop(3).click();
    assert.equal(await checkedStops(), '3 4', `${theme}: second tie moved the ringed edge`);
    const narrow = await column();
    const halfway = (await stopCentre(3) + await stopCentre(4)) / 2;
    assert.ok(Math.abs(narrow.right - narrow.left - 320) <= 1, `${theme}: column no narrower than 20rem`);
    assert.ok(Math.abs((narrow.left + narrow.right) / 2 - halfway) <= 2, `${theme}: narrow column centred between the radios`);
    if (shots) await page.screenshot({ path: join(shots, `${theme}-narrow.png`) });
    await inStep(`${theme} 20rem`, normalHeader);

    // Clicking a checked radio returns to the normal column.
    await stop(3).click();
    assert.equal(await checkedStops(), '0 5', `${theme}: checked radio resets to the home stops`);
    assert.equal(await saved(), null);
    assert.equal(await chosen(), null);
    assert.equal(await page.locator('[data-reading-margin-next]').count(), 0, `${theme}: ring cleared`);
    assert.ok(same(await column(), normal, 0.5), `${theme}: normal column back`);
    await inStep(`${theme} reset`, normalHeader);
    // So does clicking back to the home stops.
    await stop(3).click();
    assert.equal(await checkedStops(), '0 3', `${theme}: 3 moved the right edge`);
    assert.equal(await saved(), '0 3');
    await stop(5).click();
    assert.equal(await checkedStops(), '0 5');
    assert.equal(await saved(), null, `${theme}: the home stops are the normal column`);
    assert.equal(await chosen(), null);

    // A pair saved in either order is restored; anything else is ignored,
    // including values from the earlier vw stops.
    await reloadWith('5 1');
    assert.equal(await checkedStops(), '1 5', `${theme}: right-to-left pair restored`);
    assert.equal(await nextStop(), '5', `${theme}: older edge ringed after reload`);
    assert.ok(await fits(1, 5), `${theme}: restored column`);
    for (const bad of ['41 77', '5 23', '0 5', '5 0', '3 3', '2 14', '2 x', '2.5 4']) {
      await reloadWith(bad);
      assert.equal(await checkedStops(), '0 5', `${theme}: saved value ${bad} ignored`);
      assert.equal(await chosen(), null, `${theme}: saved value ${bad} ignored`);
    }
    // A column saved in a wider window waits: normal column here, choice kept.
    await reloadWith('-8 5');
    assert.equal(await checkedStops(), '0 5', `${theme}: a stop outside the window is not used`);
    assert.ok(same(await column(), normal, 0.5), `${theme}: normal column when the saved one does not fit`);
    assert.equal(await saved(), '-8 5', `${theme}: saved choice left alone`);
    await finalOnFirstPaint(`${theme} unfit`);
  }

  // A long run of clicks: a click outside the column moves the edge on its
  // side, so the edges never cross; a click inside moves the nearer edge.
  await page.evaluate(() => localStorage.clear());
  await page.goto(url);
  let [left, right] = [0, 5];
  for (const click of [-1, 1, 6, 2, 4, 6, 2, 1, 6, -1, 4, 1, 5, 2, -1, 6, 3, 5, 0, 3]) {
    if ([left, right].includes(click)) { await stop(click).click(); [left, right] = [0, 5]; assert.equal(await checkedStops(), '0 5', `run: ${click} resets`); continue; }
    if (click < left || (click < right && click - left < right - click)) left = click;
    else if (click > right || right - click < click - left) right = click;
    else { await stop(click).click(); [left, right] = (await checkedStops()).split(' ').map(Number); continue; }
    await stop(click).click();
    assert.equal(await checkedStops(), `${left} ${right}`, `run: click ${click}`);
  }

  // Other pages: the same ruler, and a saved column applies there on first paint.
  // The board is /?view=liked (its default view): under ./v3 start a bare / is the
  // local development page (LocalDevelopmentImport::isEntry).
  const board = '/?view=liked';
  const listings = [board, '/threads/?view=all', '/tags/bug/', '/recent/', '/search?q=hello', '/users/', '/unread',
    '/messages/', '/about/', '/tools/', '/tools/bookmarklets', '/docs/', '/docs/reference/agent_api.md'];
  for (const theme of themes) {
    await page.evaluate(([themeKey, theme, marginsKey]) => {
      localStorage.clear(); localStorage.setItem(themeKey, theme); localStorage.setItem(marginsKey, '-1 4');
    }, [runtime.themeStorageKey, theme, runtime.readingMarginsStorageKey]);
    for (const listing of listings) {
      await page.goto(base + listing);
      assert.equal(await checkedStops(), '-1 4', `${theme} ${listing}: saved choice shown`);
      assert.ok(await fits(-1, 4), `${theme} ${listing}: column between the checked radios`);
      await finalOnFirstPaint(`${theme} ${listing}`);
      await inStep(`${theme} ${listing}`, null);
      if (shots && listing === board) await page.screenshot({ path: join(shots, `${theme}-board.png`) });
    }
  }

  // Other desktop widths, in the default theme. A wider window shows more
  // stops; a column saved there waits while the window is too narrow for it and
  // comes back when it fits again. A narrow window keeps the column inside it.
  await page.evaluate(() => localStorage.clear());
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.goto(url);
  const wide = await shown();
  assert.ok(wide.length >= 12, `1920px: more stops (${wide.length})`);
  assert.ok(wide.every(({ x }) => x >= 12 && x <= 1920 - 12), '1920px: stops inside the window');
  assert.equal(await checkedStops(), '0 5');
  const wideNormalHeader = await edges('.site-header');
  await stop(-3).click();
  assert.equal(await checkedStops(), '-3 5');
  assert.ok(await fits(-3, 5), '1920px: column -3 to 5');
  await inStep('1920px -3-5', wideNormalHeader);
  if (shots) await page.screenshot({ path: join(shots, 'wide.png') });
  await page.setViewportSize({ width: 1000, height: 900 });
  await page.waitForFunction(() => !document.documentElement.hasAttribute('data-reading-margins'));
  assert.equal(await checkedStops(), '0 5', '1000px: the wide column waits');
  assert.equal(await saved(), '5 -3', '1000px: saved choice left alone');
  assert.ok((await shown()).every(({ x }) => x >= 12 && x <= 1000 - 12), '1000px: stops inside the window');
  assert.equal(await pageScrollsSideways(), false, '1000px: no sideways scroll');
  await page.setViewportSize({ width: 1920, height: 900 });
  await page.waitForFunction(() => document.documentElement.hasAttribute('data-reading-margins'));
  assert.equal(await checkedStops(), '-3 5', '1920px again: the saved column is back');
  assert.ok(await fits(-3, 5));
  await page.evaluate(() => localStorage.clear());
  await page.setViewportSize({ width: 700, height: 900 });
  await page.goto(url);
  assert.equal(await checkedStops(), '0 5', '700px: home stops checked');
  assert.ok(await fits(0, 5), '700px: home stops on the column');
  await reloadWith('0 1');
  assert.equal(await checkedStops(), '0 1');
  const edge = await column();
  assert.ok(edge.left >= 0 && Math.abs(edge.right - edge.left - 320) <= 1, '700px: a 20rem column at the side stays inside the window');
  await inStep('700px 0-1', null);
  if (shots) await page.screenshot({ path: join(shots, 'narrow-window.png') });

  // Phones: no ruler, and a saved column does not narrow the page.
  await page.setViewportSize({ width: 390, height: 844 });
  await page.evaluate(k => { localStorage.clear(); localStorage.setItem(k, '1 4'); }, runtime.readingMarginsStorageKey);
  await page.goto(url);
  assert.equal(await page.locator('[data-role="reading-margins"]').isVisible(), false, 'phone hides the ruler');
  const phone = await column();
  assert.ok(phone.right - phone.left > 300, 'phone keeps the full-width column');
  if (shots) await page.screenshot({ path: join(shots, 'phone.png') });

  assert.deepEqual(errors, []);
  console.log(`reading margins: ${themes.length} themes at 1280px on a thread and ${listings.length} other pages, plus 1920px, 1000px, 700px and phone`);
} finally {
  if (browser) await browser.close();
  try { process.kill(-server.pid); } catch {}
  await rm(root, { recursive: true, force: true });
}
