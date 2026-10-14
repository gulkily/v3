const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/tools_bookmarklets.js', 'utf8');
const variants = [['url', 'same-window'], ...['clip', 'selection', 'tweet'].flatMap(k => ['same-window', 'new-window'].map(m => [k, m]))];
function setup(script = source, fail = false) {
  const copied = [];
  const links = variants.map(([kind, mode]) => {
    const feedback = {textContent: ''};
    const button = {disabled: true, addEventListener(_, cb) { this.click = cb; }, focus() { this.focused = true; }};
    const attrs = {'data-bookmarklet-kind': kind, 'data-bookmarklet-mode': mode};
    return {button, feedback, getAttribute: n => attrs[n], setAttribute: (n, v) => attrs[n] = v,
      closest: () => ({querySelector: s => s.includes('feedback') ? feedback : button})};
  });
  vm.runInNewContext(script, {window: {location: {origin: 'https://forum.test'}},
    document: {addEventListener(_, cb) { cb(); }, querySelectorAll: () => links},
    navigator: {clipboard: {async writeText(text) {if (fail) throw Error('denied'); copied.push(text);}}},
    setTimeout() {}, clearTimeout() {}});
  return {links, copied};
}
function execute(script, kind, mode) {
  const title = 'A "title" 100% café';
  const text = 'Selected "quote" 50% 😀\nsecond line';
  const current = 'https://source.test/status/123?q=100%25';
  const timeLink = {href: current, querySelector: () => ({}), getAttribute: () => '/status/123'};
  const tweetText = {nodeType: 1, tagName: 'DIV', firstChild: {nodeType: 3, nodeValue: text, nextSibling: null}};
  const article = {querySelector: s => s.includes('tweetText') ? tweetText : {innerText: 'Author\n@author'}, querySelectorAll: () => [timeLink], getBoundingClientRect: () => ({bottom: 100})};
  const location = {href: current, pathname: '/status/123'};
  const opened = [];
  const window = {location, getSelection: () => ({toString: () => kind === 'tweet' ? '' : text, rangeCount: 0}), open: (...args) => opened.push(args)};
  vm.runInNewContext(script, {window, location,
    document: {title, querySelectorAll: () => [article]}, alert() {throw Error('unexpected alert');}});
  const result = mode === 'new-window' ? opened[0][0] : window.location;
  const parsed = new URL(result);
  assert.equal(parsed.origin, 'https://forum.test');
  assert.equal(parsed.pathname, '/compose/thread');
  assert.equal(parsed.searchParams.get('board_tags'), 'general');
  assert.equal(parsed.searchParams.get('subject'), kind === 'tweet' ? '@author: ' + text.split('\n')[0] : title);
  assert.equal(parsed.searchParams.get('body'), kind === 'url' ? current : kind === 'selection' ? text : kind === 'tweet' ? text + '\n\n— Author (@author)\nhttps://source.test/status/123' : text + '\n\n' + title + '\n' + current);
  if (mode === 'new-window') assert.deepEqual(opened[0].slice(1), ['_blank', 'noopener']);
  else assert.equal(opened.length, 0);
}
(async () => {
  const {links, copied} = setup();
  for (let i = 0; i < links.length; i++) {
    const link = links[i], href = link.getAttribute('href');
    assert(href.startsWith('javascript:'));
    assert(!/[\r\n\u2028\u2029]/.test(href));
    execute(decodeURIComponent(href.slice('javascript:'.length)), ...variants[i]);
    await link.button.click();
    // Copy is unencoded one-line JavaScript (valid for qutebrowser :jseval), not the percent-encoded link.
    assert(copied[i].startsWith('javascript:'));
    assert(!/[\r\n\u2028\u2029]/.test(copied[i]));
    assert(!/%[0-9A-Fa-f]{2}/.test(copied[i]));
    assert(!copied[i].includes(';;'));
    const raw = copied[i].slice('javascript:'.length);
    assert.equal(raw, decodeURIComponent(href.slice('javascript:'.length)).replace(/\s*[\r\n]+\s*/g, ' '));
    execute(raw, ...variants[i]);
    new vm.Script('(function(){' + 'javascript:' + raw + '})');
    assert.equal(link.feedback.textContent, 'Copied');
    assert.equal(link.button.disabled, false);
    assert(link.button.focused);
  }
  const failed = setup(source, true);
  await failed.links[0].button.click();
  assert.equal(failed.links[0].feedback.textContent, 'Copy failed. Try again.');
  assert.equal(failed.links[0].button.disabled, false);
  if (process.env.BOOKMARKLET_BASELINE) {
    // Baseline source is already unencoded.
    const baseline = setup(fs.readFileSync(process.env.BOOKMARKLET_BASELINE, 'utf8'));
    baseline.links.forEach((link, i) => execute(link.getAttribute('href').slice(11), ...variants[i]));
  }
  console.log('PASS seven bookmarklets: behavior, one-line copying, focus, and denied clipboard');
})();
