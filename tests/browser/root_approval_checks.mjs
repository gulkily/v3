import assert from 'node:assert/strict';
import { join } from 'node:path';

export async function checkRootApprovalPages({ page, context, base, rootKey, username }) {
  const evidencePath = '/approvals/root/' + rootKey;
  const rawPath = '/source/current/records/approval-seeds/' + rootKey + '.txt';
  for (const history of ['/user/' + username, '/profiles/' + rootKey, '/forte/user/' + username, '/forte/profiles/' + rootKey]) {
    await page.goto(base + history);
    const profilePage = history.includes('/profiles/');
    assert.equal(await page.locator('#account-keys .account-key-history-entry').count(), profilePage ? 1 : 2);
    assert.equal(await page.locator('#account-keys time').count(), profilePage ? 1 : 2);
    if (profilePage) {
      await page.getByRole('heading', { name: 'Key approval', exact: true }).waitFor();
      assert.match(await page.locator('#account-keys').innerText(), /Committed:/);
      assert.equal(await page.getByRole('link', { name: 'All account keys', exact: true }).count(), 1);
    } else {
      assert.equal(await page.locator('#account-keys details[open]').count(), 0);
    }
    await page.locator(`a[href="${evidencePath}"]`).click();
    await page.getByRole('heading', { name: 'Root approval', exact: true }).waitFor();
    assert.equal(new URL(page.url()).pathname, evidencePath);
    assert.match(await page.locator('main').innerText(), /isolated invitation browser fixture/);
  }
  for (const theme of ['light', 'dark', 'console', 'chicago', 'word97']) {
    await page.evaluate(theme => localStorage.setItem(window.forumBrowserRuntime.themeStorageKey, theme), theme);
    for (const width of [1280, 390]) {
      await page.setViewportSize({ width, height: 900 });
      await page.goto(base + evidencePath);
      assert.equal(await page.locator('html').getAttribute('data-theme'), theme);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth <= innerWidth), true);
      assert.equal(await page.getByRole('link', { name: rootKey, exact: true }).count(), 1);
      assert.equal(await page.getByRole('link', { name: 'Raw seed record', exact: true }).getAttribute('href'), rawPath);
      assert.match(await page.locator('main').innerText(), /Authorizer: not recorded\. Acceptance time: not recorded\./);
      assert.match(await page.locator('main').innerText(), /Repository commit dates describe file history/);
      assert.ok(await page.locator('main a[href^="/source/blob/"]').count() > 0);
      const gap = await page.locator('main .card').nth(1).evaluate(el => el.getBoundingClientRect().top - el.previousElementSibling.getBoundingClientRect().bottom);
      assert.ok(gap > 0);
      if (process.env.ROOT_APPROVAL_SCREENSHOT_DIR) await page.screenshot({ path: join(process.env.ROOT_APPROVAL_SCREENSHOT_DIR, `root-${theme}-${width}.png`), fullPage: true });
    }
  }
  const raw = await context.request.get(base + rawPath);
  assert.equal(raw.status(), 200);
  assert.match(await raw.text(), /Approved-Identity-ID: openpgp:/);
  const commitHref = await page.locator('main a[href^="/source/commits/"]').first().getAttribute('href');
  const commit = await context.request.get(base + commitHref);
  assert.equal(commit.status(), 200);
  assert.ok((await commit.text()).includes('records/approval-seeds/' + rootKey + '.txt'));
  const missing = await context.request.get(base + '/approvals/root/openpgp-' + 'a'.repeat(40));
  assert.equal(missing.status(), 404);
  assert.match(await missing.text(), /Root approval unavailable/);
  console.log('PASS root approval pages: four history entry points, five themes desktop/phone, accessible key links, wrapping/spacing, raw and committed evidence, unknown historical facts, missing record');
}
