// The waiting-keys card settles as keys are confirmed, like Users › Waiting
// (docs/plans/private_access_card_settle/private_access_card_settle_one_shot.md).
// Renders the real partial through PHP; OpenPGP and fetch are stubbed as in approval_review.mjs.
import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';
import { chromium } from 'playwright-core';

const project = join(dirname(fileURLToPath(import.meta.url)), '..', '..');
const ids = Array.from({ length: 7 }, (_, i) => 'openpgp:' + String(i + 1).repeat(40));
const render = spawnSync('php', ['-r', `
  require ${JSON.stringify(join(project, 'autoload.php'))};
  $e = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES);
  $waitingKeys = array_map(static fn (string $id): array => ['identity_id' => $id, 'profile_slug' => str_replace(':', '-', $id)], json_decode($argv[1], true));
  $accountName = 'ilyag';
  include ${JSON.stringify(join(project, 'templates/partials/private_access_waiting.php'))};
`, JSON.stringify(ids)], { encoding: 'utf8' });
assert.equal(render.status, 0, render.stderr);
const card = render.stdout;
assert.match(card, /7 keys for ilyag want to read your messages/);

const userAccess = '<article class="card" id="private-access-waiting" data-private-access-waiting><h2>Waiting for private access</h2>'
  + '<p class="meta" data-role="private-access-card-feedback" role="status" hidden></p><ul class="private-access-list">'
  + '<li class="private-access-row" data-private-access-row data-target-identity-id="' + ids[0] + '"><a href="#">ilyag</a> <strong class="private-access-code">1111 1111 1111</strong> '
  + '<button type="button" data-action="confirm-private-access">Confirm</button><span class="meta" data-role="private-access-feedback" role="status" hidden></span></li></ul></article>';

const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
try {
  for (const viewport of [{ width: 1280, height: 800 }, { width: 390, height: 844 }]) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    await page.route('http://card.test/**', route => route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>Waiting card fixture</title><main>' + card + '</main>' }));
    await page.goto('http://card.test/');
    await page.evaluate(failFirst => {
      const fingerprint = 'a'.repeat(40);
      for (const [key, value] of Object.entries({ fingerprint, public_key: 'public', private_key: 'private' })) localStorage.setItem('forum_pki_' + key, value);
      window.openpgp = {
        async readKey() { return { getFingerprint: () => fingerprint }; },
        async readPrivateKey() { return { getFingerprint: () => fingerprint }; },
        async createMessage(input) { return input; },
        async sign() { return 'signature'; }
      };
      window.failNext = failFirst;
      window.fetch = async (url, options) => ({ headers: { get() { return ''; } }, text: async () => {
        const body = new URLSearchParams(options.body);
        if (url === '/api/prepare_private_access_confirmation') {
          return JSON.stringify({ status: 'ok', prepare_token: body.get('target_identity_id'), canonical_record: 'Target-Identity-ID: ' + body.get('target_identity_id') + '\n' });
        }
        if (body.get('prepare_token') === window.failNext) { window.failNext = ''; return JSON.stringify({ status: 'error', error: 'Repository is busy.' }); }
        return JSON.stringify({ status: 'ok', has_access: true });
      } });
    }, ids[1]);
    await page.addScriptTag({ path: join(project, 'public/assets/browser_signing.js') });
    await page.addScriptTag({ path: join(project, 'public/assets/private_access_confirm.js') });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));

    const heading = () => page.locator('[data-role="private-access-heading"]').textContent();
    const rows = () => page.locator('[data-private-access-row]').count();
    const status = page.locator('[data-role="private-access-card-feedback"]');
    const confirm = id => page.locator(`[data-target-identity-id="${id}"] [data-action="confirm-private-access"]`).click();

    // One confirmed key leaves at once; the heading counts down and names its code.
    await confirm(ids[0]);
    await page.waitForFunction(() => document.querySelectorAll('[data-private-access-row]').length === 6);
    assert.equal(await heading(), '6 keys for ilyag want to read your messages');
    assert.equal(await status.textContent(), 'Confirmed 1111 1111 1111.');
    assert.equal(await status.isVisible(), true);

    // A failed Confirm keeps its row with the error and Retry.
    await confirm(ids[1]);
    const failed = page.locator(`[data-target-identity-id="${ids[1]}"]`);
    await failed.getByText('Repository is busy.').waitFor();
    assert.equal(await failed.getByRole('button', { name: 'Retry' }).count(), 1);
    assert.equal(await rows(), 6);
    await failed.getByRole('button', { name: 'Retry' }).click();
    await page.waitForFunction(() => document.querySelectorAll('[data-private-access-row]').length === 5);

    // A key in the drawer: its count drops, and the drawer goes when empty.
    await page.locator('details summary').click();
    assert.equal(await page.locator('details summary').textContent(), '2 more');
    await confirm(ids[5]);
    await page.waitForFunction(() => document.querySelector('details summary').textContent === '1 more');
    await confirm(ids[6]);
    await page.waitForFunction(() => document.querySelector('details') === null);

    // The server's wording for one key, then only the status line.
    await confirm(ids[2]);
    await confirm(ids[3]);
    await page.waitForFunction(() => document.querySelectorAll('[data-private-access-row]').length === 1);
    assert.equal(await heading(), 'A new key for ilyag wants to read your messages');
    await confirm(ids[4]);
    await page.waitForFunction(() => document.querySelectorAll('[data-private-access-row]').length === 0);
    assert.equal(await page.locator('[data-role="private-access-heading"]').isVisible(), false);
    assert.equal(await page.locator('[data-role="private-access-hint"]').isVisible(), false);
    assert.equal(await status.isVisible(), true);
    assert.match(await status.textContent(), /^Confirmed 5555 5555 5555\.$/);

    // Tools › User Access: no count in its heading, but the row goes and the status shows.
    await page.evaluate(html => { document.querySelector('main').innerHTML = html; }, userAccess);
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    await confirm(ids[0]);
    await page.waitForFunction(() => document.querySelectorAll('[data-private-access-row]').length === 0);
    assert.equal(await page.locator('[data-role="private-access-card-feedback"]').textContent(), 'Confirmed 1111 1111 1111.');
    await context.close();
  }
} finally {
  await browser.close();
}
console.log('private_access_card: ok');
