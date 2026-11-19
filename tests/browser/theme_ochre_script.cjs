const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/theme-ochre.js', 'utf8');

// Ochre's script on a small fake thread: start adds parent/next links and a
// fold toggle to each reply, folding hides exactly the branch below, and stop
// removes every element and class it added.
class ClassList {
  constructor() { this.set = new Set(); }
  add(name) { this.set.add(name); }
  remove(name) { this.set.delete(name); }
  contains(name) { return this.set.has(name); }
  toggle(name, on) { if (on) this.set.add(name); else this.set.delete(name); }
}
class Node {
  constructor(tag) { this.tag = tag; this.children = []; this.parentNode = null; this.attributes = {}; this.classList = new ClassList(); this.handlers = {}; this.textContent = ''; }
  set className(value) { this.classList = new ClassList(); value.split(' ').filter(Boolean).forEach(name => this.classList.add(name)); }
  get firstChild() { return this.children[0] || null; }
  get id() { return this.attributes.id || ''; }
  appendChild(child) { child.parentNode = this; this.children.push(child); return child; }
  insertBefore(child, before) { child.parentNode = this; const i = this.children.indexOf(before); this.children.splice(i < 0 ? 0 : i, 0, child); return child; }
  removeChild(child) { this.children.splice(this.children.indexOf(child), 1); child.parentNode = null; }
  getAttribute(name) { return name in this.attributes ? this.attributes[name] : null; }
  setAttribute(name, value) { this.attributes[name] = String(value); }
  hasAttribute(name) { return name in this.attributes; }
  addEventListener(name, handler) { this.handlers[name] = handler; }
  click() { this.handlers.click(); }
  all() { return this.children.flatMap(child => [child, ...(child.all ? child.all() : [])]); }
}
function card(id, depth, root) {
  const node = new Node('article');
  node.className = root ? 'card post-card thread-root-card' : 'card post-card';
  node.setAttribute('id', 'post-' + id);
  node.setAttribute('data-post-id', id);
  if (depth > 0) node.setAttribute('data-reply-depth', depth);
  const meta = new Node('p'); meta.className = 'meta'; node.appendChild(meta);
  const body = new Node('div'); body.className = 'body'; node.appendChild(body);
  return node;
}
// root; a(0) > b(1) > c(2), d(1); e(0); f(0) with no grey line.
const main = new Node('main');
const cards = [card('root', 0, true), card('a', 0), card('b', 1), card('c', 2), card('d', 1), card('e', 0), card('f', 0)];
cards[6].removeChild(cards[6].children[0]);
cards.forEach(node => main.appendChild(node));
const replies = cards.slice(1);
const document = {
  createElement: tag => new Node(tag),
  createTextNode: value => { const node = new Node('#text'); node.textContent = value; return node; },
  querySelector: selector => selector === '.thread-root-card' ? cards[0] : null,
  querySelectorAll: selector => selector === '.post-card[data-post-id]' ? main.children.filter(node => node.classList.contains('post-card')) : []
};
let hooks = null;
const window = { forumThemeScripts: { register(name, value) { assert.equal(name, 'ochre'); hooks = value; } }, requestAnimationFrame() {} };
vm.runInNewContext(source, { window, document });
const added = () => main.all().filter(node => node.classList.contains('ochre-nav') || node.classList.contains('ochre-line') || node.classList.contains('ochre-keep') || node.classList.contains('ochre-hidden') || node.classList.contains('ochre-collapsed')).length;
const toggle = node => node.all().find(child => child.classList.contains('ochre-toggle'));
const links = node => node.all().filter(child => child.classList.contains('ochre-link')).map(child => child.textContent + ' ' + child.getAttribute('href'));
const hidden = () => replies.filter(node => node.classList.contains('ochre-hidden')).map(node => node.getAttribute('data-post-id'));

hooks.start();
assert.deepEqual(links(replies[0]), ['parent #post-root', 'next #post-e']);
assert.deepEqual(links(replies[1]), ['parent #post-a', 'next #post-d']);
assert.deepEqual(links(replies[2]), ['parent #post-b']);
assert.deepEqual(links(replies[3]), ['parent #post-a']);
assert.equal(toggle(replies[0]).textContent, '[–]');
// A reply with no grey line gets one of its own, first in the card.
assert.ok(replies[5].children[0].classList.contains('ochre-line'));

toggle(replies[1]).click();
assert.deepEqual(hidden(), ['c']);
assert.equal(toggle(replies[1]).textContent, '[+2]');
assert.equal(toggle(replies[1]).getAttribute('aria-expanded'), 'false');
toggle(replies[0]).click();
assert.deepEqual(hidden(), ['b', 'c', 'd']);
assert.equal(toggle(replies[0]).textContent, '[+4]');
// Unfolding the outer branch keeps the inner fold.
toggle(replies[0]).click();
assert.deepEqual(hidden(), ['c']);

hooks.stop();
assert.equal(added(), 0);
assert.equal(replies[5].children.length, 1);
hooks.start();
assert.equal(added() > 0, true);
hooks.stop();
assert.equal(added(), 0);

console.log('PASS Ochre adds parent/next links and fold toggles, folds whole branches, and stop removes everything');
