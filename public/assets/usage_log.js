(function () {
  'use strict';
  // Usage log (local_telemetry): an opt-in record of page views and clicks
  // that stays in this browser until the visitor uploads or copies it from
  // Tools → Usage log. Loaded only while the "Usage reports" flag is on.
  //
  // Storage (internal browser state, JSON): forum_usage_log =
  //   { "recording": true, "entries": [ { "t": 1763150000, "type": "view", "page": "/threads" }, ... ] }
  //
  // The log text (what the visitor reviews and uploads) is one header line,
  // then one tab-separated line per entry; dt is seconds since the previous entry:
  //   usage-log v1
  //   view    <dt> <page>
  //   click   <dt> <page> <region> <name> <destination page or "-">
  //   submit  <dt> <page> <region> <name>
  // Pages are kinds with IDs removed (UsagePageKind on the server, kind() here).
  // Nothing typed is ever read.
  const key = 'forum_usage_log';
  const MAX_ENTRIES = 2000;
  const MAX_AGE = 30 * 24 * 3600;
  const LABEL_LENGTH = 40;
  const root = document.documentElement;
  const words = new Set((root.getAttribute('data-usage-words') || '').split(' ').filter(Boolean));
  const REGIONS = [
    ['.account-menu', 'menu'],
    ['.tools-nav', 'tools-bar'],
    ['.site-header', 'navbar'],
    ['.thread-card, .thread-row, .message-list-row, .person-head', 'list'],
    ['.post-card, article', 'post'],
  ];
  const OUTER_REGIONS = [['footer', 'footer'], ['main', 'main']];

  function read() {
    try {
      const state = JSON.parse(localStorage.getItem(key) || 'null');
      if (!state || typeof state !== 'object') return { recording: false, entries: [] };
      return { recording: state.recording === true, entries: Array.isArray(state.entries) ? state.entries : [] };
    } catch (error) { return null; }
  }
  function write(state) {
    try { localStorage.setItem(key, JSON.stringify(state)); return true; } catch (error) { return false; }
  }
  function available() {
    try {
      localStorage.setItem(key + '_probe', '1');
      localStorage.removeItem(key + '_probe');
      return read() !== null;
    } catch (error) { return false; }
  }

  // Same rule as UsagePageKind::fromPath: route words stay, everything else is *.
  function kind(path) {
    const parts = String(path || '').split(/[?#]/)[0].split('/').filter(Boolean);
    return '/' + parts.map(part => (words.has(part) ? part : '*')).join('/');
  }
  function clean(value) {
    return String(value || '').replace(/\s+/g, ' ').trim().slice(0, LABEL_LENGTH);
  }
  function region(element) {
    for (const [selector, name] of REGIONS) {
      if (element.closest(selector)) return name;
    }
    const form = element.closest('form');
    if (form && form.querySelector('textarea')) return 'composer';
    for (const [selector, name] of OUTER_REGIONS) {
      if (element.closest(selector)) return name;
    }
    return 'page';
  }
  function name(element) {
    const tab = element.getAttribute('data-tab');
    if (tab) return 'tab:' + clean(tab);
    const tone = element.getAttribute('data-tone');
    if (tone) return 'tool:' + clean(tone);
    const action = element.getAttribute('data-action');
    if (action) return 'action:' + clean(action);
    const label = clean(element.getAttribute('aria-label') || element.getAttribute('title') || element.textContent);
    return label !== '' ? label : element.tagName.toLowerCase();
  }
  function destination(element) {
    if (element.tagName !== 'A' || !element.getAttribute('href')) return '-';
    try {
      const url = new URL(element.href, location.href);
      return url.origin === location.origin ? kind(url.pathname) : 'external';
    } catch (error) { return '-'; }
  }

  const currentPage = root.getAttribute('data-usage-page') || kind(location.pathname);
  // The log page records nothing about itself, so reviewing and uploading stays out of the log.
  const quiet = currentPage === '/tools/usage-log';

  function add(entry) {
    if (quiet) return;
    const state = read();
    if (!state || !state.recording) return;
    entry.t = Math.floor(Date.now() / 1000);
    entry.page = currentPage;
    state.entries.push(entry);
    write(trim(state, entry.t));
  }
  function trim(state, now) {
    const fresh = state.entries.filter(entry => entry && typeof entry.t === 'number' && entry.t > now - MAX_AGE);
    state.entries = fresh.slice(-MAX_ENTRIES);
    return state;
  }
  function field(value) { return String(value).replace(/[\t\r\n]+/g, ' '); }
  function text(entries) {
    const lines = ['usage-log v1'];
    let previous = null;
    for (const entry of entries) {
      const dt = previous === null ? 0 : Math.max(0, entry.t - previous);
      previous = entry.t;
      const base = [entry.type, dt, entry.page];
      if (entry.type === 'click') base.push(entry.region, entry.name, entry.to);
      if (entry.type === 'submit') base.push(entry.region, entry.name);
      lines.push(base.map(field).join('\t'));
    }
    return lines.join('\n') + '\n';
  }

  document.addEventListener('click', event => {
    const target = event.target instanceof Element ? event.target.closest('a, button, summary, [role="button"]') : null;
    if (!target) return;
    add({ type: 'click', region: region(target), name: name(target), to: destination(target) });
  }, true);
  document.addEventListener('submit', event => {
    const form = event.target instanceof HTMLFormElement ? event.target : null;
    if (!form) return;
    const submitter = event.submitter instanceof Element ? event.submitter : form;
    add({ type: 'submit', region: region(form), name: name(submitter) });
  }, true);
  function view() { add({ type: 'view' }); }
  window.addEventListener('pageshow', event => { if (event.persisted) view(); });
  view();

  window.forumUsageLog = {
    available,
    kind,
    state() {
      const state = read() || { recording: false, entries: [] };
      return { recording: state.recording, count: state.entries.length };
    },
    text() { const state = read(); return text(state ? trim(state, Math.floor(Date.now() / 1000)).entries : []); },
    // A copy of the entries the text is made from, each with dt (seconds since the previous one).
    entries() {
      const state = read();
      let previous = null;
      return (state ? trim(state, Math.floor(Date.now() / 1000)).entries : []).map(entry => {
        const dt = previous === null ? 0 : Math.max(0, entry.t - previous);
        previous = entry.t;
        return { ...entry, dt };
      });
    },
    setRecording(on) {
      const state = read() || { recording: false, entries: [] };
      state.recording = on === true;
      return write(state);
    },
    clear() {
      const state = read() || { recording: false, entries: [] };
      state.entries = [];
      return write(state);
    },
  };
})();
