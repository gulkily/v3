const assert = require('node:assert/strict');
const {mkdtempSync, readFileSync, existsSync} = require('node:fs');
const {tmpdir} = require('node:os');
const {join, resolve} = require('node:path');
const {spawnSync} = require('node:child_process');
const {chromium} = require('playwright-core');
const project = resolve(__dirname, '../..');
const fixture = mkdtempSync(join(tmpdir(), 'scripture-expansion-browser-'));
const setup = spawnSync('php', [join(project, 'tests/Support/scripture_expansion_browser_fixture.php'), fixture], {cwd:project, encoding:'utf8'});
assert.equal(setup.status, 0, setup.stdout + setup.stderr);
(async () => {
  const browser = await chromium.launch({executablePath:process.env.CHROME_PATH || '/opt/google/chrome/chrome', headless:true, args:['--no-sandbox']});
  try {
    for (const surface of ['thread', 'post']) for (const width of [1280, 375]) {
      const page = await browser.newPage({viewport:{width,height:800}, serviceWorkers:'block'});
      if (surface === 'post' && width === 375) {
        await page.addInitScript(() => Object.defineProperty(window, 'sessionStorage', {get() { throw new Error('Storage blocked'); }}));
      }
      let requests = 0;
      const malicious = 'Further context.\n<script>window.expansionInjected=true</script>';
      await page.route('**/*', route => {
        const url = new URL(route.request().url());
        if (url.pathname === '/fixture' || url.pathname === '/completed') {
          const state = url.pathname === '/completed' ? 'generated' : 'available';
          return route.fulfill({contentType:'text/html',body:readFileSync(join(fixture, state + '-' + surface + '.html'), 'utf8')});
        }
        if (url.pathname.startsWith('/assets/')) {
          const path = join(project, 'public', url.pathname.replace(/^\//,'').replace(/\.[a-f0-9]{12}\./,'.'));
          if (existsSync(path)) return route.fulfill({path});
        }
        if (url.pathname === '/api/expand_agent_reply') {
          requests++;
          return route.fulfill({contentType:'application/json',body:JSON.stringify({status:'ok',generation_status:'requested'})});
        }
        if (url.pathname === '/api/agent_expansion_status') {
          return route.fulfill({contentType:'application/json',body:JSON.stringify({status:'ok',generation_status:'generated',expansion_body:malicious})});
        }
        return route.fulfill({status:404,body:''});
      });
      await page.goto('http://scripture.test/fixture');
      const button = page.locator('[data-action="expand-scripture"]');
      assert.equal(await button.count(),1);
      await button.focus(); await page.keyboard.press('Enter');
      const details = page.locator('[data-expansion-details]');
      await page.waitForFunction(() => document.querySelector('[data-expansion-details]').open);
      assert.equal(requests,1); assert.equal(await page.evaluate(() => window.expansionInjected), undefined);
      assert((await details.innerText()).includes('<script>'));
      await details.locator('summary').focus(); await page.keyboard.press('Enter');
      assert.equal(await details.getAttribute('open'), null);
      await page.goto('http://scripture.test/completed');
      assert.equal(await page.locator('[data-action="expand-scripture"]').count(),0);
      assert.equal(await page.locator('[data-expansion-details]').getAttribute('open'), null);
      await page.locator('[data-expansion-details] summary').focus(); await page.keyboard.press('Enter');
      assert.notEqual(await page.locator('[data-expansion-details]').getAttribute('open'),null);
      await page.waitForTimeout(200);
      assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false);
      console.log('PASS ' + surface + ' ' + width + ': request, progress, auto-open, safe text, keyboard, shared result');
      await page.close();
    }
    for (const width of [375, 1280]) {
    const page = await browser.newPage({viewport:{width,height:800}});
    const metadata = JSON.parse(readFileSync(join(fixture, 'fixture.json'), 'utf8'));
    await page.route('**/*', route => {
      const url = new URL(route.request().url());
      if (url.pathname === '/source-fixture') return route.fulfill({contentType:'text/plain; charset=utf-8',body:readFileSync(join(fixture, 'source.txt'), 'utf8')});
      if (url.pathname === '/offline-fixture') return route.fulfill({contentType:'text/html',body:'<main id="saved"></main><script src="/assets/sql-wasm.js"></script><script src="/assets/offline_reader.js"></script>'});
      if (url.pathname === '/snapshot.sqlite3' || url.pathname === '/unavailable.sqlite3') return route.fulfill({path:join(fixture,url.pathname.slice(1))});
      if (url.pathname.startsWith('/assets/')) {
        const path = join(project,'public',url.pathname.replace(/^\//,''));
        if (existsSync(path)) return route.fulfill({path});
      }
      return route.fulfill({status:404,body:''});
    });
    await page.goto('http://scripture.test/source-fixture');
    assert((await page.locator('body').innerText()).includes('Agent-Response-Type: scripture_oriented_analysis'));
    // Raw source uses the browser's native text viewer, not application layout.
    await page.goto('http://scripture.test/offline-fixture');
    await page.evaluate(async id => {
      const SQL = await initSqlJs({locateFile:name => '/assets/' + name});
      const bytes = await (await fetch('/snapshot.sqlite3')).arrayBuffer();
      const db = new SQL.Database(new Uint8Array(bytes));
      window.testSql = SQL;
      window.testSnapshotDatabase = db;
      window.forumOfflineSnapshot.renderThreadDetail({database:db,content:document.getElementById('saved'),threadId:id,setStatus(){}});
    }, metadata.source);
    assert.equal(await page.locator('article').count(),2);
    assert.equal(await page.locator('details').count(),1);
    assert.equal(await page.locator('[data-action="expand-scripture"]').count(),0);
    await page.locator('summary').focus(); await page.keyboard.press('Enter');
    assert.notEqual(await page.locator('details').getAttribute('open'),null);
    assert.equal(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth), false);
    await page.evaluate(id => {
      const db = window.testSnapshotDatabase;
      db.run("DELETE FROM posts WHERE agent_response_json LIKE '%scripture_expansion%'");
      db.run('ALTER TABLE posts DROP COLUMN agent_response_json');
      window.forumOfflineSnapshot.renderThreadDetail({database:db,content:document.getElementById('saved'),threadId:id,setStatus(){}});
    }, metadata.source);
    assert.equal(await page.locator('article').count(),2);
    await page.evaluate(async id => {
      const update = new window.testSql.Database(new Uint8Array(await (await fetch('/snapshot.sqlite3')).arrayBuffer()));
      window.forumOfflineSnapshot.applyUpdate(window.testSnapshotDatabase,update);
      window.forumOfflineSnapshot.renderThreadDetail({database:window.testSnapshotDatabase,content:document.getElementById('saved'),threadId:id,setStatus(){}});
    }, metadata.source);
    assert.equal(await page.locator('details').count(),1);
    await page.evaluate(async id => {
      const update = new window.testSql.Database(new Uint8Array(await (await fetch('/unavailable.sqlite3')).arrayBuffer()));
      window.forumOfflineSnapshot.applyUpdate(window.testSnapshotDatabase,update);
      window.forumOfflineSnapshot.renderThreadDetail({database:window.testSnapshotDatabase,content:document.getElementById('saved'),threadId:id,setStatus(){}});
    }, metadata.source);
    assert.equal(await page.locator('details').count(),0);
    console.log('PASS source and offline ' + width + ': supplementary content, no generation controls, keyboard, legacy schema/updates, source revocation');
    await page.close();
    }
  } finally { await browser.close(); }
})().catch(error => { console.error(error); process.exitCode = 1; });
