import assert from 'node:assert/strict';
import { mkdtemp, readFile, readdir, rm } from 'node:fs/promises';
import { readFileSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { join, resolve } from 'node:path';
import { runInThisContext } from 'node:vm';
import { spawn, spawnSync } from 'node:child_process';
import { createServer } from 'node:net';
import { once } from 'node:events';
const { chromium } = await import(process.env.PLAYWRIGHT_MODULE || 'playwright-core');
const project = resolve(new URL('../..', import.meta.url).pathname);
runInThisContext(readFileSync(join(project, 'public/assets/openpgp.min.js'), 'utf8'));
const keys = await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'reaction-browser' }], format: 'armored' });
keys.priorPublicKey = (await globalThis.openpgp.generateKey({ type: 'ecc', curve: 'ed25519', userIDs: [{ name: 'earlier-like' }], format: 'armored' })).publicKey;
const fingerprint = (await globalThis.openpgp.readKey({ armoredKey: keys.publicKey })).getFingerprint().toUpperCase();
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', headless: true, args: ['--no-sandbox'] });
try {
  for (const mode of ['classic', 'forte', 'qdb']) {
    const root = await mkdtemp(join(tmpdir(), 'signed-reactions-browser-'));
    const env = { ...process.env, FORUM_SITE_ID: mode === 'qdb' ? 'qdb' : 'zenmemes',
      FORUM_SECRETS_PATH: join(root, 'absent-secrets.php'), SIGNED_REACTION_TEST_ROOT: root,
      VISITOR_STATISTICS_DATABASE_PATH: join(root, 'visitors.sqlite3') };
    const seed = spawnSync('php', [join(project, 'tests/Support/signed_reaction_browser_fixture.php'), root], { input: JSON.stringify(keys), encoding: 'utf8', env });
    assert.equal(seed.status, 0, seed.stderr);
    const { identity } = JSON.parse(seed.stdout);
    const socket = createServer().listen(0, '127.0.0.1'); await once(socket, 'listening');
    const port = socket.address().port; await new Promise(resolve => socket.close(resolve));
    const base = `http://127.0.0.1:${port}`;
    const server = spawn('php', ['-d', `session.save_path=${root}/sessions`, '-S', `127.0.0.1:${port}`, join(project, 'tests/Support/signed_reaction_browser_router.php')], { env, stdio: ['ignore', 'ignore', 'pipe'] });
    let serverLog = ''; server.stderr.on('data', data => { serverLog += data; });
    const context = await browser.newContext({ serviceWorkers: 'block' });
    try {
      for (let i = 0; i < 60; i++) { try { await fetch(base + '/api/version'); break; } catch { await new Promise(r => setTimeout(r, 100)); } }
      await context.addCookies([{ name: 'identity_hint', value: identity, url: base }]);
      await context.addInitScript(({ keys, fingerprint, skew }) => {
        localStorage.setItem('forum_pki_private_key', keys.privateKey);
        localStorage.setItem('forum_pki_public_key', keys.publicKey);
        localStorage.setItem('forum_pki_fingerprint', fingerprint);
        localStorage.setItem('forum_pki_published_fingerprint', fingerprint);
        localStorage.setItem('forum_pki_username', 'reaction-browser');
        if (!localStorage.getItem('forum-zenmemes-theme')) localStorage.setItem('forum-zenmemes-theme', 'light');
        if (skew) {
          const Original = Date;
          window.Date = class extends Original { toISOString() { return new Original(this.getTime() - 3600000).toISOString(); } };
        }
      }, { keys, fingerprint, skew: mode === 'forte' });
      const page = await context.newPage();
      page.on('dialog', dialog => dialog.dismiss());
      const responses = [];
      page.on('response', response => { if (/\/api\/apply_(thread|post)_tag$/.test(response.url())) responses.push(response); });
      async function vote(selector, index = 0) {
        const before = responses.length;
        const button = page.locator(selector).nth(index);
        await button.locator("xpath=ancestor::article[1]").hover();
        await button.click();
        await page.waitForFunction(({ selector, index }) => document.querySelectorAll(selector)[index]?.getAttribute('aria-pressed') === 'true', { selector, index });
        // Optimistic aria-pressed can precede completion; wait for saved feedback/response.
        for (let i = 0; i < 200 && responses.length < before + (mode === 'forte' ? 2 : 1); i++) await page.waitForTimeout(50);
        const batch = responses.slice(before);
        assert.equal(batch.length, mode === 'forte' ? 2 : 1);
        const final = batch.at(-1);
        assert.match(await final.text(), /status=ok/);
        const fields = new URLSearchParams(final.request().postData());
        assert.ok(fields.get('detached_signature')?.startsWith('-----BEGIN PGP SIGNATURE-----'));
        const record = fields.get('canonical_record');
        const id = /^Record-ID: (.+)$/m.exec(record)[1];
        const family = id.startsWith('thread-label-') ? 'thread-labels' : 'post-reactions';
        assert.match(record, new RegExp(`\nTarget-Kind: ${family === 'thread-labels' ? 'thread' : 'post'}\nTarget-ID: `));
        assert.equal(await readFile(join(root, 'repository/records', family, id + '.txt'), 'utf8'), record);
        assert.equal(await readFile(join(root, 'repository/records', family, id + '.txt.asc'), 'utf8'), fields.get('detached_signature'));
        const source = await context.request.get(base + `/source/current/records/${family}/${id}.txt`);
        assert.equal(await source.text(), record);
        assert.equal(source.headers()['x-forum-signature-status'], 'verified');
        return id;
      }
      if (mode === 'qdb') {
        await page.goto(base + '/1');
        await vote('[data-action="apply-thread-tag"]', 0);
        await vote('[data-action="apply-post-tag"][data-tag="flag"]');
        await page.goto(base + '/2');
        await vote('[data-action="apply-thread-tag"]', 1);
      } else {
        await page.goto(base + (mode === 'forte' ? '/forte?selected=root-001' : '/threads/root-001'));
        if (mode === 'classic') {
          await page.evaluate(async () => {
            await window.ForumLazyComposeSigning?.load();
            window.savedReactionSigner = window.ForumBrowserSigning.signCanonicalRecord;
            window.ForumBrowserSigning.signCanonicalRecord = async () => { throw new Error('Injected signing failure'); };
          });
          const button = page.locator('[data-action="apply-thread-tag"][data-tag="like"]').first();
          await button.locator('xpath=ancestor::article[1]').hover(); await button.click();
          await page.waitForFunction(() => document.body.textContent.includes('Injected signing failure'));
          assert.equal(responses.length, 0, 'signing failure must not submit unsigned');
          await page.evaluate(() => { window.ForumBrowserSigning.signCanonicalRecord = window.savedReactionSigner; });
        }
        const likeId = await vote('[data-action="apply-thread-tag"][data-tag="like"]');
        const query = spawnSync('php', ['-r', '$p=new PDO("sqlite:".$argv[1]); echo json_encode($p->query("SELECT id, source_path, source_commit_sha FROM activity WHERE kind=\'thread_label_add\' AND label LIKE \'%like%\'")->fetchAll(PDO::FETCH_ASSOC));', join(root, 'read.sqlite3')], { encoding: 'utf8' });
        assert.equal(query.status, 0, query.stderr);
        const likeRows = JSON.parse(query.stdout);
        assert.equal(likeRows.length, 2, 'existing effective like must not hide the new contribution');
        const own = likeRows.find(row => row.source_path.endsWith(likeId + '.txt'));
        assert.ok(own);
        assert.notEqual(likeRows[0].source_commit_sha, likeRows[1].source_commit_sha);
        const rss = await context.request.get(base + '/activity/?view=all&format=rss');
        assert.equal(((await rss.text()).match(/<title>Labels added: like<\/title>/g) || []).length, 2);
        for (const width of [390, 1280]) {
          await page.setViewportSize({ width, height: 900 });
          for (const theme of ['light', 'word97', 'chicago', 'midnight']) {
            await page.evaluate(theme => localStorage.setItem('forum-zenmemes-theme', theme), theme);
            const activityPage = await context.newPage();
            await activityPage.setViewportSize({ width, height: 900 });
            await activityPage.goto(base + (mode === 'forte' ? '/forte/activity/?selected=' + own.id : '/activity/'));
            assert.equal(await activityPage.evaluate(() => innerWidth), width);
            const themeAttribute = mode === 'forte' ? 'data-forte-scheme' : 'data-resolved-theme';
            const expectedTheme = mode === 'forte' ? (theme === 'midnight' ? 'dark' : 'light') : theme;
            assert.equal(await activityPage.locator('html').getAttribute(themeAttribute), expectedTheme);
            if (mode === 'forte') {
              await activityPage.locator('[data-paned-activity-id="' + own.id + '"]').click();
              await activityPage.locator('[data-paned-activity-content-item-id="' + own.id + '"]:visible').waitFor();
            }
            const link = activityPage.locator('a[href*="' + likeId + '.txt"]').first();
            await link.waitFor({ state: 'attached' });
            // Source evidence lives in expandable manifests in both views.
            const details = link.locator('xpath=ancestor::details');
            if (await details.count()) await details.evaluateAll(nodes => nodes.forEach(node => { node.open = true; }));
            await link.waitFor({ state: 'visible' });
            assert.ok(await activityPage.locator('a[href*="' + own.source_commit_sha + '"]').count());
            assert.ok(await activityPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1), `${mode} ${theme} ${width}: page overflow`);
            if (theme === 'midnight') await activityPage.screenshot({ path: `/tmp/thread-like-${mode}-${width}.png`, fullPage: true });
            await activityPage.close();
          }
          const emptyPage = await context.newPage();
          await emptyPage.setViewportSize({ width, height: 900 });
          await emptyPage.goto(base + (mode === 'forte' ? '/forte/activity/?view=approval' : '/activity/?view=approval'));
          if (mode === 'forte') {
            assert.equal(await emptyPage.locator('[data-paned-activity-id]:visible').count(), 0);
            assert.equal(await emptyPage.locator('[data-paned-activity-status-count]').textContent(), '0 items');
          } else {
            assert.equal(await emptyPage.locator('article.card').count(), 1);
          }
          assert.ok(await emptyPage.evaluate(() => document.documentElement.scrollWidth <= innerWidth + 1));
          await emptyPage.close();
        }
        console.log(`PASS ${mode}: later signed like in Activity, own source/commit, RSS, phone/desktop and four themes`);
        await page.goto(base + (mode === 'forte' ? '/forte?selected=root-001' : '/threads/root-001'));
        await vote('[data-action="apply-post-tag"][data-post-id="root-001"][data-tag="flag"]');
        await vote('[data-action="apply-post-tag"][data-post-id="reply-001"][data-tag="like"]');
        await vote('[data-action="apply-post-tag"][data-post-id="reply-001"][data-tag="flag"]');
      }
      const count = responses.length;
      await page.reload();
      await page.waitForTimeout(150);
      assert.equal(responses.length, count, 'reload must not repeat writes');
      console.log(`PASS ${mode}: signed buttons, ${mode === 'forte' ? 'clock fallback' : 'single submission'}, exact source bytes, verified status, reload`);
      if (mode === 'classic') {
        const guest = await browser.newContext({ serviceWorkers: 'block' });
        try {
          const guestPage = await guest.newPage();
          guestPage.on('dialog', dialog => dialog.accept('guest'));
          await guestPage.goto(base + '/threads/root-001');
          const button = guestPage.locator('[data-action="apply-thread-tag"][data-tag="like"]').first();
          await button.locator('xpath=ancestor::article[1]').hover();
          const saved = guestPage.waitForResponse(r => r.url().endsWith('/api/apply_thread_tag'));
          await button.click();
          const response = await saved;
          assert.match(await response.text(), /status=ok/);
          assert.ok(new URLSearchParams(response.request().postData()).get('detached_signature'));
          console.log('PASS guest: automatic identity setup produces a signed vote');
        } finally { await guest.close(); }
        const legacy = await browser.newContext();
        try {
          await legacy.addCookies([{ name: 'identity_hint', value: 'openpgp:0168ff20eb09c3ea6193bd3c92a73aa7d20a0954', url: base }]);
          const directory = join(root, 'repository/records/thread-labels');
          const before = new Set(await readdir(directory));
          const response = await legacy.request.post(base + '/api/apply_thread_tag', { form: { thread_id: 'root-001', tag: 'like' } });
          assert.match(await response.text(), /wrote_record=yes/);
          const added = (await readdir(directory)).filter(name => !before.has(name));
          assert.equal(added.length, 1); assert.ok(added[0].endsWith('.txt'));
          const source = await legacy.request.get(base + '/source/current/records/thread-labels/' + added[0]);
          assert.equal(source.headers()['x-forum-signature-status'], 'unsigned');
          console.log('PASS legacy keyless client: unsigned submission and source status preserved');
        } finally { await legacy.close(); }
      }

    } catch (error) {
      console.error(serverLog.slice(-5000)); throw error;
    } finally {
      await context.close(); server.kill(); await once(server, 'exit'); await rm(root, { recursive: true, force: true });
    }
  }
} finally { await browser.close(); }
