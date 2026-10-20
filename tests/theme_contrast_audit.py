"""Theme token contrast audit for the docs page (WCAG 2.2 AA).

Usage: python3 -I tests/theme_contrast_audit.py <repo-root> <report.json>
"""
import json, re, sys, pathlib
root = pathlib.Path(sys.argv[1]); out = sys.argv[2]
assets = root / 'public/assets'
NAMED = {'white': '#ffffff', 'black': '#000000', 'transparent': 'rgba(0,0,0,0)'}
def blocks(css, selector_re):
    v = {}
    for m in re.finditer(r'([^{}]+)\{([^{}]*)\}', css):
        if re.search(selector_re, m.group(1)):
            for k, val in re.findall(r'(--[\w-]+)\s*:\s*([^;]+);', m.group(2)):
                v[k] = val.strip()
    return v
site = (assets / 'site.css').read_text()
defaults = blocks(site, r'^\s*:root\s*$')
def parse(val, vars_, depth=0):
    val = val.strip().lower()
    m = re.fullmatch(r'var\((--[\w-]+)(?:\s*,\s*(.+))?\)', val)
    if m:
        ref = vars_.get(m.group(1), m.group(2))
        return parse(ref, vars_, depth + 1) if ref and depth < 8 else None
    val = NAMED.get(val, val)
    m = re.fullmatch(r'#([0-9a-f]{3,8})', val)
    if m:
        h = m.group(1)
        if len(h) in (3, 4): h = ''.join(c * 2 for c in h)
        a = int(h[6:8], 16) / 255 if len(h) == 8 else 1.0
        return tuple(int(h[i:i+2], 16) for i in (0, 2, 4)) + (a,)
    m = re.fullmatch(r'rgba?\(([^)]+)\)', val)
    if m:
        p = [x.strip() for x in re.split(r'[,\s/]+', m.group(1)) if x.strip()]
        a = float(p[3].rstrip('%')) / (100 if p[3].endswith('%') else 1) if len(p) > 3 else 1.0
        return tuple(float(x) for x in p[:3]) + (a,)
    return None
def over(fg, bg):
    a = fg[3]; return tuple(fg[i] * a + bg[i] * (1 - a) for i in range(3)) + (1.0,)
def lum(c):
    def ch(x):
        x /= 255; return x / 12.92 if x <= 0.03928 else ((x + 0.055) / 1.055) ** 2.4
    return 0.2126 * ch(c[0]) + 0.7152 * ch(c[1]) + 0.0722 * ch(c[2])
def ratio(a, b):
    la, lb = sorted((lum(a), lum(b)), reverse=True); return (la + 0.05) / (lb + 0.05)
PAIRS = [('--ink', '--page-bg', 4.5), ('--ink', '--panel', 4.5), ('--ink-soft', '--panel', 4.5),
         ('--ink-soft', '--page-bg', 4.5), ('--ink', '--input-bg', 4.5), ('--ink', '--button-bg', 4.5),
         ('--active-ink', '--active-bg', 4.5), ('--line', '--panel', 3.0), ('--line', '--input-bg', 3.0),
         ('--active-line', '--panel', 3.0)]
themes = re.findall(r"'name' => '([a-z0-9]+)'", (root / 'src/ForumRewrite/View/ThemeRegistry.php').read_text())
report = {}
for theme in [t for t in themes if t != 'auto']:
    css = (assets / f'theme-{theme}.css').read_text()
    v = dict(defaults); v.update(blocks(css, rf'data-theme(?:-option)?="{theme}"\](?![^,]*\s[.\w#])'))
    page = parse(v.get('--page-bg', '#fff'), v) or (255, 255, 255, 1.0)
    page = over(page, (255, 255, 255, 1.0))
    rows = []
    for fg, bg, need in PAIRS:
        b = parse(v.get(bg, ''), v); f = parse(v.get(fg, ''), v)
        if not b or not f:
            rows.append({'pair': f'{fg} on {bg}', 'required': need, 'ratio': None, 'pass': None, 'note': 'unresolved token'}); continue
        b = over(b, page); f = over(f, b); r = round(ratio(f, b), 2)
        rows.append({'pair': f'{fg} on {bg}', 'required': need, 'ratio': r, 'pass': r >= need})
    report[theme] = rows
json.dump(report, open(out, 'w'), indent=1)
for theme, rows in report.items():
    bad = [f"{r['pair']} {r['ratio']}" for r in rows if r['pass'] is False]
    unk = [r['pair'] for r in rows if r['pass'] is None]
    print(f"{theme:11} {'OK' if not bad else 'FAIL: ' + '; '.join(bad)}{'  (unresolved: ' + ', '.join(unk) + ')' if unk else ''}")
