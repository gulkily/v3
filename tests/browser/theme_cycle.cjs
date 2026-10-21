const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/theme_toggle.js', 'utf8');

function setup(systemDark, storedTheme, names = ['auto', 'light', 'dark', 'word97']) {
  function element(attributes = {}) {
    return {
      attributes, handlers: {}, hidden: true,
      getAttribute(name) { return this.attributes[name] ?? null; },
      setAttribute(name, value) { this.attributes[name] = value; },
      removeAttribute(name) { delete this.attributes[name]; },
      addEventListener(name, handler) { this.handlers[name] = handler; },
      focus() {}, querySelector() { return null; }
    };
  }
  const root = element();
  const cycle = element();
  const toggle = element();
  const options = names.map(name => element({ 'data-theme-option': name }));
  const popover = element();
  popover.querySelectorAll = () => options;
  const menu = element();
  menu.querySelector = selector => ({
    "[data-action='theme-cycle']": cycle,
    "[data-action='theme-toggle']": toggle,
    '#theme-menu-popover': popover
  })[selector];
  let saved = storedTheme;
  vm.runInNewContext(source, {
    window: {
      forumBrowserRuntime: { themeStorageKey: 'theme' },
      matchMedia: () => ({ matches: systemDark, addEventListener() {} }),
      setTimeout() {}
    },
    document: {
      documentElement: root,
      querySelector: () => menu,
      getElementById: () => null,
      addEventListener(name, handler) { if (name === 'DOMContentLoaded') handler(); }
    },
    localStorage: { getItem: () => saved, setItem(key, value) { saved = value; } }
  });
  return { root, cycle, options, saved: () => saved };
}

for (const systemDark of [false, true]) {
  const app = setup(systemDark, 'auto');
  const expected = systemDark ? 'light' : 'dark';
  assert.match(app.cycle.getAttribute('aria-label'), new RegExp('switch to ' + expected));
  app.cycle.handlers.click();
  assert.equal(app.root.getAttribute('data-resolved-theme'), expected);
  assert.equal(app.saved(), expected);
  assert.equal(app.options.find(option => option.getAttribute('data-theme-option') === expected).getAttribute('aria-checked'), 'true');
}

const wrap = setup(false, 'word97');
wrap.cycle.handlers.click();
assert.equal(wrap.saved(), 'auto');
assert.equal(wrap.root.getAttribute('data-resolved-theme'), 'light');
// Selecting a mode explicitly remains possible even when its appearance matches.
wrap.options[1].handlers.click();
assert.equal(wrap.saved(), 'light');
wrap.options[0].handlers.click();
assert.equal(wrap.saved(), 'auto');

const equivalent = setup(false, 'light', ['auto', 'light']);
equivalent.cycle.handlers.click();
assert.equal(equivalent.saved(), 'light');
console.log('PASS theme cycle appearance, labels, persistence, wraparound and explicit selection');
