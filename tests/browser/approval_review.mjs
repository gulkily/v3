import assert from 'node:assert/strict';
import { chromium } from 'playwright-core';
const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
try {
  for (const viewport of [{ width: 1280, height: 800 }, { width: 390, height: 844 }]) {
    const context = await browser.newContext({ viewport });
    const page = await context.newPage();
    await page.route('http://approval.test/**', route => route.fulfill({ contentType: 'text/html', body: '<!doctype html><title>Approval review fixture</title>' }));
    await page.goto('http://approval.test/');
    await page.evaluate(() => {
      const fingerprint = 'a'.repeat(40);
      for (const [key, value] of Object.entries({ fingerprint, public_key: 'public', private_key: 'private', published_fingerprint: fingerprint })) localStorage.setItem('forum_pki_' + key, value);
      window.openpgp = {
        async readKey() { return { getFingerprint: () => fingerprint }; },
        async readPrivateKey() { return { getFingerprint: () => fingerprint }; },
        async createMessage(input) { return input; },
        async sign() { window.signed++; return 'signature'; }
      };
      window.signed = 0;
      window.finalized = 0;
      window.review = { label: 'Grant this key access as bob', consequence: 'bob already has 2 approved keys. This key will be able to act as bob.', target_identity_id: 'openpgp:' + 'b'.repeat(40) };
      window.fetch = async url => ({ headers: { get() { return ''; } }, text: async () => {
        if (url === '/api/prepare_approval') return JSON.stringify({ status: 'ok', prepare_token: 'token', post_id: 'approval', canonical_record: 'Author-Identity-ID: openpgp:' + fingerprint + '\n', approval_review: window.review });
        if (url === '/api/create_prepared_approval') { window.finalized++; return JSON.stringify({ status: 'ok' }); }
        return 'status=ok\n';
      } });
    });
    await page.addScriptTag({ path: 'public/assets/browser_signing.js' });
    let dialogCount = 0;
    page.on('dialog', async dialog => {
      dialogCount++;
      assert.match(dialog.message(), /bob already has 2 approved keys/);
      assert.match(dialog.message(), /Account key: openpgp:bbbb/);
      if (dialogCount === 1) await dialog.dismiss(); else await dialog.accept();
    });
    const cancel = await page.evaluate(() => window.ForumBrowserSigning.submitSignedApproval('target'));
    assert.equal(cancel.ok, false);
    assert.equal(await page.evaluate(() => window.signed + window.finalized), 0);
    const accepted = await page.evaluate(() => window.ForumBrowserSigning.submitSignedApproval('target'));
    assert.equal(accepted.ok, true);
    assert.equal(await page.evaluate(() => window.signed), 1);
    assert.equal(await page.evaluate(() => window.finalized), 1);
    await page.evaluate(() => { window.review = null; });
    assert.equal((await page.evaluate(() => window.ForumBrowserSigning.submitSignedApproval('target'))).ok, false);
    assert.equal(await page.evaluate(() => window.signed), 1);
    await page.evaluate(() => {
      window.review = { label: 'Approve new member bob', consequence: 'Approve this key.', target_identity_id: 'openpgp:' + 'b'.repeat(40) };
      document.body.innerHTML = '<section data-pending-approvals-root><p data-role="pending-approvals-feedback" hidden></p><table><tbody data-role="pending-approvals-body"><tr data-username="bob"><td><button data-action="approve-user" data-profile-slug="target">Approve</button></td></tr></tbody></table></section>';
    });
    await page.addScriptTag({ path: 'public/assets/pending_approvals.js' });
    await page.evaluate(() => document.dispatchEvent(new Event('DOMContentLoaded')));
    await page.getByRole('button', { name: 'Approve', exact: true }).click();
    await page.waitForFunction(() => document.querySelector('[data-role="pending-approvals-feedback"]').textContent === 'Approved user bob.');
    assert.equal(dialogCount, 2);
    assert.equal(await page.evaluate(() => window.signed), 2);
    assert.equal(await page.evaluate(() => window.finalized), 2);
    await context.close();
  }
  console.log('PASS approval review: desktop/mobile profile confirmation, cancel, retry, missing review; pending directory approves without confirmation');
} finally { await browser.close(); }
