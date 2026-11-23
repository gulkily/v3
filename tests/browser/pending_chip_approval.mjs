import assert from 'node:assert/strict';
import fs from 'node:fs';
import { chromium } from 'playwright-core';

// The Pending chip beside an author opens an in-page summary; an approved
// member approves from it, signing scripts load on first use, and every chip
// for that key leaves the page. Run: node tests/browser/pending_chip_approval.mjs
const css = fs.readFileSync('public/assets/site.css', 'utf8');
const ochreCss = fs.readFileSync('public/assets/theme-ochre.css', 'utf8');
const summaryScript = fs.readFileSync('public/assets/pending_user_summary.js', 'utf8');
const signingScript = fs.readFileSync('public/assets/browser_signing.js', 'utf8');
const chip = (slug, name) => `<a class="pending-chip" href="/profiles/${slug}" data-pending-profile="${slug}" data-pending-username="${name}"><span class="chip chip--pending">Pending</span></a>`;
const campaign_html = () => `<!doctype html><meta charset="utf-8"><html data-member="approved"><head><style>${css}</style>
<script>window.__forumAssetPaths = { openpgpLoader: '/assets/openpgp_loader.js', browserSigning: '/assets/browser_signing.js' };</script>
<script src="/assets/pending_user_summary.js" defer></script></head><body>
<div class="pledge-totals" data-pledge-totals><p class="pledge-totals__sum"><strong>20</strong> of 100 coffees pledged</p></div>
<article class="card post-card pledge-row" data-post-id="p1"><p class="meta" data-post-byline id="one">Oct 6 ${chip('mouse', 'mouse')}</p></article>
</body></html>`;
const page_html = (member) => `<!doctype html><meta charset="utf-8"><html data-theme="ochre"${member ? ' data-member="approved"' : ''}><head><style>${css}
${ochreCss}</style>
<script>window.__forumAssetPaths = { openpgpLoader: '/assets/openpgp_loader.js', browserSigning: '/assets/browser_signing.js' };</script>
<script src="/assets/pending_user_summary.js" defer></script></head><body>
<p class="meta thread-row__meta" id="one"><a href="/profiles/mouse">mouse</a> · Oct 6 ${chip('mouse', 'mouse')}</p>
<p class="meta thread-row__meta" id="two"><a href="/profiles/mouse">mouse</a> · Oct 7 ${chip('mouse', 'mouse')}</p>
<p class="meta thread-row__meta" id="three"><a href="/profiles/cat">cat</a> · Oct 8 ${chip('cat', 'cat')}</p>
</body></html>`;

const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
try {
  for (const viewport of [{ width: 1280, height: 800 }, { width: 375, height: 667 }]) {
    for (const member of [true, false]) {
      const context = await browser.newContext({ viewport });
      const page = await context.newPage();
      const calls = { profile: 0, prepare: 0, create: 0, signingLoads: 0, pageLoads: 0 };
      let failNextSign = true;
      await context.route('http://pending.test/**', async route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/') { calls.pageLoads++; return route.fulfill({ contentType: 'text/html', body: page_html(member) }); }
        if (url.pathname === '/assets/pending_user_summary.js') return route.fulfill({ contentType: 'text/javascript', body: summaryScript });
        if (url.pathname === '/assets/openpgp_loader.js') return route.fulfill({ contentType: 'text/javascript', body: `
          window.openpgp = {
            async readKey() { return { getFingerprint: () => '${'a'.repeat(40)}' }; },
            async readPrivateKey() { return { getFingerprint: () => '${'a'.repeat(40)}' }; },
            async createMessage(input) { return input; },
            async sign() { if (window.failNextSign) { window.failNextSign = false; throw new Error('Signing failed. Try again.'); } return 'signature'; }
          };` });
        if (url.pathname === '/assets/browser_signing.js') { calls.signingLoads++; return route.fulfill({ contentType: 'text/javascript', body: signingScript }); }
        if (url.pathname === '/api/get_profile') {
          calls.profile++;
          const slug = url.searchParams.get('profile_slug');
          return route.fulfill({ contentType: 'text/plain', body: `Profile-Slug: ${slug}\nUsername: ${slug}\nApproved: no\nPosts: 3\nThreads: 1\n`
            + `Recent-Post: 2026-11-12T10:00:00Z\tt1\tp9\tA reply about vending machines\n`
            + `Recent-Post: 2026-10-01T10:00:00Z\tt1\tt1\tHello everyone\n` });
        }
        if (url.pathname === '/api/prepare_approval') {
          calls.prepare++;
          return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ status: 'ok', prepare_token: 'token', post_id: 'approval', canonical_record: 'Author-Identity-ID: openpgp:' + 'a'.repeat(40) + '\n', approval_review: { label: 'Approve new member mouse', consequence: 'You vouch for them.', target_identity_id: 'openpgp:' + 'b'.repeat(40) } }) });
        }
        if (url.pathname === '/api/create_prepared_approval') { calls.create++; return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ status: 'ok' }) }); }
        return route.fulfill({ contentType: 'text/plain', body: 'status=ok\n' });
      });
      await page.addInitScript((fp) => {
        for (const [key, value] of Object.entries({ fingerprint: fp, public_key: 'public', private_key: 'private', published_fingerprint: fp })) localStorage.setItem('forum_pki_' + key, value);
        window.failNextSign = true;
      }, 'a'.repeat(40));
      page.on('dialog', () => assert.fail('no browser confirm() for an in-page review'));
      page.on('pageerror', error => assert.fail(error.message));
      await page.goto('http://pending.test/');

      await page.locator('#one .pending-chip').click();
      const dialog = page.locator('dialog.pending-user-dialog');
      await dialog.waitFor({ state: 'visible' });
      assert.equal(await dialog.evaluate(element => getComputedStyle(element).backgroundColor), 'rgb(246, 246, 239)', 'Ochre Pending summary covers the page behind it');
      await page.waitForFunction(() => document.querySelector('[data-role="counts"]').textContent === '1 thread · 3 posts');
      assert.equal(await dialog.locator('h2').textContent(), 'mouse');
      assert.equal(await dialog.getByRole('link', { name: 'Profile' }).getAttribute('href'), '/profiles/mouse');
      const recent = dialog.locator('[data-role="recent"] li');
      assert.equal(await recent.count(), 2);
      assert.equal(await recent.nth(0).locator('a').getAttribute('href'), '/posts/p9');
      assert.equal(await recent.nth(1).locator('a').getAttribute('href'), '/threads/t1');
      assert.equal(await recent.nth(1).locator('a').textContent(), 'Hello everyone');
      assert.equal(await recent.nth(1).locator('.meta').textContent(), 'Oct 1');
      const box = await dialog.boundingBox();
      const chipBox = await page.locator('#one .pending-chip').boundingBox();
      assert.ok(box.x >= 0 && box.x + box.width <= viewport.width, 'dialog fits the viewport');
      assert.ok(Math.abs(box.y - (chipBox.y + chipBox.height + 6)) <= 2, 'dialog opens just below the chip');
      assert.ok(box.x <= chipBox.x + 1, 'dialog starts at or left of the chip');
      await page.screenshot({ path: `/tmp/pending-chip-${viewport.width}-${member ? 'member' : 'visitor'}.png` });

      const approve = dialog.getByRole('button', { name: 'Approve' });
      if (!member) {
        assert.equal(await approve.isVisible(), false, 'non-members see no Approve');
        await dialog.getByRole('button', { name: 'Close' }).click();
        assert.equal(await dialog.isVisible(), false);
        // A chip near the bottom of the window opens its summary above it.
        await page.evaluate(() => { document.body.style.paddingTop = (window.innerHeight - 60) + 'px'; });
        await page.locator('#one .pending-chip').click();
        await dialog.waitFor({ state: 'visible' });
        await page.waitForFunction(() => document.querySelectorAll('[data-role="recent"] li').length === 2);
        await page.waitForTimeout(50);
        const low = await dialog.boundingBox();
        const lowChip = await page.locator('#one .pending-chip').boundingBox();
        assert.ok(low.y + low.height <= lowChip.y, 'dialog sits above a chip near the bottom');
        await page.keyboard.press('Escape');
        assert.equal(await page.locator('[data-pending-profile]').count(), 3);
        await context.close();
        continue;
      }

      assert.equal(calls.signingLoads, 0, 'signing loads only on first use');
      await approve.click();
      await page.waitForFunction(() => document.querySelector('[data-role="error"]').textContent.includes('Signing failed'));
      assert.equal(await approve.isEnabled(), true, 'retry stays available');
      assert.equal(calls.create, 0);
      assert.equal(await page.locator('[data-pending-profile]').count(), 3);

      await approve.click();
      await dialog.waitFor({ state: 'hidden' });
      assert.equal(calls.signingLoads, 1);
      assert.equal(calls.create, 1);
      assert.equal(await page.locator('[data-pending-profile="mouse"]').count(), 0, 'every chip for the approved key goes');
      assert.equal(await page.locator('[data-pending-profile="cat"]').count(), 1, 'other keys keep theirs');

      // A modified click (new tab) is left to the browser.
      const prevented = await page.evaluate(() => {
        const event = new MouseEvent('click', { bubbles: true, cancelable: true, ctrlKey: true, metaKey: true });
        const link = document.querySelector('#three .pending-chip');
        link.addEventListener('click', e => e.preventDefault(), { once: true, capture: false });
        document.addEventListener('click', e => { window.seenPrevented = e.defaultPrevented; }, { once: true, capture: true });
        link.dispatchEvent(event);
        return window.seenPrevented;
      });
      assert.equal(prevented, false);
      assert.equal(await dialog.isVisible(), false, 'modified click opens no summary');
      assert.equal(calls.pageLoads, 1, 'approving on a page without a campaign does not reload');
      await context.close();
    }
  }
  // On a campaign thread, approving a key with a reply on the page reloads it, so the totals count them.
  {
    const context = await browser.newContext({ viewport: { width: 375, height: 667 } });
    let loads = 0;
    await context.route('http://pending.test/**', async route => {
      const url = new URL(route.request().url());
      if (url.pathname === '/') { loads++; return route.fulfill({ contentType: 'text/html', body: campaign_html() }); }
      if (url.pathname === '/assets/pending_user_summary.js') return route.fulfill({ contentType: 'text/javascript', body: summaryScript });
      if (url.pathname === '/assets/openpgp_loader.js') return route.fulfill({ contentType: 'text/javascript', body: `
        window.openpgp = { async readKey() { return { getFingerprint: () => '${'a'.repeat(40)}' }; }, async readPrivateKey() { return { getFingerprint: () => '${'a'.repeat(40)}' }; }, async createMessage(input) { return input; }, async sign() { return 'signature'; } };` });
      if (url.pathname === '/assets/browser_signing.js') return route.fulfill({ contentType: 'text/javascript', body: signingScript });
      if (url.pathname === '/api/get_profile') return route.fulfill({ contentType: 'text/plain', body: 'Username: mouse\nApproved: no\nPosts: 1\nThreads: 0\n' });
      if (url.pathname === '/api/prepare_approval') return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ status: 'ok', prepare_token: 'token', post_id: 'approval', canonical_record: 'Author-Identity-ID: openpgp:' + 'a'.repeat(40) + '\n', approval_review: { label: 'Approve new member mouse', consequence: 'You vouch for them.', target_identity_id: 'openpgp:' + 'b'.repeat(40) } }) });
      if (url.pathname === '/api/create_prepared_approval') return route.fulfill({ contentType: 'application/json', body: JSON.stringify({ status: 'ok' }) });
      return route.fulfill({ contentType: 'text/plain', body: 'status=ok\n' });
    });
    const page = await context.newPage();
    await page.addInitScript((fp) => {
      for (const [key, value] of Object.entries({ fingerprint: fp, public_key: 'public', private_key: 'private', published_fingerprint: fp })) localStorage.setItem('forum_pki_' + key, value);
    }, 'a'.repeat(40));
    page.on('pageerror', error => assert.fail(error.message));
    await page.goto('http://pending.test/?campaign');
    await page.locator('#one .pending-chip').click();
    const dialog = page.locator('dialog.pending-user-dialog');
    await page.waitForFunction(() => document.querySelector('[data-role="counts"]').textContent.includes('post'));
    await Promise.all([page.waitForEvent('load'), dialog.getByRole('button', { name: 'Approve' }).click()]);
    assert.equal(loads, 2, 'the campaign thread reloads after approval');
    await context.close();
  }

  // Themes that give `.card` position: relative must not make the dialog
  // non-fixed; that put it at the top of the document and opening scrolled
  // the page there.
  for (const theme of ['bubblegum', 'sticker']) {
    const themeCss = fs.readFileSync(`public/assets/theme-${theme}.css`, 'utf8');
    const context = await browser.newContext({ viewport: { width: 1280, height: 800 } });
    await context.route('http://pending.test/**', async route => {
      const url = new URL(route.request().url());
      if (url.pathname === '/') return route.fulfill({ contentType: 'text/html', body: page_html(false).replace('<html', `<html data-theme="${theme}"`).replace('</style>', `</style><style>${themeCss}</style>`).replace('<body>', '<body><div style="height:3000px"></div>') });
      if (url.pathname === '/assets/pending_user_summary.js') return route.fulfill({ contentType: 'text/javascript', body: summaryScript });
      if (url.pathname === '/api/get_profile') return route.fulfill({ contentType: 'text/plain', body: 'Username: mouse\nPosts: 1\nThreads: 1\n' });
      return route.fulfill({ status: 404, body: '' });
    });
    const page = await context.newPage();
    await page.goto('http://pending.test/');
    await page.locator('#two .pending-chip').evaluate(el => el.scrollIntoView({ block: 'center' }));
    const before = await page.evaluate(() => scrollY);
    assert.ok(before > 2000, 'page is scrolled before opening');
    await page.locator('#two .pending-chip').click();
    const dialog = page.locator('dialog.pending-user-dialog');
    await page.waitForFunction(() => document.querySelector('[data-role="counts"]').textContent.includes('post'));
    assert.equal(await page.evaluate(() => scrollY), before, `${theme}: opening does not scroll the page`);
    assert.equal(await dialog.evaluate(el => getComputedStyle(el).position), 'fixed', `${theme}: dialog stays fixed`);
    const box = await dialog.boundingBox();
    const chipBox = await page.locator('#two .pending-chip').boundingBox();
    const below = Math.abs(box.y - (chipBox.y + chipBox.height + 6)) <= 2;
    const above = Math.abs(box.y + box.height - (chipBox.y - 6)) <= 2;
    assert.ok(below || above, `${theme}: dialog opens right beside the chip: ${JSON.stringify({ box, chipBox })}`);
    await context.close();
  }

  console.log('PASS pending chip: summary with recent posts beside the chip, member-only Approve, lazy signing, failure and retry, chips removed, a campaign thread reloads, modified click left to the browser, no scroll on open in themes that position cards; desktop and phone');
} finally { await browser.close(); }
