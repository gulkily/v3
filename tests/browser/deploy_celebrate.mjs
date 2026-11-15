// Deploy celebration: a header Deploy now that changes the code reloads into a page
// with confetti and a Deployed banner, once. Up to date gets none; reduced motion
// keeps the banner without confetti. Set DEPLOY_CELEBRATE_SHOTS=<dir> for screenshots.
import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
import { readFile, mkdir } from 'node:fs/promises';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';

const project = resolve(new URL('../..', import.meta.url).pathname);
const layout = (deploy) => execFileSync('php', [`${project}/tests/Support/app_layout_fixture.php`, 'board', '1', '1', '0', deploy], { encoding: 'utf8' });
const htmlOn = layout('1');
const htmlOff = layout('0');
assert.ok(/src="[^"]*deploy_celebrate[^"]*"/.test(htmlOn), 'flag on loads the celebration script');
assert.equal(/src="[^"]*deploy_celebrate/.test(htmlOff), false, 'flag off loads no celebration script');
const themeKey = htmlOn.match(/"themeStorageKey":"([^"]+)"/)?.[1];
const shots = process.env.DEPLOY_CELEBRATE_SHOTS || '';
if (shots) await mkdir(shots, { recursive: true });
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });

async function open({ outcome, width = 390, reducedMotion = 'no-preference', theme = null }) {
  const context = await browser.newContext({ viewport: { width, height: 760 }, reducedMotion });
  const page = await context.newPage();
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  await page.addInitScript(([themeStorageKey, chosen]) => {
    localStorage.setItem('forum_deploy_prompt', '1');
    if (chosen && themeStorageKey) localStorage.setItem(themeStorageKey, chosen);
  }, [themeKey, theme]);
  await page.route('http://app.test/**', async (route) => {
    const path = new URL(route.request().url()).pathname;
    if (path === '/tools/deploy/') {
      const body = JSON.parse(route.request().postData() || '{}');
      const payload = body.action === 'check'
        ? { status: 'ok', action: 'check', result: { outcome: 'checked' }, state: { behind: 2 } }
        : { status: 'ok', action: 'now', result: { outcome, message: 'Updated to 1a2b3c4d5e6f.' }, state: { behind: 0 } };
      return route.fulfill({ contentType: 'application/json', body: JSON.stringify(payload) });
    }
    if (path.startsWith('/assets/')) {
      const file = path.replace(/\.[0-9a-f]{12}\./, '.');
      try { return route.fulfill({ contentType: file.endsWith('.css') ? 'text/css' : 'application/javascript', body: await readFile(project + '/public' + file) }); }
      catch { return route.fulfill({ status: 404, body: '' }); }
    }
    if (path.startsWith('/api/')) return route.fulfill({ status: 404, contentType: 'application/json', body: '{}' });
    return route.fulfill({ contentType: 'text/html', body: htmlOn });
  });
  await page.goto('http://app.test/');
  await page.waitForSelector('[data-deploy-prompt]:not([hidden])', { timeout: 6000 });
  await page.click('[data-deploy-prompt-button]');
  await page.waitForFunction(() => performance.getEntriesByType('navigation')[0]?.type === 'reload' && document.readyState === 'complete', null, { timeout: 6000 });
  await page.waitForTimeout(400);
  return { page, context, errors };
}

const state = (page) => page.evaluate(() => ({
  banner: document.querySelector('.deploy-celebration')?.textContent || null,
  confetti: document.querySelectorAll('.deploy-confetti i').length,
  stored: sessionStorage.getItem('forum_deploy_celebrate'),
}));

for (const [width, theme] of [[390, null], [1200, null], [390, 'word97'], [1200, 'midnight']]) {
  const { page, context, errors } = await open({ outcome: 'deployed', width, theme });
  const after = await state(page);
  assert.equal(after.banner, 'Deployed! Updated to 1a2b3c4d5e6f.', `${width}/${theme}: banner`);
  assert.ok(after.confetti > 50, `${width}/${theme}: confetti`);
  assert.equal(after.stored, null, 'the note is consumed');
  const box = await page.locator('.deploy-celebration').boundingBox();
  assert.ok(box && box.x >= 0 && box.x + box.width <= width + 1, `${width}/${theme}: banner fits the screen`);
  if (shots) await page.screenshot({ path: `${shots}/deployed-${width}-${theme || 'default'}.png` });
  // Plays once: a further reload is quiet.
  await page.reload();
  await page.waitForTimeout(300);
  const again = await state(page);
  assert.equal(again.banner, null, 'no banner after another reload');
  assert.equal(again.confetti, 0, 'no confetti after another reload');
  assert.deepEqual(errors, []);
  await context.close();
}

{
  const { page, context } = await open({ outcome: 'up_to_date' });
  const after = await state(page);
  assert.equal(after.banner, null, 'up to date: no banner');
  assert.equal(after.confetti, 0, 'up to date: no confetti');
  await context.close();
}

{
  const { page, context } = await open({ outcome: 'deployed', reducedMotion: 'reduce' });
  const after = await state(page);
  assert.ok(after.banner, 'reduced motion: banner');
  assert.equal(after.confetti, 0, 'reduced motion: no confetti');
  await page.click('.deploy-celebration');
  assert.equal(await page.locator('.deploy-celebration').count(), 0, 'a tap dismisses the banner');
  await context.close();
}

{
  // The banner and confetti leave on their own.
  const { page, context } = await open({ outcome: 'deployed' });
  await page.waitForTimeout(5600);
  const later = await state(page);
  assert.equal(later.banner, null, 'banner gone after a few seconds');
  assert.equal(later.confetti, 0, 'confetti gone after a few seconds');
  await context.close();
}

await browser.close();
console.log('deploy_celebrate: ok');
