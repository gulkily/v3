// The Port HN bookmarklet on saved Hacker News pages: a story, a comment's own
// page, a selection, and a page that isn't Hacker News. Each port file it sends
// must parse with PortFile and put every comment under its parent.
// Run: node tests/browser/thread_port_bookmarklet.mjs  (CHROMIUM_PATH overrides Chrome's path)
import assert from 'node:assert/strict';
import fs from 'node:fs';
import vm from 'node:vm';
import { execFileSync } from 'node:child_process';
import { chromium } from 'playwright-core';

const project = new URL('../..', import.meta.url).pathname;
const fixture = name => fs.readFileSync(project + 'tests/fixtures/hn/' + name, 'utf8');

// Build the bookmarklet the way the Bookmarklets page does.
function bookmarklet(mode) {
  const links = [];
  const link = { getAttribute: n => ({ 'data-bookmarklet-kind': 'hn-port', 'data-bookmarklet-mode': mode })[n], setAttribute: (n, v) => { link.href = v; },
    closest: () => ({ querySelector: () => ({ addEventListener() {}, focus() {} }) }) };
  links.push(link);
  vm.runInNewContext(fs.readFileSync(project + 'public/assets/tools_bookmarklets.js', 'utf8'), {
    window: { location: { origin: 'https://forum.test' } },
    document: { addEventListener(_, cb) { cb(); }, querySelectorAll: () => links },
    navigator: {}, setTimeout() {}, clearTimeout() {},
  });
  assert.ok(link.href.startsWith('javascript:/*forum.test*/'));
  return decodeURIComponent(link.href.slice('javascript:/*forum.test*/'.length));
}

function parse(portText) {
  const php = `require '${project}autoload.php'; $p = ForumRewrite\\Port\\PortFile::parse(stream_get_contents(STDIN));
    echo json_encode(['title' => $p->threadTitle, 'link' => $p->threadLink, 'items' => array_map(fn ($i) => (array) $i, $p->items)]);`;
  return JSON.parse(execFileSync('php', ['-r', php], { input: portText }).toString());
}

const browser = await chromium.launch({ headless: true, executablePath: process.env.CHROMIUM_PATH || '/opt/google/chrome/chrome', args: ['--no-sandbox'] });
try {
  const context = await browser.newContext();
  const sent = [];
  await context.route('https://news.ycombinator.com/**', route => {
    const id = new URL(route.request().url()).searchParams.get('id');
    route.fulfill({ status: 200, contentType: 'text/html', body: fixture(id === '1006' ? 'comment.html' : 'story.html') });
  });
  await context.route('https://forum.test/**', route => {
    sent.push({ url: route.request().url(), method: route.request().method(), body: route.request().postData() || '' });
    route.fulfill({ status: 200, contentType: 'text/html', body: '<p>ok</p>' });
  });
  const page = await context.newPage();
  const alerts = [];
  page.on('dialog', d => { alerts.push(d.message()); d.dismiss(); });
  const run = async (mode = 'same-window') => {
    const before = sent.length;
    await page.evaluate(bookmarklet(mode));
    if (alerts.length) return null;
    await page.waitForFunction(() => location.host === 'forum.test').catch(() => {});
    assert.equal(sent.length, before + 1, 'one form sent');
    const last = sent.at(-1);
    assert.equal(last.method, 'POST');
    assert.equal(last.url, 'https://forum.test/port');
    return parse(new URLSearchParams(last.body).get('port'));
  };

  // Story page: everything, nested.
  await page.goto('https://news.ycombinator.com/item?id=1000');
  let port = await run();
  assert.equal(port.title, 'Show HN: A test story');
  assert.equal(port.link, 'https://news.ycombinator.com/item?id=1000');
  const parents = Object.fromEntries(port.items.map(i => [i.id, i.parent]));
  assert.deepEqual(parents, { 1000: null, 1001: '1000', 1002: '1001', 1003: '1002', 1004: '1001', 1005: '1004', 1006: '1000', 1007: '1006' });
  const byId = Object.fromEntries(port.items.map(i => [i.id, i]));
  assert.equal(byId[1000].author, 'storyteller');
  assert.equal(byId[1000].createdAt, '2026-01-01T00:00:00Z');
  assert.equal(byId[1000].link, 'https://example.com/story');
  assert.equal(byId[1000].text, "The story's own text.");
  assert.equal(byId[1001].text, 'First top-level comment.\n\nWith a second paragraph and a https://example.com/a/very/long/path/that/hn/truncates link.');
  assert.equal(byId[1003].text, 'Nested deeper, with code.');
  assert.equal(byId[1004].author, '[deleted]');
  assert.equal(byId[1006].text, 'Second top-level comment.\n\n%% looks like a separator');
  assert.equal(byId[1002].text, 'A reply to alice.', 'reply links are left out');

  // A comment's own page: that comment is the root, with the story's title and link.
  await page.goto('https://news.ycombinator.com/item?id=1006');
  port = await run('new-window');
  assert.equal(port.title, 'Show HN: A test story');
  assert.equal(port.link, 'https://news.ycombinator.com/item?id=1000');
  assert.deepEqual(port.items.map(i => [i.id, i.parent]), [['1006', null], ['1007', '1006']]);
  assert.equal(port.items[0].title, '');

  // A selection inside comment 1002 ports it and its replies only.
  await page.goto('https://news.ycombinator.com/item?id=1000');
  await page.evaluate(() => {
    const text = document.getElementById('1002').querySelector('.commtext').firstChild;
    const range = document.createRange();
    range.setStart(text, 2); range.setEnd(text, 7);
    getSelection().removeAllRanges(); getSelection().addRange(range);
  });
  port = await run();
  assert.deepEqual(port.items.map(i => [i.id, i.parent]), [['1002', null], ['1003', '1002']]);
  assert.equal(port.title, 'Show HN: A test story');

  // Not a Hacker News item page: an alert, nothing sent.
  await page.goto('https://forum.test/elsewhere');
  const count = sent.length;
  assert.equal(await run(), null);
  assert.match(alerts[0], /Open a Hacker News thread first/);
  assert.equal(sent.length, count);
  console.log('thread_port_bookmarklet: ok');
} finally {
  await browser.close();
}
