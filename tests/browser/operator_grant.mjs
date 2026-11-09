import assert from 'node:assert/strict';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright-core';

// Renders the real partial, then drives public/assets/operator_grant.js with stubbed signing and network.
const operator = 'a'.repeat(40);
const target = 'openpgp:' + 'b'.repeat(40);
const partial = execFileSync('php', ['-r', `
  $e = static fn ($v) => htmlspecialchars((string) $v, ENT_QUOTES);
  $operatorGrantActors = ['${operator}'];
  $profile = ['identity_id' => '${target}'];
  include 'templates/partials/operator_grant.php';
`]).toString();
assert.match(partial, /data-operator-grant data-target-identity-id="openpgp:b{40}"/);
const empty = execFileSync('php', ['-r', `$e = static fn ($v) => $v; $operatorGrantActors = []; $profile = []; include 'templates/partials/operator_grant.php';`]).toString();
assert.equal(empty.trim(), '', 'No markup when nobody may grant');

const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
try {
  for (const viewport of [{ width: 1280, height: 800 }, { width: 390, height: 844 }]) {
    for (const fingerprint of ['c'.repeat(40), operator]) {
      const context = await browser.newContext({ viewport });
      const page = await context.newPage();
      await page.addInitScript(([fp]) => {
        for (const [key, value] of Object.entries({ fingerprint: fp, public_key: 'public', private_key: 'private' })) localStorage.setItem('forum_pki_' + key, value);
      }, [fingerprint]);
      await page.route('http://grant.test/**', route => route.fulfill({ contentType: 'text/html', body: '<!doctype html><meta charset="utf-8"><title>Grant fixture</title><body>' + partial + '<script src="/operator_grant.js"></script></body>' }));
      await page.route('http://grant.test/operator_grant.js', route => route.fulfill({ contentType: 'text/javascript', path: 'public/assets/operator_grant.js' }));
      await page.goto('http://grant.test/');
      const visible = await page.locator('[data-operator-grant-review]').isVisible();
      if (fingerprint !== operator) {
        assert.equal(visible, false, 'Hidden for a key that is not an operator');
        await context.close();
        continue;
      }
      assert.equal(visible, true, 'Shown for an operator key');
      const reports = [];
      await page.exposeFunction('report', entry => { reports.push(entry); });
      await page.evaluate(() => {
        window.failFinalize = true;
        window.ForumBrowserSigning = { async signCanonicalRecord(record, actor) { window.report('sign ' + actor); return 'signature'; } };
        window.fetch = async (url, options) => {
          const body = JSON.parse(options.body);
          window.report(url + ' ' + JSON.stringify(body));
          if (url === '/api/prepare_operator_grant') {
            return { ok: true, json: async () => ({ status: 'ok', prepare_token: 't', record_id: 'operator-grant-1', record_path: 'records/operator-grants/operator-grant-1.txt', canonical_record: 'Record-ID: operator-grant-1\n', target_username: 'ilyag', echo: body }) };
          }
          if (window.failFinalize) { window.failFinalize = false; return { ok: false, json: async () => ({ status: 'error', error: 'Network hiccup.' }) }; }
          window.finalized = body;
          return { ok: true, json: async () => ({ status: 'ok' }) };
        };
      });
      await page.getByRole('button', { name: 'Make root…' }).click();
      await page.waitForFunction(() => !document.querySelector('[data-operator-grant-confirm]').hidden);
      assert.match(await page.locator('[data-operator-grant-question]').textContent(), /^Make ilyag \(bbbbbbbb…\) a root operator\?/);
      await page.getByRole('button', { name: 'Cancel' }).click();
      assert.equal(await page.locator('[data-operator-grant-confirm]').isHidden(), true);
      await page.getByRole('button', { name: 'Make root…' }).click();
      await page.waitForFunction(() => !document.querySelector('[data-operator-grant-confirm]').hidden);
      await page.getByRole('button', { name: 'Make root', exact: true }).click();
      await page.waitForFunction(() => !document.querySelector('[data-operator-grant-feedback]').hidden);
      assert.match(await page.locator('[data-operator-grant-feedback]').textContent(), /Network hiccup\. Reload the page/);
      const navigation = page.waitForEvent('framenavigated');
      await page.getByRole('button', { name: 'Make root', exact: true }).click();
      await navigation;
      const signed = JSON.stringify({ prepare_token: 't', record_id: 'operator-grant-1', record_path: 'records/operator-grants/operator-grant-1.txt', canonical_record: 'Record-ID: operator-grant-1\n', detached_signature: 'signature' });
      assert.deepEqual(reports, [
        '/api/prepare_operator_grant ' + JSON.stringify({ target_identity_id: target }),
        '/api/prepare_operator_grant ' + JSON.stringify({ target_identity_id: target }),
        'sign openpgp:' + operator,
        '/api/finalize_operator_grant ' + signed,
        '/api/finalize_operator_grant ' + signed,
      ], 'One signature; the retry resends the same signed request; then the page reloads');
      await context.close();
    }
  }
  console.log('PASS operator grant: hidden for non-operators and with no actors; shown before paint for operators; confirmation names the username; cancel; a failed finalize keeps the signed request and the retry reloads; desktop and mobile');
} finally { await browser.close(); }
