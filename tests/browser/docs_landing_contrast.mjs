import { chromium } from 'playwright-core';
import { readFile, writeFile } from 'node:fs/promises';

// Measures rendered text contrast for the docs landing/results components in every theme.
// Usage: node tests/browser/docs_landing_contrast.mjs [report.json]
const base = process.env.DOCS_DYNAMIC_BASE_URL || 'http://127.0.0.1:8191';
const registry = await readFile('src/ForumRewrite/View/ThemeRegistry.php', 'utf8');
const themes = [...registry.matchAll(/'name' => '([a-z0-9]+)'/g)].map(match => match[1]).filter(name => name !== 'auto');
const targets = {
  '/docs/': ['.docs-intro p', '.docs-search label', '.docs-entry__title', '.docs-entry__description', '.docs-entry__path', '.docs-flow__node strong', '.docs-flow__node span', '.docs-flow__node code', '.docs-flow__edge', '.docs-quick-links a'],
  '/docs/?category=Architecture&q=trust': ['.docs-chip', '.docs-filters > summary', '.docs-filter-list a', '.docs-filter-list a[aria-current]', '.docs-result__title', '.docs-result__summary', '.docs-result__path', '.docs-badge', '.docs-intro__home a'],
};
const browser = await chromium.launch({ executablePath: process.env.CHROMIUM_PATH || '/usr/bin/google-chrome', args: ['--no-sandbox'] });
const report = {};
try {
  for (const theme of themes) {
    const page = await browser.newPage();
    report[theme] = [];
    for (const [path, selectors] of Object.entries(targets)) {
      await page.goto(base + path, { waitUntil: 'networkidle' });
      // Swap the theme in its own <link>, so it keeps its place before site and page styles.
      await page.evaluate(name => new Promise(resolve => {
        const link = document.getElementById('theme-stylesheet');
        document.documentElement.dataset.theme = name;
        link.addEventListener('load', resolve, { once: true });
        link.href = '/assets/theme-' + name + '.css';
      }), theme);
      await page.evaluate(() => document.querySelector('.docs-filters')?.setAttribute('open', ''));
      report[theme].push(...await page.evaluate(selectors => {
        const parse = value => { const m = value.match(/rgba?\(([^)]+)\)/); if (!m) return null; const p = m[1].split(/[\s,/]+/).filter(Boolean).map(Number); return [p[0], p[1], p[2], p.length > 3 ? p[3] : 1]; };
        const over = (fg, bg) => [0, 1, 2].map(i => fg[i] * fg[3] + bg[i] * (1 - fg[3])).concat(1);
        const lum = c => { const ch = x => { x /= 255; return x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4; }; return 0.2126 * ch(c[0]) + 0.7152 * ch(c[1]) + 0.0722 * ch(c[2]); };
        const background = node => {
          const layers = [];
          let image = false;
          for (let current = node; current; current = current.parentElement) {
            const style = getComputedStyle(current);
            if (style.backgroundImage !== 'none') image = true;
            const color = parse(style.backgroundColor);
            if (color && color[3] > 0) { layers.push(color); if (color[3] === 1) break; }
          }
          let result = [255, 255, 255, 1];
          for (const layer of layers.reverse()) result = over(layer, result);
          return { color: result, image };
        };
        return selectors.map(selector => {
          const node = [...document.querySelectorAll(selector)].find(element => element.getClientRects().length);
          if (!node) return { selector, missing: true };
          const style = getComputedStyle(node), bg = background(node), fg = over(parse(style.color), bg.color);
          const [hi, lo] = [lum(fg), lum(bg.color)].sort((a, b) => b - a);
          const ratio = Math.round(((hi + 0.05) / (lo + 0.05)) * 100) / 100;
          const large = parseFloat(style.fontSize) >= 24 || (parseFloat(style.fontSize) >= 18.66 && Number(style.fontWeight) >= 700);
          return { selector, ratio, required: large ? 3 : 4.5, pass: ratio >= (large ? 3 : 4.5), backgroundImage: bg.image, fontSize: style.fontSize };
        });
      }, selectors));
    }
    await page.close();
  }
} finally {
  await browser.close();
}
if (process.argv[2]) await writeFile(process.argv[2], JSON.stringify(report, null, 1));
for (const [theme, rows] of Object.entries(report)) {
  const failures = rows.filter(row => row.pass === false).map(row => `${row.selector} ${row.ratio}${row.backgroundImage ? ' (bg image)' : ''}`);
  const missing = rows.filter(row => row.missing).map(row => row.selector);
  console.log(theme.padEnd(11), failures.length ? 'FAIL: ' + failures.join('; ') : 'OK', missing.length ? ' missing: ' + missing.join(', ') : '');
}
