(() => {
  'use strict';
  const keys = ['q', 'folder', 'category', 'group', 'page'];
  const params = () => {
    const source = new URLSearchParams(location.search), result = new URLSearchParams();
    for (const key of keys) if (source.has(key)) result.set(key, source.get(key));
    return result;
  };
  // Mirrors PlatformDocsCatalog::categorySlug so mixed-case category URLs keep matching.
  const categorySlug = value => value.toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '');
  const returnLink = document.querySelector('[data-docs-return]');
  if (returnLink) returnLink.href = '/docs/?' + params() + '#docs-library-heading';
  // Carry return state through related local document links, including anchors.
  if (returnLink) document.querySelectorAll('.docs-content a[href^="/docs/"]').forEach(link => {
    const url = new URL(link.href);
    if (url.pathname === '/docs/' || url.pathname.startsWith('/docs/_download/')) return;
    url.search = params().toString();
    link.href = url.href;
  });
  const retry = document.querySelector('[data-docs-document-retry]');
  if (retry) retry.addEventListener('click', () => location.reload());
  const root = document.querySelector('[data-docs-library]');
  if (!root) return;
  // Static releases serve one page for every query; show the view the URL asks for.
  function showView() {
    const results = [...new URLSearchParams(location.search).keys()].some(key => keys.includes(key));
    document.querySelectorAll('[data-docs-landing]').forEach(element => { element.hidden = results; });
    root.hidden = !results;
    const search = document.getElementById('docs-search-input');
    if (search) search.value = params().get('q') || '';
    return results;
  }
  const form = root.querySelector('form'), results = root.querySelector('[data-docs-results]');
  const pages = root.querySelector('[data-docs-pages]'), error = root.querySelector('[data-docs-error]');
  let inventory, pending;
  async function load() {
    if (inventory) return inventory;
    if (!pending) pending = fetch('/docs/_search.json', {cache: 'no-cache'}).then(response => {
      if (!response.ok) throw new Error('Search unavailable');
      return response.json();
    }).then(data => { inventory = data.entries; return inventory; }).finally(() => { pending = null; });
    return pending;
  }
  let generation = 0;
  async function render() {
    const current = ++generation, query = params();
    for (const key of keys.slice(0, 4)) form.elements[key].value = query.get(key) || '';
    try {
      const entries = await load();
      if (current !== generation) return;
      error.hidden = true;
      const terms = (query.get('q') || '').trim().toLowerCase().split(/\s+/).filter(Boolean);
      const filters = {folder: query.get('folder'), category: categorySlug(query.get('category') || ''), group: query.get('group')};
      const matches = entries.filter(entry => Object.entries(filters).every(([key, value]) => !value || value === entry[key] || (key === 'folder' && entry.folder.startsWith(value + '/')))
        && terms.every(term => (entry.title + ' ' + entry.path + ' ' + entry.text).toLowerCase().includes(term)));
      const page = Math.max(1, Math.min(parseInt(query.get('page'), 10) || 1, Math.ceil(matches.length / 40) || 1));
      results.replaceChildren(); pages.replaceChildren();
      for (const entry of matches.slice((page - 1) * 40, page * 40)) {
        const item = document.createElement('li'), link = document.createElement('a'), path = document.createElement('code');
        link.href = entry.href + '?' + query; link.textContent = entry.title; path.textContent = entry.path;
        item.append(link, document.createElement('br'), path, document.createTextNode(' · ' + entry.group));
        results.append(item);
      }
      root.dataset.docsQuery = location.search;
      root.dataset.docsReady = 'true';
      root.querySelector('[data-docs-count]').textContent = matches.length + ' documents';
      root.querySelector('[data-docs-empty]').hidden = matches.length !== 0;
      for (const [label, target] of [['Previous', page - 1], ['Next', page + 1]]) {
        if (target < 1 || (target - 1) * 40 >= matches.length) continue;
        const link = document.createElement('a'), next = new URLSearchParams(query);
        next.set('page', target); link.href = '/docs/?' + next + '#docs-library-heading'; link.textContent = label;
        link.addEventListener('click', event => { event.preventDefault(); navigate(link.href); });
        pages.append(link, document.createTextNode(' '));
      }
    } catch (_) { if (current === generation) error.hidden = false; }
  }
  function navigate(url) { history.pushState(null, '', url); if (showView()) render(); }
  form.addEventListener('submit', event => {
    event.preventDefault();
    const query = new URLSearchParams(new FormData(form));
    for (const [key, value] of [...query]) if (!value) query.delete(key);
    navigate('/docs/?' + query + '#docs-library-heading');
  });
  root.querySelector('[data-docs-clear]').addEventListener('click', event => { event.preventDefault(); navigate('/docs/#docs-library-heading'); });
  root.querySelector('[data-docs-retry]').addEventListener('click', render);
  window.addEventListener('popstate', () => { if (showView()) render(); });
  if (showView()) render();
})();
