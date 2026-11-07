const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/theme_toggle.js', 'utf8');

// Exit from a theme works like a menu choice of its target, and the cycle
// button passes over themes marked to skip it.
function setup({ stored = {}, rootAttributes = {}, names = ['auto', 'light', 'word97', 'gloss', 'engagify'], skip = ['engagify'] } = {}) {
  function element(attributes = {}) {
    return {
      attributes, handlers: {}, hidden: true,
      getAttribute(name) { return this.attributes[name] ?? null; },
      setAttribute(name, value) { this.attributes[name] = value; },
      removeAttribute(name) { delete this.attributes[name]; },
      addEventListener(name, handler) { this.handlers[name] = handler; },
      remove() {}, focus() {}, querySelector() { return null; }
    };
  }
  const root = element(rootAttributes);
  const cycle = element();
  const toggle = element();
  const options = names.map(name => element(Object.assign({ 'data-theme-option': name }, skip.includes(name) ? { 'data-theme-cycle': 'skip' } : {})));
  const popover = element();
  popover.querySelectorAll = () => options;
  const menu = element();
  menu.querySelector = selector => ({
    "[data-action='theme-cycle']": cycle,
    "[data-action='theme-toggle']": toggle,
    '#theme-menu-popover': popover
  })[selector];
  const storage = Object.assign({}, stored);
  const window = {
    forumBrowserRuntime: { themeStorageKey: 'theme' },
    forumThemeScriptPaths: {},
    matchMedia: () => ({ matches: false, addEventListener() {} }),
    setTimeout() {}
  };
  vm.runInNewContext(source, {
    window,
    document: {
      readyState: 'interactive',
      documentElement: root,
      head: { appendChild() {} },
      createElement: () => element(),
      querySelector: () => menu,
      getElementById: () => null,
      addEventListener(name, handler) { if (name === 'DOMContentLoaded') handler(); }
    },
    localStorage: {
      getItem: key => (key in storage ? storage[key] : null),
      setItem(key, value) { storage[key] = String(value); }
    }
  });
  return { root, window, storage, cycle, option: name => options[names.indexOf(name)] };
}

// A reader who picked Engagify exits to Gloss, which is saved.
const picked = setup({ stored: { theme: 'word97' } });
picked.option('engagify').handlers.click();
assert.equal(picked.root.getAttribute('data-theme'), 'engagify');
picked.window.forumExitTheme('gloss');
assert.equal(picked.root.getAttribute('data-theme'), 'gloss');
assert.equal(picked.storage.theme, 'gloss');

// On a page theme's page, Exit shows Gloss, saves it over the reader's own
// theme, and remembers the page exit.
const pageAttributes = { 'data-page-theme': 'engagify', 'data-page-path': '/threads/cookies', 'data-resolved-theme': 'engagify' };
const page = setup({ stored: { theme: 'word97' }, rootAttributes: pageAttributes });
assert.equal(page.root.getAttribute('data-theme'), 'engagify');
page.window.forumExitTheme('gloss');
assert.equal(page.root.getAttribute('data-theme'), 'gloss');
assert.equal(page.storage.theme, 'gloss');
assert.equal(page.storage['theme-page-exit:/threads/cookies'], '1');

// The revisit stays on Gloss.
const revisit = setup({ stored: page.storage, rootAttributes: pageAttributes });
assert.equal(revisit.root.getAttribute('data-theme'), 'gloss');

// A target the site does not offer falls back to the site default.
const noGloss = setup({ stored: { theme: 'engagify' }, names: ['auto', 'light', 'engagify'] });
noGloss.window.forumExitTheme('gloss');
assert.equal(noGloss.root.getAttribute('data-theme'), null);
assert.equal(noGloss.storage.theme, 'auto');

// The cycle button never lands on Engagify, from any theme, and still
// leaves it when Engagify is current.
const cycling = setup({ stored: { theme: 'light' } });
const seen = [];
for (let i = 0; i < 12; i += 1) {
  cycling.cycle.handlers.click();
  seen.push(cycling.root.getAttribute('data-theme') ?? 'auto');
}
assert.equal(seen.includes('engagify'), false);
assert.equal(seen.includes('gloss'), true);
const fromEngagify = setup({ stored: { theme: 'engagify' } });
fromEngagify.cycle.handlers.click();
assert.notEqual(fromEngagify.root.getAttribute('data-theme'), 'engagify');

console.log('PASS Exit saves its target theme, page exits are remembered, and the cycle button skips marked themes');
