const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/theme_toggle.js', 'utf8');

// Theme scripts: only the active theme's script is loaded and started, and
// switching away stops it before the next theme starts.
function setup(storedTheme, resolvedAtLoad) {
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
  const root = element({ 'data-resolved-theme': resolvedAtLoad });
  const cycle = element();
  const toggle = element();
  const names = ['auto', 'light', 'word97', 'engagify'];
  const options = names.map(name => element({ 'data-theme-option': name }));
  const popover = element();
  popover.querySelectorAll = () => options;
  const menu = element();
  menu.querySelector = selector => ({
    "[data-action='theme-cycle']": cycle,
    "[data-action='theme-toggle']": toggle,
    '#theme-menu-popover': popover
  })[selector];
  const appended = [];
  const window = {
    forumBrowserRuntime: { themeStorageKey: 'theme' },
    forumThemeScriptPaths: { engagify: '/assets/theme-engagify.js' },
    matchMedia: () => ({ matches: false, addEventListener() {} }),
    setTimeout() {}
  };
  let saved = storedTheme;
  vm.runInNewContext(source, {
    window,
    document: {
      readyState: 'interactive',
      documentElement: root,
      head: { appendChild(node) { appended.push(node); } },
      createElement: () => element(),
      querySelector: () => menu,
      getElementById: () => null,
      addEventListener(name, handler) { if (name === 'DOMContentLoaded') handler(); }
    },
    localStorage: { getItem: () => saved, setItem(key, value) { saved = value; } }
  });
  const option = name => options[names.indexOf(name)];
  return { window, appended, option };
}

const events = [];
const hooks = { start: () => events.push('start'), stop: () => events.push('stop') };

// A page that starts on a theme without a script loads nothing.
const plain = setup('light', 'light');
assert.equal(plain.appended.length, 0);

// Choosing the theme loads its script once and starts it after it registers.
plain.option('engagify').handlers.click();
assert.equal(plain.appended.length, 1);
assert.equal(plain.appended[0].src, '/assets/theme-engagify.js');
assert.deepEqual(events, []);
plain.window.forumThemeScripts.register('engagify', hooks);
assert.deepEqual(events, ['start']);
assert.equal(plain.window.forumThemeScripts.active(), 'engagify');

// Switching away stops it; switching back starts it again without a reload.
plain.option('word97').handlers.click();
assert.deepEqual(events, ['start', 'stop']);
assert.equal(plain.window.forumThemeScripts.active(), null);
plain.option('engagify').handlers.click();
assert.deepEqual(events, ['start', 'stop', 'start']);
assert.equal(plain.appended.length, 1);

// A page that loads with the theme active requests its script immediately.
const direct = setup('engagify', 'engagify');
assert.equal(direct.appended.length, 1);

// A failing start does not break theme switching.
const failing = setup('engagify', 'engagify');
failing.window.console = { error() {} };
failing.window.forumThemeScripts.register('engagify', { start() { throw new Error('boom'); }, stop() {} });
failing.option('light').handlers.click();
assert.equal(failing.window.forumThemeScripts.active(), null);

console.log('PASS theme scripts load once, start only while active, and stop on switch');
