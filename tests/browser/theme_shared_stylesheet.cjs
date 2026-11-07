const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync('public/assets/theme_toggle.js', 'utf8');

// Two theme names that share one stylesheet load it once; switching between
// them waits on no second request.
function setup(storedTheme) {
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
  const root = element({ 'data-resolved-theme': storedTheme });
  const cycle = element();
  const toggle = element();
  const names = ['auto', 'light', 'gloss', 'engagify'];
  const options = names.map(name => element({ 'data-theme-option': name }));
  const popover = element();
  popover.querySelectorAll = () => options;
  const menu = element();
  // No palette pickers in these menus.
  menu.querySelectorAll = () => [];
  menu.querySelector = selector => ({
    "[data-action='theme-cycle']": cycle,
    "[data-action='theme-toggle']": toggle,
    '#theme-menu-popover': popover
  })[selector];
  const appended = [];
  const window = {
    forumBrowserRuntime: { themeStorageKey: 'theme' },
    forumThemeStylesheetPaths: { light: '/assets/theme-light.css', gloss: '/assets/theme-gloss.css', engagify: '/assets/theme-gloss.css' },
    matchMedia: () => ({ matches: false, addEventListener() {} }),
    setTimeout() {}
  };
  let saved = storedTheme;
  vm.runInNewContext(source, {
    window,
    Promise,
    document: {
      readyState: 'interactive',
      documentElement: root,
      head: { appendChild(node) { appended.push(node); } },
      createElement: () => element(),
      querySelector: () => menu,
      getElementById: () => null,
      addEventListener(name, handler) { if (name === 'DOMContentLoaded') handler(); }
    },
    localStorage: { getItem: () => saved, setItem(key, value) { if (key === 'theme') saved = value; } }
  });
  const option = name => options[names.indexOf(name)];
  const links = path => appended.filter(node => node.getAttribute('href') === path);
  return { root, option, links, saved: () => saved };
}

(async () => {
  const page = setup('light');

  // Gloss requests the shared file and waits for it.
  page.option('gloss').handlers.click();
  assert.equal(page.links('/assets/theme-gloss.css').length, 1);
  assert.equal(page.root.getAttribute('data-theme'), 'light');

  // Engagify, chosen before the file arrives, reuses the same request.
  page.option('engagify').handlers.click();
  assert.equal(page.links('/assets/theme-gloss.css').length, 1);

  page.links('/assets/theme-gloss.css')[0].handlers.load();
  await new Promise(resolve => setImmediate(resolve));
  assert.equal(page.root.getAttribute('data-theme'), 'engagify');
  assert.equal(page.saved(), 'engagify');

  // Back to Gloss and to Engagify again: applied at once, no new request.
  page.option('gloss').handlers.click();
  assert.equal(page.root.getAttribute('data-theme'), 'gloss');
  page.option('engagify').handlers.click();
  assert.equal(page.root.getAttribute('data-theme'), 'engagify');
  assert.equal(page.links('/assets/theme-gloss.css').length, 1);

  console.log('PASS themes that share a stylesheet load it once and switch without waiting');
})().catch(error => { console.error(error); process.exit(1); });
