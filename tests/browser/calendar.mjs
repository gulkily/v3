// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/calendar.mjs
// The calendar: a day page posts an event through the shared composer, signed with JavaScript
// and plain without it; the grid and day page fit a phone and a desktop.
import assert from 'node:assert/strict';
import { mkdtemp } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
import { chromium } from 'playwright-core';

const project = resolve(new URL('../..', import.meta.url).pathname);
const shots = process.env.CALENDAR_SCREENSHOTS || '';
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'seed' }], format: 'armored' });
const root = await mkdtemp(join(tmpdir(), 'calendar-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root, FORUM_EVENT_SUPPORT_ENABLED: 'true',
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seeded = spawnSync('php', [join(project, 'tests/Support/signed_reaction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const open = async (javaScriptEnabled, width = 390) => {
  const context = await browser.newContext({ serviceWorkers: 'block', javaScriptEnabled, viewport: { width, height: 844 } });
  const page = await context.newPage();
  const seen = { dialogs: [], errors: [] };
  page.on('dialog', d => { seen.dialogs.push(d.message()); void d.dismiss(); });
  page.on('pageerror', e => seen.errors.push(e.message));
  return { context, page, seen };
};
async function settled(page) {
  for (let quiet = 0, i = 0; quiet < 3 && i < 40; i++) {
    const url = page.url(); await page.waitForTimeout(400);
    quiet = page.url() === url && await page.evaluate(() => document.readyState === 'complete').catch(() => false) ? quiet + 1 : 0;
  }
}
const fitsWidth = page => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);
try {
  // The fixture repository's committed root-approved operator.
  const operator = 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954';
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }

  // With JavaScript: a draft started on /compose/thread does not change a day page's date or tag.
  const writer = await open(true);
  await writer.page.goto(base + '/compose/thread?event_date=2026-01-01');
  await settled(writer.page);
  await writer.page.locator('[data-compose-kind="thread"] textarea[name="body"]').fill('A draft from the compose page');
  await writer.page.waitForTimeout(600);
  await writer.page.goto(base + '/calendar/couches/2026-11-30');
  await settled(writer.page);
  const form = writer.page.locator('[data-compose-kind="thread"]');
  assert.equal(await form.locator('[name="event_date"]').inputValue(), '2026-11-30');
  assert.equal(await form.locator('[name="board_tags"]').inputValue(), 'couches');
  assert.equal(await form.locator('[name="body"]').inputValue(), '');
  assert.equal(await form.locator('[data-compose-disclosure="event"]').evaluate(d => d.open), true);

  // Post a two-night offer, signed by the browser's key.
  await form.getByRole('textbox', { name: 'Title' }).fill('Spare room in Cambridge');
  await form.getByRole('textbox', { name: 'Body' }).fill('Two nights, quiet street');
  await form.locator('[name="event_end_date"]').fill('2026-12-01');
  await form.locator('[name="event_location"]').fill('Cambridge');
  await form.getByRole('button', { name: 'Post', exact: true }).click();
  await writer.page.waitForURL(/\/threads\//, { timeout: 60000 });
  const threadId = /\/threads\/([^?#]+)/.exec(writer.page.url())[1];
  await writer.page.getByText('2026-11-30 – 2026-12-01').first().waitFor();
  const record = spawnSync('git', ['-C', join(root, 'repository'), 'show', `HEAD:${spawnSync('git', ['-C', join(root, 'repository'), 'log', '-1', '--name-only', '--format=', '--', 'records/posts'], { encoding: 'utf8' }).stdout.trim().split('\n')[0]}`], { encoding: 'utf8' }).stdout;
  assert.match(record, /Board-Tags: couches\n/);
  assert.match(record, /Author-Identity-ID: openpgp:/, 'signed by the browser key');
  assert.match(record, /Event-Date: 2026-11-30\nEvent-End-Date: 2026-12-01\n/);
  assert.match(record, /Event-Location: Cambridge\n/);
  // Only approved members put events on the calendar: the new browser key's event waits for approval.
  await writer.page.goto(base + '/calendar/couches?month=2026-11');
  assert.equal(await writer.page.locator(`a[href="/threads/${threadId}"]`).count(), 0, 'unapproved author: not on the calendar');
  const writerIdentity = /Author-Identity-ID: (openpgp:[0-9a-f]+)/.exec(record)[1];
  const approved = spawnSync('php', [join(project, 'scripts/inject_approval.php'), 'approve', operator, writerIdentity,
    join(root, 'repository'), join(root, 'read.sqlite3'), join(root, 'static')], { encoding: 'utf8', env });
  assert.equal(approved.status, 0, approved.stdout + approved.stderr);
  await writer.page.goto(base + '/calendar/couches?month=2026-11');
  for (const day of ['2026-11-30', '2026-12-01']) {
    assert.equal(await writer.page.locator(`[data-calendar-day="${day}"]`).getAttribute('data-calendar-mark'), 'open');
  }
  assert.equal(await writer.page.locator(`[data-calendar-day="2026-11-30"] a[href="/threads/${threadId}"]`).count(), 1);
  assert.deepEqual(writer.seen, { dialogs: [], errors: [] });
  await writer.context.close();

  // Without JavaScript the same day page posts plainly.
  const plain = await open(false);
  await plain.page.goto(base + '/calendar/couches/2026-11-28');
  await plain.page.locator('[data-compose-disclosure="event"]').evaluate(d => { d.open = true; });
  await plain.page.getByRole('textbox', { name: 'Title' }).fill('Couch without JavaScript');
  await plain.page.getByRole('textbox', { name: 'Body' }).fill('Plain post');
  await plain.page.locator('[data-compose-kind="thread"] .compose-form-actions > button[type="submit"]').click();
  await plain.page.waitForLoadState('load');
  await plain.page.getByText('Couch without JavaScript').first().waitFor();
  // An anonymous post keeps its event on its thread but stays off the calendar.
  await plain.page.goto(base + '/calendar/couches/2026-11-28');
  assert.equal(await plain.page.getByRole('link', { name: 'Couch without JavaScript' }).count(), 0, 'anonymous: not on the calendar');
  await plain.context.close();

  // Five events on one day: the cell caps its list and the grid stays inside a phone's width.
  for (let n = 1; n <= 5; n++) {
    const body = new URLSearchParams({ board_tags: 'couches', subject: `Busy night ${n}`, body: 'x', event_date: '2026-11-25', author_identity_id: operator });
    const response = await fetch(base + '/api/create_thread', { method: 'POST', body });
    assert.equal(response.ok, true, await response.text());
  }
  for (const width of [390, 1280]) {
    const viewer = await open(true, width);
    await viewer.page.goto(base + '/calendar/couches?month=2026-11');
    assert.equal(await viewer.page.locator('[data-calendar-day="2026-11-25"] li.calendar-entry').count(), 3);
    if (width >= 1024) {
      assert.equal(await viewer.page.locator('[data-calendar-day="2026-11-25"] .calendar-day__more').textContent(), '+2 more');
      assert.equal(await viewer.page.locator('[data-calendar-day="2026-11-25"] li.calendar-entry').first().isVisible(), true);
      assert.equal(await viewer.page.locator('[data-calendar-titles]').isVisible(), false, 'desktop: no titles toggle');
    } else {
      // A phone shows each day's count by default; titles are on the day page.
      const square = viewer.page.locator('[data-calendar-day="2026-11-25"]');
      const title = square.locator('li.calendar-entry').first();
      assert.equal(await square.locator('.calendar-day__count').textContent(), '5');
      assert.equal(await title.isVisible(), false, 'phone: counts only by default');
      assert.equal(await viewer.page.locator('[data-calendar-day="2026-11-29"] .calendar-day__number').isVisible(), true, 'Saturday is on screen');
      // Show titles: the first words of each, in tiny text, inside the square.
      const toggle = viewer.page.locator('[data-calendar-titles]');
      assert.equal(await toggle.textContent(), 'Show titles');
      await toggle.click();
      assert.equal(await toggle.getAttribute('aria-pressed'), 'true');
      assert.equal(await title.isVisible(), true, 'phone: titles shown');
      const [titleBox, squareBox] = [await title.boundingBox(), await square.boundingBox()];
      assert.ok(titleBox.y + titleBox.height <= squareBox.y + squareBox.height + 0.5 && titleBox.x + titleBox.width <= squareBox.x + squareBox.width + 0.5, 'phone: title inside its square');
      assert.equal(await viewer.page.locator('[data-calendar-day="2026-11-29"] .calendar-day__number').isVisible(), true, 'Saturday is on screen with titles');
      assert.equal(await fitsWidth(viewer.page), true, 'grid page with titles fits a phone');
      // The titles are too small to aim at: a tap on one opens the day.
      await viewer.page.mouse.click(titleBox.x + titleBox.width / 2, titleBox.y + titleBox.height / 2);
      await viewer.page.waitForURL(/\/calendar\/couches\/2026-11-25$/);
      // The choice is remembered, and switches back.
      await viewer.page.goto(base + '/calendar/couches?month=2026-11');
      assert.equal(await title.isVisible(), true, 'phone: titles remembered');
      await toggle.click();
      assert.equal(await title.isVisible(), false, 'phone: back to counts');
      assert.equal(await toggle.textContent(), 'Show titles');
    }
    assert.equal(await fitsWidth(viewer.page), true, `grid page fits ${width}px`);
    // Days next to the blank cells before the 1st keep their borders: the 1st's left edge, the second row's top edges.
    const edges = await viewer.page.evaluate(() => {
      const visible = (cell, side) => { const style = getComputedStyle(cell); return parseFloat(style[`border${side}Width`]) > 0 && style[`border${side}Style`] !== 'none' && !/rgba\(0, 0, 0, 0\)/.test(style[`border${side}Color`]); };
      return [visible(document.querySelector('[data-calendar-day="2026-11-01"]'), 'Left'),
        visible(document.querySelector('[data-calendar-day="2026-12-01"]'), 'Left'),
        ...['2026-12-06', '2026-12-07'].map(day => visible(document.querySelector(`[data-calendar-day="${day}"]`), 'Top'))];
    });
    assert.deepEqual(edges, [true, true, true, true], `${width}px: edge borders`);
    // A click anywhere in an empty square opens that day.
    const empty = await viewer.page.locator('[data-calendar-day="2026-11-03"]').boundingBox();
    await viewer.page.mouse.click(empty.x + empty.width - 4, empty.y + empty.height - 4);
    await viewer.page.waitForURL(/\/calendar\/couches\/2026-11-03$/);
    await viewer.page.goBack();
    if (width >= 1024) {
      // A title opens its thread; the rest of that square opens the day.
      assert.equal(await viewer.page.locator('[data-calendar-day="2026-11-25"] .calendar-entry__author').count(), 0, 'titles only');
      await viewer.page.locator('[data-calendar-day="2026-11-25"] li.calendar-entry a').first().click();
      await viewer.page.waitForURL(/\/threads\//);
      await viewer.page.goBack();
      const busy = await viewer.page.locator('[data-calendar-day="2026-11-25"]').boundingBox();
      await viewer.page.mouse.click(busy.x + busy.width - 4, busy.y + busy.height - 4);
      await viewer.page.waitForURL(/\/calendar\/couches\/2026-11-25$/);
      await viewer.page.goBack();
    }
    if (shots) await viewer.page.screenshot({ path: join(shots, `calendar-grid-${width}.png`), fullPage: true });
    await viewer.page.goto(base + '/calendar/couches/2026-11-25');
    assert.equal(await fitsWidth(viewer.page), true, `day page fits ${width}px`);
    if (shots) await viewer.page.screenshot({ path: join(shots, `calendar-day-${width}.png`), fullPage: true });
    assert.deepEqual(viewer.seen, { dialogs: [], errors: [] });
    await viewer.context.close();
  }
  // Every theme: no page overflow at phone and desktop widths, and empty, open and
  // confirmed days look different from one another.
  const confirmedDay = '2026-11-30';
  {
    const settle = new URLSearchParams({ thread_id: threadId, parent_id: threadId, body: 'event: confirmed', author_identity_id: operator });
    const settled = await fetch(base + '/api/create_reply', { method: 'POST', body: settle });
    assert.equal(settled.ok, true, await settled.text());
  }
  const themes = ['light', 'dark', 'console', 'lcd', 'chicago', 'vapor', 'forge', 'sticker', 'arena', 'thermal', 'whitehot', 'word97', 'midnight'];
  for (const theme of themes) {
    for (const width of [390, 1280]) {
      const viewer = await open(true, width);
      await viewer.page.addInitScript(theme => {
        localStorage.setItem('zenmemes-theme', theme);
        for (const key of Object.keys(localStorage)) if (key.endsWith('-theme')) localStorage.setItem(key, theme);
      }, theme);
      await viewer.page.goto(base + '/calendar/couches?month=2026-11');
      assert.equal(await viewer.page.locator('html').getAttribute('data-theme'), theme);
      assert.equal(await fitsWidth(viewer.page), true, `${theme} ${width}px: grid fits`);
      const look = day => viewer.page.locator(`[data-calendar-day="${day}"]`).evaluate(cell => {
        const style = getComputedStyle(cell);
        return `${style.backgroundColor}|${style.borderTopColor}`;
      });
      // Text in a marked day stays readable on that day's background (WCAG ratio, large-text floor).
      const contrast = day => viewer.page.locator(`[data-calendar-day="${day}"]`).evaluate(cell => {
        const rgba = value => { const n = (value.match(/[\d.]+/g) || []).map(Number); return [n[0] ?? 0, n[1] ?? 0, n[2] ?? 0, n.length > 3 ? n[3] : 1]; };
        const luminance = ([r, g, b]) => [r, g, b].map(c => { c /= 255; return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4; })
          .reduce((sum, c, i) => sum + c * [0.2126, 0.7152, 0.0722][i], 0);
        // Composite translucent backgrounds from the cell up until one is opaque.
        const layers = [];
        for (let node = cell; node; node = node.parentElement) {
          const layer = rgba(getComputedStyle(node).backgroundColor);
          if (layer[3] > 0) layers.push(layer);
          if (layer[3] >= 1) break;
        }
        let bgColor = [255, 255, 255];
        for (const [r, g, b, a] of layers.reverse()) bgColor = [r * a + bgColor[0] * (1 - a), g * a + bgColor[1] * (1 - a), b * a + bgColor[2] * (1 - a)];
        const bg = luminance(bgColor);
        return Array.from(cell.querySelectorAll('a, .meta, .calendar-day__count')).filter(el => el.getClientRects().length > 0)
          .map(el => { const fg = luminance(rgba(getComputedStyle(el).color)); return { ratio: (Math.max(fg, bg) + 0.05) / (Math.min(fg, bg) + 0.05), what: `${el.className || el.tagName} ${getComputedStyle(el).color} on rgb(${bgColor.map(Math.round)})` }; })
          .reduce((worst, item) => item.ratio < worst.ratio ? item : worst, { ratio: Infinity, what: '' });
      });
      for (const day of ['2026-11-25', confirmedDay]) {
        const { ratio, what } = await contrast(day);
        assert.ok(ratio >= 3, `${theme} ${width}px: text on ${day} has contrast ${ratio.toFixed(2)} (${what})`);
      }
      const empty = await look('2026-11-02');
      const open_ = await look('2026-11-25');
      assert.notEqual(open_, empty, `${theme} ${width}px: open differs from empty`);
      {
        assert.equal(await viewer.page.locator(`[data-calendar-day="${confirmedDay}"]`).getAttribute('data-calendar-mark'), 'confirmed');
        const confirmed = await look(confirmedDay);
        assert.notEqual(confirmed, empty, `${theme} ${width}px: confirmed differs from empty`);
        assert.notEqual(confirmed, open_, `${theme} ${width}px: confirmed differs from open`);
      }
      if (shots && ['light', 'dark', 'word97', 'chicago', 'midnight'].includes(theme)) {
        await viewer.page.screenshot({ path: join(shots, `calendar-${theme}-${width}.png`), fullPage: true });
      }
      await viewer.page.goto(base + '/calendar/couches/2026-11-30');
      assert.equal(await fitsWidth(viewer.page), true, `${theme} ${width}px: day page fits`);
      assert.deepEqual(viewer.seen, { dialogs: [], errors: [] });
      await viewer.context.close();
    }
  }
  console.log('calendar: ok');
} catch (error) {
  console.error(serverLog.slice(-4000));
  throw error;
} finally {
  await browser.close();
  server.kill();
}
