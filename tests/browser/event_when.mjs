// Run with: CHROMIUM_PATH=/usr/bin/google-chrome node tests/browser/event_when.mjs
// The When box: Fill reads a phrase into Date, Until and Time in place with JavaScript, by
// submitting without it, keeps a calendar day page's date, and never posts.
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
const shots = process.env.EVENT_WHEN_SCREENSHOTS || '';
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'seed' }], format: 'armored' });
const root = await mkdtemp(join(tmpdir(), 'event-when-'));
const env = { ...process.env, FORUM_SITE_ID: 'zenmemes', SIGNED_REACTION_TEST_ROOT: root,
  FORUM_EVENT_SUPPORT_ENABLED: 'true', FORUM_EVENT_WHEN_ENABLED: 'true',
  FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
const seeded = spawnSync('php', [join(project, 'tests/Support/signed_reaction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
assert.equal(seeded.status, 0, seeded.stderr);
const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening'); const port = socket.address().port; await new Promise(r => socket.close(r));
const base = `http://127.0.0.1:${port}`;
const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
let serverLog = ''; server.stderr.on('data', d => { serverLog += d; });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
const open = async (javaScriptEnabled, width = 390, timezoneId = 'America/New_York') => {
  const context = await browser.newContext({ serviceWorkers: 'block', javaScriptEnabled, viewport: { width, height: 844 }, timezoneId });
  const page = await context.newPage();
  const seen = { dialogs: [], errors: [] };
  page.on('dialog', d => { seen.dialogs.push(d.message()); void d.dismiss(); });
  page.on('pageerror', e => seen.errors.push(e.message));
  return { context, page, seen };
};
const fitsWidth = page => page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth);
const field = (form, name) => form.locator(`[name="${name}"]`);
const localDate = (page, offsetDays) => page.evaluate(days => {
  const d = new Date(); d.setDate(d.getDate() + days);
  return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
}, offsetDays);
const head = () => spawnSync('git', ['-C', join(root, 'repository'), 'rev-parse', 'HEAD'], { encoding: 'utf8' }).stdout.trim();
try {
  for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
  const before = head();
  assert.match(before, /^[0-9a-f]{40}$/);

  for (const [width, timezoneId] of [[390, 'America/New_York'], [1280, 'Asia/Tokyo']]) {
    const writer = await open(true, width, timezoneId);
    await writer.page.goto(base + '/compose/thread');
    const form = writer.page.locator('[data-compose-kind="thread"]');
    await form.locator('[data-compose-disclosure="event"] > summary').click();
    const when = form.getByRole('textbox', { name: 'When' });
    assert.equal(await when.isVisible(), true);

    // Fill in place, with the browser's own date.
    await when.fill('tomorrow 7pm');
    await form.getByRole('button', { name: 'Fill' }).click();
    await assert.doesNotReject(() => writer.page.waitForFunction(() => document.querySelector('[name="event_time"]').value === '19:00'));
    assert.equal(await field(form, 'event_date').inputValue(), await localDate(writer.page, 1));
    assert.equal(await form.locator('[data-role="event-when-status"]').isHidden(), true);
    assert.match(await form.locator('[data-compose-disclosure="event"] [data-role="compose-disclosure-summary"]').textContent(), /19:00/);

    // Enter means Fill, not Post; a span fills Until.
    await when.fill('Friday to Sunday');
    await when.press('Enter');
    await writer.page.waitForFunction(() => document.querySelector('[name="event_end_date"]').value !== '');
    assert.equal(writer.page.url(), base + '/compose/thread');
    assert.equal(await field(form, 'event_time').inputValue(), '');

    // An unread phrase changes nothing and says why.
    const kept = await field(form, 'event_date').inputValue();
    await when.fill('whenever works');
    await form.getByRole('button', { name: 'Fill' }).click();
    await form.locator('[data-role="event-when-status"]:not([hidden])').waitFor();
    assert.equal(await form.locator('[data-role="event-when-status"]').textContent(), 'Could not read a date from that.');
    assert.equal(await field(form, 'event_date').inputValue(), kept);
    assert.equal(await fitsWidth(writer.page), true);
    if (shots) await writer.page.screenshot({ path: join(shots, `event-when-compose-${width}.png`), fullPage: true });
    assert.deepEqual(writer.seen, { dialogs: [], errors: [] });
    await writer.context.close();
  }

  // A calendar day page keeps its date; Fill still sets the time.
  const day = await open(true);
  await day.page.goto(base + '/calendar/2027-03-05');
  const dayForm = day.page.locator('[data-compose-kind="thread"]');
  assert.equal(await dayForm.locator('[data-compose-disclosure="event"]').evaluate(d => d.open), true, 'a day page opens Event');
  await dayForm.getByRole('textbox', { name: 'When' }).fill('Monday at 8');
  await dayForm.getByRole('button', { name: 'Fill' }).click();
  await day.page.waitForFunction(() => document.querySelector('[name="event_time"]').value === '20:00');
  assert.equal(await field(dayForm, 'event_date').inputValue(), '2027-03-05');
  assert.equal(await dayForm.locator('[data-role="event-when-status"]').textContent(), 'The date stays 2027-03-05 on this page.');
  assert.deepEqual(day.seen, { dialogs: [], errors: [] });
  await day.context.close();

  // Without JavaScript: Fill submits, the composer comes back filled, and nothing is posted.
  const plain = await open(false);
  await plain.page.goto(base + '/compose/thread');
  const plainForm = plain.page.locator('[data-compose-kind="thread"]');
  await plainForm.locator('[data-compose-disclosure="event"] > summary').click();
  await plainForm.getByRole('textbox', { name: 'Body' }).fill('Games night body');
  await plainForm.getByRole('textbox', { name: 'When' }).fill('2027-04-02 at 6:30pm');
  await plainForm.getByRole('button', { name: 'Fill' }).click();
  await plain.page.waitForLoadState();
  const filled = plain.page.locator('[data-compose-kind="thread"]');
  assert.equal(await field(filled, 'event_date').inputValue(), '2027-04-02');
  assert.equal(await field(filled, 'event_time').inputValue(), '18:30');
  assert.equal(await filled.getByRole('textbox', { name: 'Body' }).inputValue(), 'Games night body');
  assert.match(await filled.locator('[data-role="event-when-status"]').textContent(), /\(UTC\)\.$/);
  assert.equal(await fitsWidth(plain.page), true);
  if (shots) await plain.page.screenshot({ path: join(shots, 'event-when-no-js.png'), fullPage: true });
  await plain.context.close();

  assert.equal(head(), before, 'Fill never posts');
  console.log('event_when: ok');
} catch (error) {
  console.error(serverLog.slice(-4000));
  throw error;
} finally {
  await browser.close();
  server.kill();
}
