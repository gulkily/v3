(() => {
  'use strict';
  const keys = ['q', 'folder', 'category', 'group', 'page'];
  const params = () => {
    const source = new URLSearchParams(location.search), result = new URLSearchParams();
    // Keep the URL's own order, as the server does for return links.
    for (const [key, value] of source) if (keys.includes(key) && !result.has(key)) result.set(key, value);
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
  const form = document.querySelector('.docs-search'), results = root.querySelector('[data-docs-results]');
  const pages = root.querySelector('[data-docs-pages]'), error = root.querySelector('[data-docs-error]');
  const labels = {q: 'Search', group: 'Collection', category: 'Category', folder: 'Folder'};
  const pageSize = 40;
  // Mirrors the template's results URL: never empty, so it always opens the results view.
  const resultsUrl = values => {
    const query = new URLSearchParams();
    for (const [key, value] of Object.entries(values)) if (value) query.set(key, value);
    return '/docs/?' + (query.toString() || 'page=1');
  };
  const element = (tag, attributes = {}, ...children) => {
    const node = document.createElement(tag);
    for (const [name, value] of Object.entries(attributes)) node.setAttribute(name, value);
    node.append(...children);
    return node;
  };
  const decorative = text => element('span', {'aria-hidden': 'true'}, text);
  // Static releases serve one page for every query; show the view the URL asks for.
  function showView() {
    const showResults = [...new URLSearchParams(location.search).keys()].some(key => keys.includes(key));
    document.querySelectorAll('[data-docs-landing]').forEach(node => { node.hidden = showResults; });
    document.querySelectorAll('[data-docs-results-only]').forEach(node => { node.hidden = !showResults; });
    root.hidden = !showResults;
    form.elements.q.value = params().get('q') || '';
    return showResults;
  }
  let inventory, pending;
  async function load() {
    if (inventory) return inventory;
    if (!pending) pending = fetch('/docs/_search.json', {cache: 'no-cache'}).then(response => {
      if (!response.ok) throw new Error('Search unavailable');
      return response.json();
    }).then(data => { inventory = data.entries; return inventory; }).finally(() => { pending = null; });
    return pending;
  }
  function activeFilters(query) {
    const active = {};
    for (const key of Object.keys(labels)) {
      const value = (query.get(key) || '').trim();
      if (value) active[key] = key === 'category' ? categorySlug(value) : value;
    }
    return active;
  }
  function categoryLabel(slug) {
    const link = root.querySelector(`[data-docs-filter="category"][data-docs-value="${CSS.escape(slug)}"]`);
    return link ? link.dataset.docsLabel : slug;
  }
  function renderControls(active) {
    form.querySelectorAll('[data-docs-filter-input]').forEach(input => input.remove());
    for (const [key, value] of Object.entries(active)) if (key !== 'q') form.append(element('input', {type: 'hidden', name: key, value, 'data-docs-filter-input': ''}));
    const chips = root.querySelector('[data-docs-chips]');
    chips.replaceChildren();
    chips.hidden = Object.keys(active).length === 0;
    for (const [key, value] of Object.entries(active)) {
      const label = labels[key] + ': ' + (key === 'category' ? categoryLabel(value) : value);
      const rest = {...active}; delete rest[key];
      chips.append(element('li', {}, element('a', {class: 'docs-chip', href: resultsUrl(rest), 'aria-label': 'Remove ' + label}, label + ' ', decorative('×'))));
    }
    chips.append(element('li', {}, element('a', {href: resultsUrl({})}, 'Clear all')));
    root.querySelectorAll('[data-docs-filter]').forEach(link => {
      const key = link.dataset.docsFilter, value = link.dataset.docsValue;
      link.href = resultsUrl({q: active.q, [key]: value});
      if (active[key] === value) link.setAttribute('aria-current', 'page'); else link.removeAttribute('aria-current');
    });
    root.querySelectorAll('[data-docs-folder-branch]').forEach(details => {
      const folder = details.dataset.docsFolderBranch;
      details.open = Boolean(active.folder && (active.folder === folder || active.folder.startsWith(folder + '/')));
    });
  }
  function renderRow(entry, active, query) {
    const meta = element('p', {class: 'docs-result__meta'}, element('code', {class: 'docs-result__path'}, entry.path), ' ', element('span', {class: 'docs-badge'}, entry.categoryLabel));
    if (!active.group && entry.group !== 'Current guidance') meta.append(' ', element('span', {class: 'docs-badge'}, entry.group));
    const row = element('li', {class: 'docs-result'}, element('a', {class: 'docs-result__title', href: entry.href + '?' + query}, entry.title));
    if (entry.summary) row.append(element('p', {class: 'docs-result__summary'}, entry.summary));
    row.append(meta);
    return row;
  }
  let generation = 0;
  async function render() {
    const current = ++generation, query = params(), active = activeFilters(query);
    renderControls(active);
    try {
      const entries = await load();
      if (current !== generation) return;
      error.hidden = true;
      const terms = (active.q || '').toLowerCase().split(/\s+/).filter(Boolean);
      const matches = entries.filter(entry => (!active.folder || entry.folder === active.folder || entry.folder.startsWith(active.folder + '/'))
        && (!active.category || entry.category === active.category) && (!active.group || entry.group === active.group)
        && terms.every(term => (entry.title + ' ' + entry.path + ' ' + entry.text).toLowerCase().includes(term)));
      const pageCount = Math.max(1, Math.ceil(matches.length / pageSize));
      const page = Math.max(1, Math.min(parseInt(query.get('page'), 10) || 1, pageCount));
      results.replaceChildren(...matches.slice((page - 1) * pageSize, page * pageSize).map(entry => renderRow(entry, active, query)));
      pages.replaceChildren();
      pages.hidden = pageCount === 1;
      if (page > 1) pages.append(element('a', {href: resultsUrl({...active, page: page - 1})}, decorative('←'), ' Previous'), ' ');
      pages.append(element('span', {'aria-current': 'page'}, `Page ${page} of ${pageCount}`));
      if (page < pageCount) pages.append(' ', element('a', {href: resultsUrl({...active, page: page + 1})}, 'Next ', decorative('→')));
      root.dataset.docsQuery = location.search;
      root.dataset.docsReady = 'true';
      root.querySelector('[data-docs-count]').textContent = matches.length.toLocaleString('en-US') + (matches.length === 1 ? ' document' : ' documents');
      root.querySelector('[data-docs-empty]').hidden = matches.length !== 0;
    } catch (_) { if (current === generation) error.hidden = false; }
  }
  function navigate(url) { history.pushState(null, '', url); if (showView()) render(); }
  form.addEventListener('submit', event => {
    event.preventDefault();
    const query = new URLSearchParams(new FormData(form));
    for (const [key, value] of [...query]) if (!value) query.delete(key);
    navigate(resultsUrl(Object.fromEntries(query)));
  });
  // Same-page results links update in place, like the form.
  document.addEventListener('click', event => {
    const link = event.target.closest('a[href^="/docs/?"]');
    if (!link || event.defaultPrevented || event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    if (!root.contains(link) && !link.closest('.docs-page')) return;
    event.preventDefault();
    navigate(link.href);
    // Keep keyboard and screen-reader position with the new results.
    root.querySelector('#docs-library-heading').focus();
  });
  root.querySelector('[data-docs-retry]').addEventListener('click', render);
  window.addEventListener('popstate', () => { if (showView()) render(); });
  if (showView()) render();
})();
